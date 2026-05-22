<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
