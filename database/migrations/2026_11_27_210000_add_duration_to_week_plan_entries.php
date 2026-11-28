<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Week plan entries can state how many working days they are expected to
     * take, so a task can span more than a single day or week.
     */
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_days')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table): void {
            $table->dropColumn('duration_days');
        });
    }
};
