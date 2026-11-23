<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The role previously called `business_analyst` is actually a business
     * administrator: they administer staff accounts rather than only reporting.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'business_analyst')
            ->update(['role' => 'business_admin']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'business_admin')
            ->update(['role' => 'business_analyst']);
    }
};
