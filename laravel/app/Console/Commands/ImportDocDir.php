<?php

namespace App\Console\Commands;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use Illuminate\Console\Command;

class ImportDocDir extends Command
{
    use \App\Console\Commands\Concerns\ExtractsText;
    protected $signature = 'docs:import-dir
        {dir : Directory of files to import (absolute or relative to storage/app/docs/)}
        {--data-type=public : public or user}
        {--user-id= : User ID for private docs}
        {--skip-existing : Skip files already in the documents table by source_path}';

    protected $description = 'Batch import a directory of .txt, .md, or .html files into the knowledge base';

    private array $supported = ['txt', 'md', 'html', 'htm', 'pdf'];

    public function handle(): int
    {
        $dir = $this->argument('dir');

        if (! str_starts_with($dir, '/')) {
            $dir = storage_path('app/docs/' . $dir);
        }

        if (! is_dir($dir)) {
            $this->error("Directory not found: {$dir}");
            return self::FAILURE;
        }

        $files = $this->collectFiles($dir);

        if (empty($files)) {
            $this->warn("No supported files found in {$dir}");
            return self::SUCCESS;
        }

        $dataType = $this->option('data-type');
        $userId   = $this->option('user-id') ? (int) $this->option('user-id') : null;
        $skip     = $this->option('skip-existing');

        $this->info("Found " . count($files) . " files — queuing...");

        $queued  = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $file) {
            if ($skip && Document::where('source_path', $file)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $text = $this->extractText($file);

            if (empty(trim($text))) {
                $bar->advance();
                continue;
            }

            $doc = Document::create([
                'title'       => pathinfo($file, PATHINFO_FILENAME),
                'source_path' => $file,
                'data_type'   => $dataType,
                'user_id'     => $userId,
                'raw_text'    => $text,
                'status'      => 'pending',
            ]);

            IndexDocumentJob::dispatch($doc->id)->onQueue('uploads');
            $queued++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Queued: {$queued}" . ($skipped ? ", skipped (already imported): {$skipped}" : ''));

        return self::SUCCESS;
    }

    private function collectFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $this->supported)) {
                $files[] = $file->getRealPath();
            }
        }
        sort($files);
        return $files;
    }

}
