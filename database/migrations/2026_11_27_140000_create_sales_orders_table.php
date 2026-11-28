<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales Orders (report 2.4): a distinct record created when a quotation is
     * accepted or work is awarded, which may then generate one or more Job
     * Cards.
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_quotation_id')->nullable();
            $table->foreignId('client_id');
            $table->foreignId('department_id')->nullable();
            $table->foreignId('created_by')->nullable();

            $table->string('order_number')->unique();
            $table->string('title');
            $table->string('status')->default('Draft');
            $table->string('client_po_reference', 80)->nullable();

            $table->text('scope')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->date('order_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('delivery_requirements')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // One accepted quotation produces at most one order; duplicates are
            // blocked here as well as in the controller.
            $table->unique('sales_quotation_id', 'uq_sales_order_quotation');

            $table->foreign('sales_quotation_id', 'fk_sales_orders_quotation')
                ->references('id')->on('sales_quotations')->nullOnDelete();
            $table->foreign('client_id', 'fk_sales_orders_client')
                ->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('department_id', 'fk_sales_orders_department')
                ->references('id')->on('departments')->nullOnDelete();
            $table->foreign('created_by', 'fk_sales_orders_creator')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('job_cards', function (Blueprint $table): void {
            $table->foreignId('sales_order_id')->nullable()->after('sales_quotation_id');
            $table->foreign('sales_order_id', 'fk_job_cards_sales_order')
                ->references('id')->on('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_cards', function (Blueprint $table): void {
            $table->dropForeign('fk_job_cards_sales_order');
            $table->dropColumn('sales_order_id');
        });

        Schema::dropIfExists('sales_orders');
    }
};
