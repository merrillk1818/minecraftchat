<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScrapeUrlJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class DashboardController extends Controller
{
    public function index()
    {
        $docs = Document::selectRaw('status, count(*) as total, sum(chunks_indexed) as chunks')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $docStats = [
            'indexed'  => (int) ($docs['indexed']?->total  ?? 0),
            'pending'  => (int) ($docs['pending']?->total  ?? 0),
            'indexing' => (int) ($docs['indexing']?->total ?? 0),
            'failed'   => (int) ($docs['failed']?->total   ?? 0),
            'chunks'   => (int) ($docs['indexed']?->chunks ?? 0),
        ];

        $userStats = [
            'total' => User::count(),
            'admin' => User::where('role', 'admin')->count(),
        ];

        $queueStats = [
            'uploads'  => Queue::size('uploads'),
            'crawlers' => Queue::size('crawlers'),
            'default'  => Queue::size('default'),
        ];

        $aiUrl   = rtrim(config('services.python_api.url', env('AI_API_URL', 'http://python-api:8000')), '/');
        $apiOk   = false;
        $ollamaOk = false;

        try { $apiOk    = Http::timeout(3)->get("{$aiUrl}/health")->successful(); } catch (\Exception) {}
        try { $ollamaOk = Http::timeout(3)->get('http://ollama:11434/api/tags')->successful(); } catch (\Exception) {}

        return view('admin.dashboard', compact('docStats', 'userStats', 'queueStats', 'apiOk', 'ollamaOk'));
    }

    public function scrape(Request $request)
    {
        $request->validate(['url' => 'required|url|max:2048']);

        ScrapeUrlJob::dispatch($request->input('url'))->onQueue('crawlers');

        return back()->with('success', 'Scraping queued: ' . $request->input('url'));
    }
}
