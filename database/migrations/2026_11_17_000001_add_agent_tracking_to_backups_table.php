<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backup kini dikerjakan agent sungguhan: catat database yang ikut di-backup, job agent
 * pembuat & pemulih arsip (untuk sinkron status), dan pesan error bila gagal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->foreignId('database_id')->nullable()->after('destination_id')->constrained('databases')->nullOnDelete();
            $table->foreignId('agent_job_id')->nullable()->after('database_id')->constrained('agent_jobs')->nullOnDelete();
            $table->foreignId('restore_job_id')->nullable()->after('agent_job_id')->constrained('agent_jobs')->nullOnDelete();
            $table->text('error')->nullable()->after('archive_path');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('database_id');
            $table->dropConstrainedForeignId('agent_job_id');
            $table->dropConstrainedForeignId('restore_job_id');
            $table->dropColumn('error');
        });
    }
};
