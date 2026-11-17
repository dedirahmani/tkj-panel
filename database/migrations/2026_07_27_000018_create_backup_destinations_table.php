<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tujuan backup (PRD 6.8): lokal, S3-compatible, Google Drive. Kredensial (key/secret)
 * dienkripsi at-rest. Retensi mis. 7 harian / 4 mingguan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');                 // local | s3 | gdrive
            $table->text('config')->nullable();      // encrypted json (endpoint/bucket/key/secret/folder)
            $table->unsignedInteger('retention_daily')->default(7);
            $table->unsignedInteger('retention_weekly')->default(4);
            $table->string('schedule')->nullable();  // ekspresi cron (opsional)
            $table->boolean('encrypt_archive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_destinations');
    }
};
