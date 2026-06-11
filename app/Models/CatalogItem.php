<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItem extends Model
{
    public const TYPE_SERVICE = 'service';

    public const TYPE_PRODUCT = 'product';

    public const TYPES = [
        self::TYPE_SERVICE => 'Service',
        self::TYPE_PRODUCT => 'Product',
    ];

    protected $fillable = [
        'department_id',
        'created_by',
        'type',
        'name',
        'description',
        'unit_price',
        'taxable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
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
        if ($user->canViewReports()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->whereNull('department_id')
                ->orWhere('department_id', $user->department_id);
        });
    }
}
