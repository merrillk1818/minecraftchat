<?php

namespace App\Jobs;

use App\Console\Commands\Concerns\ExtractsText;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ExtractsText;

    public int $timeout = 60;
    public int $tries   = 2;

    public function __construct(public readonly string $url) {}

    public function handle(): void
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'MinecraftChatBot/1.0 (educational crawler)'])
            ->get($this->url);

        if (!$response->successful()) {
            Log::warning("ScrapeUrlJob: HTTP {$response->status()} fetching {$this->url}");
            $this->fail("HTTP {$response->status()}");
            return;
        }

        $html = $response->body();
        $text = $this->cleanHtml($html);

        if (mb_strlen(trim($text)) < 50) {
            Log::warning("ScrapeUrlJob: too little text extracted from {$this->url}");
            $this->fail('Extracted text too short');
            return;
        }

        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m);
        $title = isset($m[1]) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
        $title = mb_substr($title ?: $this->url, 0, 255);

        $doc = Document::create([
            'title'       => $title,
            'source_path' => $this->url,
            'data_type'   => 'public',
            'user_id'     => null,
            'raw_text'    => $text,
            'status'      => 'pending',
        ]);

        IndexDocumentJob::dispatch($doc->id)->onQueue('uploads');
        Log::info("ScrapeUrlJob: document {$doc->id} created from {$this->url}");
    }
}
