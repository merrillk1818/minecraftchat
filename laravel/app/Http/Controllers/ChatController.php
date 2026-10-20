<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Setting;
use App\Rules\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private string $aiUrl;

    private string $internalKey;

    public function __construct()
    {
        $this->aiUrl        = rtrim(config('services.python_api.url', env('AI_API_URL', 'http://python-api:8000')), '/');
        $this->internalKey  = config('services.python_api.internal_key', '');
    }

    private function aiHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $headers = $this->internalKey ? ['X-Internal-Key' => $this->internalKey] : [];
        return Http::withHeaders($headers);
    }

    public function chat(Request $request)
    {
        $maxLen = (int) Setting::get('chat_max_length', 1000);
        $request->validate([
            'message'               => "required|string|min:3|max:{$maxLen}",
            'session_id'            => 'nullable|integer',
            'cf-turnstile-response' => ['required', new Turnstile(config('services.turnstile.secret_key_chat'))],
        ]);

        $user        = Auth::user();
        $chatSession = null;

        if ($user) {
            if ($request->integer('session_id')) {
                $chatSession = ChatSession::where('id', $request->integer('session_id'))
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            } else {
                $chatSession = ChatSession::create([
                    'user_id' => $user->id,
                    'title'   => mb_substr($request->input('message'), 0, 60),
                ]);
            }

            $chatSession->messages()->create([
                'role'    => 'user',
                'content' => $request->input('message'),
            ]);
        }

        $pythonSessionId = $chatSession ? (string) $chatSession->id : $request->session()->getId();

        // Fetch prior messages from this session (exclude the message we just saved)
        $history = [];
        if ($chatSession) {
            $prior = $chatSession->messages()
                ->orderBy('id', 'asc')
                ->get(['role', 'content']);

            // Drop the last entry (the user message we just saved)
            $prior = $prior->slice(0, max(0, $prior->count() - 1));

            // Keep at most the last 10 messages (5 exchanges)
            $history = $prior->slice(-10)
                ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
                ->values()
                ->toArray();
        }

        try {
            $payload = [
                'message'       => $request->input('message'),
                'session_id'    => $pythonSessionId,
                'history'       => $history,
                'session_title' => $chatSession?->title,
            ];
            if ($user) {
                $payload['user_id'] = $user->id;
            }

            $response = $this->aiHttp()->timeout(120)->post("{$this->aiUrl}/chat", $payload);

            if ($response->failed()) {
                Log::error('AI service error', [
                    'session_id' => $pythonSessionId,
                    'status'     => $response->status(),
                    'body'       => mb_substr($response->body(), 0, 2000),
                ]);

                return response()->json(['error' => 'AI service error'], 503);
            }

            $data    = $response->json();
            $answer  = $data['response'] ?? '';

            if ($chatSession) {
                $chatSession->messages()->create([
                    'role'    => 'assistant',
                    'content' => $answer,
                ]);
            }

            return response()->json([
                'response'   => $answer,
                'session_id' => $chatSession?->id,
            ]);

        } catch (\Exception $e) {
            Log::error('AI service unreachable', [
                'session_id' => $pythonSessionId,
                'message'    => $e->getMessage(),
            ]);

            return response()->json(['error' => 'AI service unreachable'], 503);
        }
    }

    public function sessions()
    {
        $sessions = Auth::user()
            ->chatSessions()
            ->latest()
            ->limit(50)
            ->get(['id', 'title', 'created_at']);

        return response()->json($sessions);
    }

    public function sessionMessages(int $id)
    {
        $session = ChatSession::where('id', $id)
            ->where('user_id', Auth::id())
            ->with('messages')
            ->firstOrFail();

        return response()->json([
            'session' => ['id' => $session->id, 'title' => $session->title],
            'messages' => $session->messages->map(fn ($m) => [
                'role'    => $m->role,
                'content' => $m->content,
            ]),
        ]);
    }

    public function deleteSession(int $id)
    {
        ChatSession::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail()
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function status()
    {
        $apiOk = false;
        $groq  = ['ok' => false, 'model' => null, 'detail' => null];

        try {
            $res   = $this->aiHttp()->timeout(5)->get("{$this->aiUrl}/health");
            $apiOk = $res->successful();
            $groq  = $res->json('groq', $groq);
        } catch (\Exception $e) {
            Log::warning('Status check: python-api unreachable', ['message' => $e->getMessage()]);
        }

        $docCount = \App\Models\Document::where('status', 'indexed')->count();

        return response()->json([
            'services' => [
                'api'        => ['ok' => $apiOk],
                'groq'       => $groq,
                'opensearch' => ['docs' => $docCount],
            ],
        ]);
    }
}
