<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    public const CATEGORIES = [
        'Rent',
        'Internet & Telecom',
        'Utilities',
        'Office Supplies',
        'Travel',
        'Software',
        'Hardware',
        'Professional Services',
        'Labour',
        'Marketing',
        'Insurance',
        'Maintenance',
        'Office',
        'Other',
    ];

    public const TYPES = ['Operational', 'Project', 'Labour'];

    public const STATUSES = ['Recorded', 'Paid', 'Pending', 'Cancelled'];

    protected $fillable = [
        'department_id',
        'supplier_id',
        'requisition_id',
        'recorded_by',
        'expense_number',
        'category',
        'expense_type',
        'payee',
        'expense_date',
        'duration_months',
        'amount',
        'vat_amount',
        'total_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'duration_months' => 'integer',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class)->orderBy('position');
    }

    /**
     * Recalculate the worksheet amount from its lines.
     */
    public function recalculateFromItems(): void
    {
        $amount = round((float) $this->items()->sum('line_total'), 2);

        $this->update([
            'amount' => $amount,
            'total_amount' => round($amount + (float) $this->vat_amount, 2),
        ]);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports()) {
            return $query;
        }

        return $query->where('department_id', $user->department_id);
    }
}
