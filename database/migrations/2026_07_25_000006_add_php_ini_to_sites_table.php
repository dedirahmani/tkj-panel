<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override php.ini per situs/pool (PRD 6.2: editor php.ini per pool via UI).
 * Disimpan sebagai JSON berisi hanya direktif dari whitelist PhpIniDirectives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('php_ini')->nullable()->after('web_backend');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('php_ini');
        });
    }
};
