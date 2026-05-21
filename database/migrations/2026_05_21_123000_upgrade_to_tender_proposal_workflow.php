<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && ! Schema::hasTable('tender_proposals')) {
            Schema::rename('projects', 'tender_proposals');
        }

        if (Schema::hasTable('tender_proposals')) {
            $this->renameColumnIfNeeded('tender_proposals', 'project_code', 'tender_reference');
            $this->renameColumnIfNeeded('tender_proposals', 'name', 'title');
            $this->renameColumnIfNeeded('tender_proposals', 'start_date', 'received_date');
            $this->renameColumnIfNeeded('tender_proposals', 'deadline', 'closing_date');
            $this->renameColumnIfNeeded('tender_proposals', 'notes', 'brief');
        }

        DB::table('assignments')
            ->where('assignable_type', 'App\\Models\\Project')
            ->update(['assignable_type' => 'App\\Models\\TenderProposal']);

        DB::table('documents')
            ->where('documentable_type', 'App\\Models\\Project')
            ->update(['documentable_type' => 'App\\Models\\TenderProposal']);

        DB::table('email_logs')
            ->where('emailable_type', 'App\\Models\\Project')
            ->update(['emailable_type' => 'App\\Models\\TenderProposal']);

        DB::table('reminder_logs')
            ->where('remindable_type', 'App\\Models\\Project')
            ->update(['remindable_type' => 'App\\Models\\TenderProposal']);

        if (! Schema::hasColumn('assignments', 'due_date')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->date('due_date')->nullable()->after('assigned_at');
            });
        }

        if (! Schema::hasColumn('assignments', 'instructions')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->text('instructions')->nullable()->after('due_date');
            });
        }

        if (! Schema::hasColumn('assignments', 'workflow_status')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->string('workflow_status')->default('Assigned')->after('status');
            });
        }

        if (! Schema::hasColumn('assignments', 'read_at')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->timestamp('read_at')->nullable()->after('instructions');
            });
        }

        if (! Schema::hasColumn('assignments', 'viewed_at')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->timestamp('viewed_at')->nullable()->after('read_at');
            });
        }

        if (! Schema::hasColumn('assignments', 'completed_at')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->timestamp('completed_at')->nullable()->after('viewed_at');
            });
        }

        if (! Schema::hasColumn('documents', 'category')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->string('category')->default('other')->after('documentable_id');
            });
        }

        if (! Schema::hasTable('important_dates')) {
            Schema::create('important_dates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tender_proposal_id')->constrained()->cascadeOnDelete();
                $table->string('label');
                $table->date('due_date');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tender_proposal_id', 'due_date']);
            });
        }

        if (! Schema::hasTable('submissions')) {
            Schema::create('submissions', function (Blueprint $table): void {
                $table->id();
                $table->morphs('submittable');
                $table->foreignId('assignment_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('department_id')->constrained()->cascadeOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('Draft');
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['department_id', 'status']);
                $table->index(['submitted_at']);
            });
        }

        if (! Schema::hasColumn('users', 'receives_submissions')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('receives_submissions')->default(false)->after('is_active');
            });
        }

        if (! Schema::hasColumn('users', 'can_access_sppra')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('can_access_sppra')->default(false)->after('receives_submissions');
            });
        }

        $directorDepartmentIds = DB::table('departments')->where('slug', 'directors')->pluck('id');
        if ($directorDepartmentIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('department_id', $directorDepartmentIds)
                ->where('role', '!=', 'super_admin')
                ->update([
                    'role' => 'director',
                    'receives_submissions' => true,
                    'can_access_sppra' => true,
                ]);
        }

        $receptionDepartmentIds = DB::table('departments')->where('slug', 'admin-reception')->pluck('id');
        if ($receptionDepartmentIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('department_id', $receptionDepartmentIds)
                ->where('role', '!=', 'super_admin')
                ->update([
                    'role' => 'reception',
                    'receives_submissions' => true,
                    'can_access_sppra' => true,
                ]);
        }

        DB::table('users')
            ->where('role', 'super_admin')
            ->update([
                'receives_submissions' => true,
                'can_access_sppra' => true,
            ]);

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'sppra_url'],
            ['value' => '', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('assignments')
            ->where('assignable_type', 'App\\Models\\TenderProposal')
            ->update(['assignable_type' => 'App\\Models\\Project']);

        DB::table('documents')
            ->where('documentable_type', 'App\\Models\\TenderProposal')
            ->update(['documentable_type' => 'App\\Models\\Project']);

        DB::table('email_logs')
            ->where('emailable_type', 'App\\Models\\TenderProposal')
            ->update(['emailable_type' => 'App\\Models\\Project']);

        DB::table('reminder_logs')
            ->where('remindable_type', 'App\\Models\\TenderProposal')
            ->update(['remindable_type' => 'App\\Models\\Project']);

        Schema::dropIfExists('submissions');
        Schema::dropIfExists('important_dates');

        foreach (['due_date', 'instructions', 'workflow_status', 'read_at', 'viewed_at', 'completed_at'] as $column) {
            if (Schema::hasColumn('assignments', $column)) {
                Schema::table('assignments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        if (Schema::hasColumn('documents', 'category')) {
            Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('category'));
        }

        foreach (['receives_submissions', 'can_access_sppra'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        if (Schema::hasTable('tender_proposals')) {
            $this->renameColumnIfNeeded('tender_proposals', 'tender_reference', 'project_code');
            $this->renameColumnIfNeeded('tender_proposals', 'title', 'name');
            $this->renameColumnIfNeeded('tender_proposals', 'received_date', 'start_date');
            $this->renameColumnIfNeeded('tender_proposals', 'closing_date', 'deadline');
            $this->renameColumnIfNeeded('tender_proposals', 'brief', 'notes');
        }

        if (Schema::hasTable('tender_proposals') && ! Schema::hasTable('projects')) {
            Schema::rename('tender_proposals', 'projects');
        }
    }

    private function renameColumnIfNeeded(string $table, string $from, string $to): void
    {
        if (! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }
};
