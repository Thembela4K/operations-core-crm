<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseRecord extends Model
{
    public const STATUS_PLANNED = 'Planned';

    public const STATUS_ORDERED = 'Ordered';

    public const STATUS_RECEIVED = 'Received';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_ORDERED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    /**
     * QuickBooks-style payment terms shared with invoices.
     */
    public const PAYMENT_TERMS = [
        'Due on receipt' => 0,
        'Net 15' => 15,
        'Net 30' => 30,
        'Net 45' => 45,
        'Net 60' => 60,
    ];

    protected $fillable = [
        'supplier_id',
        'requisition_id',
        'department_id',
        'created_by',
        'purchase_number',
        'title',
        'status',
        'purchase_date',
        'ship_to',
        'terms',
        'expected_date',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'expected_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRecordItem::class)->orderBy('position');
    }

    /**
     * Replaces the line items and keeps the stored amount equal to their total,
     * because reports and ledger posting read `amount`.
     */
    public function syncItems(array $items): void
    {
        DB::transaction(function () use ($items): void {
            $this->items()->delete();

            foreach (array_values($items) as $index => $item) {
                $quantity = round((float) ($item['quantity'] ?? 1), 2);
                $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);

                $this->items()->create([
                    'position' => $index + 1,
                    'catalog_item_id' => $item['catalog_item_id'] ?? null,
                    'description' => (string) $item['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                ]);
            }

            $total = round((float) $this->items()->sum('line_total'), 2);
            $this->forceFill(['amount' => $total])->save();
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports() || $user->canManageFinance()) {
            return $query;
        }

        return $query->where('department_id', $user->department_id);
    }
}
