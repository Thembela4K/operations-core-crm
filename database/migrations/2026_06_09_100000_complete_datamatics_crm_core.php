<?php

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'staff_member_id')) {
                $table->foreignId('staff_member_id')->nullable()->after('department_id')->constrained('staff_members')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('name')->unique();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        }

        Schema::table('staff_members', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_members', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('documents', 'title')) {
                $table->string('title')->nullable()->after('category');
            }

            if (! Schema::hasColumn('documents', 'tags')) {
                $table->string('tags')->nullable()->after('title');
            }

            if (! Schema::hasColumn('documents', 'is_generated')) {
                $table->boolean('is_generated')->default(false)->after('tags');
            }
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vat_number')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['name', 'is_active']);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('expenses', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('department_id')->constrained('suppliers')->nullOnDelete();
            }
        });

        Schema::table('requisitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('requisitions', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('department_id')->constrained('suppliers')->nullOnDelete();
            }
        });

        Schema::create('purchase_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purchase_number')->unique();
            $table->string('title');
            $table->string('status')->default('Planned');
            $table->date('purchase_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'purchase_date']);
            $table->index(['department_id', 'status']);
        });

        Schema::create('crm_tasks', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('taskable');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('To Do');
            $table->string('priority')->default('Medium');
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crm_task_id')->constrained('crm_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('in_time')->nullable();
            $table->timestamp('out_time')->nullable();
            $table->unsignedInteger('total_minutes')->default(0);
            $table->string('status')->default('Clocked In');
            $table->text('note')->nullable();
            $table->text('correction_note')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['department_id', 'work_date']);
            $table->index(['status', 'work_date']);
        });

        Schema::create('crm_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('general');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('auditable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('client_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('Call');
            $table->string('subject');
            $table->text('notes')->nullable();
            $table->date('activity_date')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->string('status')->default('Open');
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['responsible_user_id', 'next_follow_up_date']);
        });

        $this->syncExistingStaffAccounts();
    }

    public function down(): void
    {
        Schema::dropIfExists('client_activities');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('crm_notifications');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('purchase_records');

        Schema::table('requisitions', function (Blueprint $table): void {
            if (Schema::hasColumn('requisitions', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::table('expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('expenses', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::dropIfExists('suppliers');

        Schema::table('documents', function (Blueprint $table): void {
            foreach (['title', 'tags', 'is_generated'] as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('staff_members', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_members', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'staff_member_id')) {
                $table->dropConstrainedForeignId('staff_member_id');
            }

            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            }
        });
    }

    private function syncExistingStaffAccounts(): void
    {
        if (! Schema::hasTable('staff_members')) {
            return;
        }

        StaffMember::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->each(function (StaffMember $staff): void {
                $username = $this->uniqueUsername($staff->name);
                $existing = null;

                if ($staff->email) {
                    $existing = User::query()->where('email', $staff->email)->first();
                }

                if (! $existing && $staff->user_id) {
                    $existing = User::query()->find($staff->user_id);
                }

                if ($existing) {
                    $existing->forceFill([
                        'staff_member_id' => $staff->id,
                        'username' => $existing->username ?: $username,
                        'department_id' => $existing->department_id ?: $staff->department_id,
                    ])->save();
                    $staff->forceFill(['user_id' => $existing->id])->save();

                    return;
                }

                $user = User::query()->create([
                    'staff_member_id' => $staff->id,
                    'department_id' => $staff->department_id,
                    'name' => $staff->name,
                    'username' => $username,
                    'email' => $staff->email,
                    'password' => Hash::make(Str::password(18)),
                    'role' => User::ROLE_DEPARTMENT_USER,
                    'is_active' => true,
                ]);

                $staff->forceFill(['user_id' => $user->id])->save();
            });
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::slug($name, '.');
        $base = preg_replace('/[^a-z0-9.]/', '', strtolower($base)) ?: 'staff';
        $username = $base;
        $counter = 2;

        while (User::query()->where('username', $username)->exists()) {
            $username = "{$base}{$counter}";
            $counter++;
        }

        return $username;
    }
};
