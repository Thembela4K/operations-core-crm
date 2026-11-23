<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff identity fields that were previously deferred.
     *
     * `job_title` is a person's position (e.g. "MIS Trainee") and is deliberately
     * separate from `role`, which stays the fixed permission model.
     * `employee_no` feeds the printed leave application form.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_no', 40)->nullable()->after('username');
            $table->string('job_title', 120)->nullable()->after('employee_no');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_no', 'job_title']);
        });
    }
};
