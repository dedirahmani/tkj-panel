<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat arsip backup per situs (file + dump DB). Restore satu-klik per situs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('backup_destinations')->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued | success | failed
            $table->boolean('include_database')->default(true);
            $table->unsignedBigInteger('size_kb')->nullable();
            $table->string('archive_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
