<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ExtractsText;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use Illuminate\Console\Command;

class ImportDoc extends Command
{
    use ExtractsText;

    protected $signature = 'docs:import
        {file : Path to a .txt, .md, .html, or .pdf file (absolute or relative to storage/app/docs/)}
        {--title= : Override document title (defaults to filename)}
        {--data-type=public : public or user}
        {--user-id= : User ID for private docs}';

    protected $description = 'Import a document into the knowledge base';

    private array $supported = ['txt', 'md', 'html', 'htm', 'pdf'];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! str_starts_with($file, '/')) {
            $file = storage_path('app/docs/' . $file);
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($ext, $this->supported)) {
            $this->error("Unsupported file type .{$ext} — use: " . implode(', ', $this->supported));
            return self::FAILURE;
        }

        $text = $this->extractText($file);

        if (empty(trim($text))) {
            $this->error('File produced no indexable text.');
            return self::FAILURE;
        }

        $title    = $this->option('title') ?: pathinfo($file, PATHINFO_FILENAME);
        $dataType = $this->option('data-type');
        $userId   = $this->option('user-id') ? (int) $this->option('user-id') : null;

        $doc = Document::create([
            'title'       => $title,
            'source_path' => $file,
            'data_type'   => $dataType,
            'user_id'     => $userId,
            'raw_text'    => $text,
            'status'      => 'pending',
        ]);

        IndexDocumentJob::dispatch($doc->id)->onQueue('uploads');

        $size = number_format(strlen($text) / 1024, 1);
        $this->info("Queued document #{$doc->id}: \"{$title}\" ({$size} KB) → uploads queue");

        return self::SUCCESS;
    }
}
