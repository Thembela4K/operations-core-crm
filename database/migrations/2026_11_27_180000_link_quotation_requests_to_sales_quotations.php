<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report 2.1: a quotation request converts into a Sales Quotation while
     * keeping the originating request reference.
     */
    public function up(): void
    {
        Schema::table('sales_quotations', function (Blueprint $table): void {
            $table->foreignId('source_quotation_request_id')->nullable()->after('client_id');
            $table->foreign('source_quotation_request_id', 'fk_sales_quotations_source_request')
                ->references('id')->on('quotations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_quotations', function (Blueprint $table): void {
            $table->dropForeign('fk_sales_quotations_source_request');
            $table->dropColumn('source_quotation_request_id');
        });
    }
};
