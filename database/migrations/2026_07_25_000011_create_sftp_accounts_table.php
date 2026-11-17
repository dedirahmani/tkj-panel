<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun SFTP via user sistem, chroot ke direktori dalam home (PRD 6.7 & 7.1).
 * Tanpa daemon FTP tambahan (OpenSSH). Password tidak disimpan (via job terenkripsi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // pemilik panel
            $table->string('username')->unique();   // user sistem SFTP (prefix pemilik)
            $table->string('home_path');            // chroot target (dalam home pemilik)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sftp_accounts');
    }
};
