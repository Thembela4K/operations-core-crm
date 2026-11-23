<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('leave_type');
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('working_days', 5, 1)->default(0);
            $table->date('back_at_work_on')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('certificate_attached')->default(false);
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('Pending');
            $table->unsignedSmallInteger('leave_year');
            $table->date('applied_on');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'leave_year']);
            $table->index(['user_id', 'from_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
