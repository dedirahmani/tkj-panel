<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemakaian disk terisolasi per user (PRD 7.1/7.2). disk_used_mb dilaporkan agent
 * (Linux quota / repquota). Kuota batasnya diturunkan dari paket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('disk_used_mb')->default(0)->after('is_active');
            $table->timestamp('provisioned_at')->nullable()->after('disk_used_mb');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['disk_used_mb', 'provisioned_at']);
        });
    }
};
