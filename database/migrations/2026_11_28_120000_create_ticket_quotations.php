<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The IT Officer prices a ticket: supplier, reference and line items. The
     * quotation is then sent to the directors as a requisition.
     */
    public function up(): void
    {
        Schema::create('ticket_quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id');
            $table->foreignId('supplier_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('reference', 80)->nullable();
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable();
            $table->foreignId('requisition_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id', 'fk_ticket_quotes_ticket')
                ->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('supplier_id', 'fk_ticket_quotes_supplier')
                ->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('prepared_by', 'fk_ticket_quotes_prepared_by')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('requisition_id', 'fk_ticket_quotes_requisition')
                ->references('id')->on('requisitions')->nullOnDelete();
        });

        Schema::create('ticket_quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_quotation_id');
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->foreign('ticket_quotation_id', 'fk_ticket_quote_items_quote')
                ->references('id')->on('ticket_quotations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_quotation_items');
        Schema::dropIfExists('ticket_quotations');
    }
};
