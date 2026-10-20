<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;

class DocsStatus extends Command
{
    protected $signature = 'docs:status {--failed : Show only failed documents}';
    protected $description = 'Show indexing status of all documents';

    public function handle(): int
    {
        $counts = Document::selectRaw('status, count(*) as total, sum(chunks_indexed) as chunks')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $this->table(
            ['Status', 'Documents', 'Chunks Indexed'],
            collect(['pending', 'indexing', 'indexed', 'failed'])->map(fn ($s) => [
                $s,
                $counts[$s]?->total ?? 0,
                $counts[$s]?->chunks ?? '-',
            ])
        );

        if ($this->option('failed')) {
            $failed = Document::where('status', 'failed')->get(['id', 'title', 'error_message']);
            if ($failed->isNotEmpty()) {
                $this->newLine();
                $this->error('Failed documents:');
                $this->table(['ID', 'Title', 'Error'], $failed->map(fn ($d) => [
                    $d->id, $d->title, substr($d->error_message, 0, 80),
                ]));
            }
        }

        return self::SUCCESS;
    }
}
