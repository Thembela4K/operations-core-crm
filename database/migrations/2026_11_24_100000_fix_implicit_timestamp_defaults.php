<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MariaDB (with explicit_defaults_for_timestamp = 0) gives the first
     * TIMESTAMP column of a table an implicit `DEFAULT CURRENT_TIMESTAMP
     * ON UPDATE CURRENT_TIMESTAMP`.
     *
     * That silently destroyed expiry dates: the invitation service updates the
     * row to record `sent_at`, and the update reset `expires_at` to "now", so
     * invitations expired the moment they were sent. Password-reset OTPs had
     * the same latent defect.
     *
     * Making the columns nullable removes the implicit default and auto-update.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE user_invitations MODIFY expires_at TIMESTAMP NULL DEFAULT NULL');
        DB::statement('ALTER TABLE password_reset_otps MODIFY expires_at TIMESTAMP NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE user_invitations MODIFY expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        DB::statement('ALTER TABLE password_reset_otps MODIFY expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
};
