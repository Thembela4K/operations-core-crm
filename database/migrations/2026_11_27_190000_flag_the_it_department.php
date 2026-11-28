<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The IT Officer is a department user inside the IT Department. Triage is
     * therefore tied to the department record rather than to a role string or a
     * name match, so renaming the department cannot silently remove access.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->boolean('is_it_department')->default(false)->after('is_active');
        });

        $flagged = DB::table('departments')->where('slug', 'it-department')->update(['is_it_department' => true]);

        if ($flagged === 0) {
            // Fall back to the first department that looks like IT/MIS.
            $candidate = DB::table('departments')
                ->where(function ($query): void {
                    $query->where('name', 'like', '%IT%')
                        ->orWhere('name', 'like', '%MIS%')
                        ->orWhere('name', 'like', '%ICT%');
                })
                ->orderBy('id')
                ->value('id');

            if ($candidate !== null) {
                DB::table('departments')->where('id', $candidate)->update(['is_it_department' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('is_it_department');
        });
    }
};
