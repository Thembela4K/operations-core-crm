<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_code')->unique();
            $table->string('client');
            $table->string('opportunity');
            $table->string('owner');
            $table->string('owner_email')->nullable();
            $table->string('status');
            $table->string('priority');
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('risk');
            $table->unsignedTinyInteger('win_probability_percent')->default(0);
            $table->decimal('quoted_amount', 14, 2)->default(0);
            $table->decimal('expected_cost', 14, 2)->default(0);
            $table->date('issue_date');
            $table->date('valid_until');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
