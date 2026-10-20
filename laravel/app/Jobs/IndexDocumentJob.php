<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 3;

    public function __construct(public readonly int $documentId) {}

    public function handle(): void
    {
        $doc = Document::findOrFail($this->documentId);
        $doc->markIndexing();

        $apiUrl      = rtrim(config('services.python_api.url', env('AI_API_URL', 'http://python-api:8000')), '/');
        $internalKey = config('services.python_api.internal_key', '');
        $http        = $internalKey ? Http::withHeaders(['X-Internal-Key' => $internalKey]) : Http::new();

        try {
            $response = $http->timeout(280)->post("{$apiUrl}/index/document", [
                'text'      => $doc->raw_text,
                'source'    => $doc->source_path ?? $doc->title,
                'data_type' => $doc->data_type,
                'user_id'   => $doc->user_id,
                'doc_id'    => $doc->id,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            $doc->markIndexed($response->json('chunks_indexed', 0));
            Log::info("Document {$doc->id} indexed: {$doc->chunks_indexed} chunks");

        } catch (\Throwable $e) {
            $doc->markFailed($e->getMessage());
            Log::error("Document {$doc->id} indexing failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Document::find($this->documentId)?->markFailed($e->getMessage());
    }
}
