<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // QuickBooks-style purchase order header fields.
        Schema::table('purchase_records', function (Blueprint $table): void {
            $table->text('ship_to')->nullable()->after('purchase_date');
            $table->string('terms', 60)->nullable()->after('ship_to');
            $table->date('expected_date')->nullable()->after('terms');
        });

        Schema::create('purchase_record_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_record_id')->constrained('purchase_records')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->foreignId('catalog_item_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();

            // Explicit constraint names: InnoDB names are database-wide unique.
            $table->foreign('catalog_item_id', 'fk_purchase_item_catalog')
                ->references('id')->on('catalog_items')->nullOnDelete();

            $table->index(['purchase_record_id', 'position'], 'idx_purchase_item_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_record_items');

        Schema::table('purchase_records', function (Blueprint $table): void {
            $table->dropColumn(['ship_to', 'terms', 'expected_date']);
        });
    }
};
