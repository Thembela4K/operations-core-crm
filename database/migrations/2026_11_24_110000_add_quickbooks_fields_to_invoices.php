<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QuickBooks-style invoice header fields.
     *
     * Reception creates invoices directly, so the form needs the same
     * reference fields the QuickBooks invoice form carries.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('po_number', 60)->nullable()->after('invoice_number');
            $table->text('ship_to')->nullable()->after('terms');
            $table->date('ship_date')->nullable()->after('ship_to');
            $table->string('ship_via', 60)->nullable()->after('ship_date');
            $table->text('customer_message')->nullable()->after('ship_via');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['po_number', 'ship_to', 'ship_date', 'ship_via', 'customer_message']);
        });
    }
};
