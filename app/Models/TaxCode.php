<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxCode extends Model
{
    public const DIRECTION_OUTPUT = 'output';

    public const DIRECTION_INPUT = 'input';

    /** Business rule (ADR-014): VAT is fixed at 15%. */
    public const STANDARD_RATE = 15.00;

    protected $fillable = [
        'code',
        'name',
        'rate',
        'direction',
        'account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isOutput(): bool
    {
        return $this->direction === self::DIRECTION_OUTPUT;
    }
}
