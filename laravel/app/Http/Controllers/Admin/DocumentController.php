<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\Concerns\ExtractsText;
use App\Http\Controllers\Controller;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    use ExtractsText;

    private array $supported = ['txt', 'md', 'html', 'htm', 'pdf'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search', '');

        $query = Document::query()->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        $documents = $query->paginate(25)->withQueryString();

        $counts = Document::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.documents', compact('documents', 'counts', 'status', 'search'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'  => 'required|file|mimes:txt,md,pdf,html,htm|max:20480',
            'title' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $path = $file->store('uploads', 'local');
        $full = Storage::disk('local')->path($path);

        $text = $this->extractText($full);

        if (empty(trim($text))) {
            return back()->with('error', 'File produced no indexable text.');
        }

        $title = trim($request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        $doc = Document::create([
            'title'       => $title,
            'source_path' => $file->getClientOriginalName(),
            'data_type'   => 'public',
            'user_id'     => null,
            'raw_text'    => $text,
            'status'      => 'pending',
        ]);

        IndexDocumentJob::dispatch($doc->id)->onQueue('uploads');

        return back()->with('success', "Uploaded \"{$title}\" — queued for indexing.");
    }

    public function reindex(int $id)
    {
        $doc = Document::findOrFail($id);
        $doc->update(['status' => 'pending', 'error_message' => null, 'chunks_indexed' => null]);
        IndexDocumentJob::dispatch($doc->id)->onQueue('uploads');

        return back()->with('success', "Re-queued \"{$doc->title}\" for indexing.");
    }

    public function reindexFailed()
    {
        $failed = Document::where('status', 'failed')->get();

        foreach ($failed as $doc) {
            $doc->update(['status' => 'pending', 'error_message' => null]);
            IndexDocumentJob::dispatch($doc->id)->onQueue('uploads');
        }

        return back()->with('success', "Re-queued {$failed->count()} failed documents.");
    }

    public function destroy(int $id)
    {
        $doc         = Document::findOrFail($id);
        $aiUrl       = rtrim(config('services.python_api.url', env('AI_API_URL', 'http://python-api:8000')), '/');
        $internalKey = config('services.python_api.internal_key', '');
        $http        = $internalKey ? Http::withHeaders(['X-Internal-Key' => $internalKey]) : Http::new();

        try {
            $http->timeout(10)->delete("{$aiUrl}/index/document/{$doc->id}");
        } catch (\Exception) {}

        $doc->delete();

        return back()->with('success', "\"{$doc->title}\" deleted.");
    }
}
