<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A18: provable privacy consent — policy version shown to the user plus
 * server-set timestamp. Nullable so pre-policy rows stay untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('consent_privacy_version')->nullable()->after('message');
            $table->timestamp('consented_at')->nullable()->after('consent_privacy_version');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('consent_privacy_version')->nullable()->after('inquiry_id');
            $table->timestamp('consented_at')->nullable()->after('consent_privacy_version');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn(['consent_privacy_version', 'consented_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['consent_privacy_version', 'consented_at']);
        });
    }
};
