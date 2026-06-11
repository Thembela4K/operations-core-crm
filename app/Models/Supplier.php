<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'supplier_code',
        'name',
        'contact_person',
        'email',
        'phone',
        'vat_number',
        'address',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PurchaseRecord::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports() || $user->canManageFinance()) {
            return $query;
        }

        return $query->whereHas('purchases', fn (Builder $purchase) => $purchase->where('department_id', $user->department_id));
    }
}
