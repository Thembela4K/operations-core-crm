<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table): void {
            $table->dropUnique('unique_reminder_once');
        });

        Schema::table('reminder_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('reminder_logs', 'assignment_id')) {
                $table->foreignId('assignment_id')
                    ->nullable()
                    ->after('remindable_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            $table->unique(
                ['remindable_type', 'remindable_id', 'assignment_id', 'due_on', 'days_before'],
                'unique_reminder_once_per_assignment',
            );
        });
    }

    public function down(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table): void {
            $table->dropUnique('unique_reminder_once_per_assignment');
        });

        Schema::table('reminder_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('reminder_logs', 'assignment_id')) {
                $table->dropConstrainedForeignId('assignment_id');
            }

            $table->unique(['remindable_type', 'remindable_id', 'due_on', 'days_before'], 'unique_reminder_once');
        });
    }
};
