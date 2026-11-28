<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketQuotationItem extends Model
{
    protected $fillable = [
        'ticket_quotation_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(TicketQuotation::class, 'ticket_quotation_id');
    }
}
