<?php

namespace App\Http\Controllers;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class NoteController extends Controller
{
    public function store(Request $request)
    {
        $maxLen = (int) Setting::get('note_max_length', 10000);
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'content' => "required|string|min:10|max:{$maxLen}",
        ]);

        $title = trim($data['title'] ?? '') ?: 'Note — ' . now()->format('M j, Y g:i A');

        $doc = Document::create([
            'title'     => $title,
            'data_type' => 'user',
            'user_id'   => Auth::id(),
            'raw_text'  => $data['content'],
            'status'    => 'pending',
        ]);

        IndexDocumentJob::dispatch($doc->id)->onQueue('uploads');

        return response()->json(['ok' => true, 'doc_id' => $doc->id, 'title' => $title]);
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search', '');

        $query = Document::where('data_type', 'user')
            ->where('user_id', Auth::id())
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $notes = $query->paginate(25)->withQueryString();

        $counts = Document::where('data_type', 'user')
            ->where('user_id', Auth::id())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('notes.index', compact('notes', 'counts', 'status', 'search'));
    }

    public function edit(int $id)
    {
        $note = Document::where('data_type', 'user')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return view('notes.edit', compact('note'));
    }

    public function update(Request $request, int $id)
    {
        $note = Document::where('data_type', 'user')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $maxLen = (int) Setting::get('note_max_length', 10000);
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'content' => "required|string|min:10|max:{$maxLen}",
        ]);

        $title = trim($data['title'] ?? '') ?: $note->title;

        $note->update([
            'title'          => $title,
            'raw_text'       => $data['content'],
            'status'         => 'pending',
            'error_message'  => null,
            'chunks_indexed' => null,
        ]);

        IndexDocumentJob::dispatch($note->id)->onQueue('uploads');

        return redirect('/my-notes')->with('success', "Note saved and queued for indexing.");
    }

    public function destroy(int $id)
    {
        $note = Document::where('data_type', 'user')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $aiUrl       = rtrim(config('services.python_api.url', env('AI_API_URL', 'http://python-api:8000')), '/');
        $internalKey = config('services.python_api.internal_key', '');
        $http        = $internalKey ? Http::withHeaders(['X-Internal-Key' => $internalKey]) : Http::new();

        try {
            $http->timeout(10)->delete("{$aiUrl}/index/document/{$note->id}");
        } catch (\Exception) {}

        $note->delete();

        return redirect('/my-notes')->with('success', "Note deleted.");
    }
}
