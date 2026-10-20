<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'title',
        'source_path',
        'data_type',
        'user_id',
        'raw_text',
        'status',
        'chunks_indexed',
        'error_message',
    ];

    public function markIndexing(): void
    {
        $this->update(['status' => 'indexing', 'error_message' => null]);
    }

    public function markIndexed(int $chunks): void
    {
        $this->update(['status' => 'indexed', 'chunks_indexed' => $chunks]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error_message' => $error]);
    }
}
