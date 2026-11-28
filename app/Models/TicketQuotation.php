<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A priced quotation the IT Officer prepares for a ticket, later sent to the
 * directors as a requisition.
 */
class TicketQuotation extends Model
{
    protected $fillable = [
        'ticket_id',
        'supplier_id',
        'supplier_name',
        'reference',
        'valid_until',
        'subtotal',
        'total',
        'notes',
        'prepared_by',
        'requisition_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketQuotationItem::class)->orderBy('position');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null || $this->requisition_id !== null;
    }

    public function supplierLabel(): string
    {
        return $this->supplier?->name ?: ($this->supplier_name ?: 'No supplier');
    }
}
