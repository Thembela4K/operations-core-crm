<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requisition_number')->unique();
            $table->string('addressed_to')->default('Directors');
            $table->string('title');
            $table->string('category')->default('Operational');
            $table->string('priority')->default('Medium');
            $table->string('status')->default('Draft');
            $table->date('needed_by')->nullable();
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->decimal('bank_total', 15, 2)->default(0);
            $table->decimal('cash_total', 15, 2)->default(0);
            $table->decimal('other_total', 15, 2)->default(0);
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('release_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('funds_released_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['status', 'needed_by']);
            $table->index(['requested_by', 'status']);
        });

        Schema::create('requisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->text('description');
            $table->string('payment_type')->default('Cash');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->text('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['requisition_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
    }
};
