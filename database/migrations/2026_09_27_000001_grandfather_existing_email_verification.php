<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grandfather pre-verification-gate accounts: A11 blocks login for
     * unverified users, but register never stamped email_verified_at,
     * so every existing user reads as unverified. Stamp them once;
     * the gate applies forward-only to new registers.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // No rollback: verification timestamps must never be unset in bulk.
    }
};
