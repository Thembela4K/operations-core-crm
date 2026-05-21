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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('name');
            $table->string('owner');
            $table->string('owner_email')->nullable();
            $table->string('status');
            $table->string('priority');
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('risk');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->decimal('budget', 14, 2)->default(0);
            $table->date('start_date');
            $table->date('deadline');
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
        Schema::dropIfExists('projects');
    }
};
