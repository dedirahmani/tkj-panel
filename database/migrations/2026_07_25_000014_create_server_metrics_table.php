<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat metrik server (PRD 6.11). Diisi agent (collect_metrics). Panel menampilkan
 * realtime (baris terbaru) + agregat rolling (mis. 7 hari).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('captured_at')->index();

            $table->float('cpu_percent')->default(0);
            $table->float('ram_percent')->default(0);
            $table->unsignedBigInteger('ram_used_mb')->default(0);
            $table->unsignedBigInteger('ram_total_mb')->default(0);
            $table->float('disk_percent')->default(0);
            $table->unsignedBigInteger('disk_used_mb')->default(0);
            $table->unsignedBigInteger('disk_total_mb')->default(0);
            $table->float('load_1')->default(0);
            $table->float('load_5')->default(0);
            $table->float('load_15')->default(0);
            $table->unsignedBigInteger('net_rx_kb')->default(0);
            $table->unsignedBigInteger('net_tx_kb')->default(0);

            $table->json('processes')->nullable(); // proses teratas
            $table->json('ports')->nullable();      // port aktif
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
