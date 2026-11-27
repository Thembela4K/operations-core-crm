<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Sales Order (report 2.4) records the company's commitment to deliver scope
 * agreed in an accepted quotation. One order may be fulfilled by many job cards.
 */
class SalesOrder extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_CONFIRMED = 'Confirmed';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_FULFILLED = 'Fulfilled';

    public const STATUS_CLOSED = 'Closed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FULFILLED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    /** Statuses that still accept new job cards. */
    public const OPEN_STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_IN_PROGRESS,
    ];

    protected $fillable = [
        'sales_quotation_id',
        'client_id',
        'department_id',
        'created_by',
        'order_number',
        'title',
        'status',
        'client_po_reference',
        'scope',
        'value',
        'order_date',
        'delivery_date',
        'delivery_requirements',
        'notes',
        'accepted_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'order_date' => 'date',
            'delivery_date' => 'date',
            'accepted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(SalesQuotation::class, 'sales_quotation_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_FULFILLED, self::STATUS_CLOSED, self::STATUS_CANCELLED], true);
    }

    public function canAcceptJobCards(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewPortfolio()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('department_id', $user->department_id)
                ->orWhere('created_by', $user->id);
        });
    }
}
