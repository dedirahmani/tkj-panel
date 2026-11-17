<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metrik kini diisi dari hasil job collect_metrics agent. agent_job_id mencegah satu hasil
 * job dimasukkan dua kali (sinkron berjalan tiap menit dan saat halaman dibuka).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_job_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->dropUnique(['agent_job_id']);
            $table->dropColumn('agent_job_id');
        });
    }
};
