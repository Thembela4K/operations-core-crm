<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentText extends Model
{
    public const STATUS_INDEXED = 'indexed';

    public const STATUS_UNSUPPORTED = 'unsupported';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'document_id',
        'status',
        'content',
        'char_count',
        'extracted_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'extracted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
