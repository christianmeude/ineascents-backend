<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add nullable category (women|men) to scents.
     */
    public function up(): void
    {
        Schema::table('scents', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('scents', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
