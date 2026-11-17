<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database MariaDB milik user (PRD 6.3). Password TIDAK disimpan di panel
 * (hanya ditampilkan sekali saat dibuat/reset, lalu diteruskan ke agent via job
 * terenkripsi) - lihat PRD 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name')->unique();       // nama db lengkap (prefix user_)
            $table->string('db_user')->unique();     // user db lengkap (prefix user_)
            $table->string('host')->default('localhost');
            $table->boolean('remote_access')->default(false);
            $table->json('allowed_ips')->nullable(); // whitelist IP untuk remote
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
