<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an expense worksheet: labour, material, service or other, with a
 * quantity, unit cost and a duration in months.
 */
class ExpenseItem extends Model
{
    public const TYPES = ['Labour', 'Material', 'Service', 'Other'];

    protected $fillable = [
        'expense_id',
        'description',
        'item_type',
        'quantity',
        'unit_cost',
        'duration_months',
        'line_total',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'duration_months' => 'integer',
            'position' => 'integer',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Quantity x unit cost x duration.
     */
    public static function computeLineTotal(float $quantity, float $unitCost, int $durationMonths): float
    {
        return round($quantity * $unitCost * max(1, $durationMonths), 2);
    }
}
