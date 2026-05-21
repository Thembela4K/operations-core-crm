<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportantDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'due_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function tenderProposal(): BelongsTo
    {
        return $this->belongsTo(TenderProposal::class);
    }
}
