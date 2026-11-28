<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expenses become a worksheet: an expense carries priced lines, and each
     * line carries a duration (months) so recurring spend such as rent, internet
     * or Datamatics labour can be costed for a period.
     *
     * Requisitions that have funds released also produce an expense, linked back
     * to the requisition.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('requisition_id')->nullable()->after('supplier_id');
            $table->string('expense_type')->default('Operational')->after('category');
            $table->unsignedSmallInteger('duration_months')->nullable()->after('expense_date');

            $table->foreign('requisition_id', 'fk_expenses_requisition')
                ->references('id')->on('requisitions')->nullOnDelete();
        });

        Schema::create('expense_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_id');
            $table->string('description');
            $table->string('item_type', 20)->default('Material'); // Labour | Material | Service | Other
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->unsignedSmallInteger('duration_months')->default(1);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->foreign('expense_id', 'fk_expense_items_expense')
                ->references('id')->on('expenses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign('fk_expenses_requisition');
            $table->dropColumn(['requisition_id', 'expense_type', 'duration_months']);
        });
    }
};
