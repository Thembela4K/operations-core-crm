<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two changes:
     *
     * 1. `super_admin` is merged into `business_admin` — there is one
     *    administrator role, so existing super admin accounts become business
     *    admins.
     * 2. Users gain a department `level` (Trainee / Intern / Officer / Head) so
     *    officers can add trainees and interns to their own department.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('level', 20)->nullable()->after('role');
        });

        DB::table('users')->where('role', 'super_admin')->update(['role' => 'business_admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('level');
        });
    }
};
