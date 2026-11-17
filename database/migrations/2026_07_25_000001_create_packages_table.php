<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket kuota (PRD Â§7.2). Didefinisikan Super Admin; Reseller mengalokasikan
 * user di bawah kuota paket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();

            // Kuota (nilai default mengikuti PRD Â§7.2)
            $table->unsignedInteger('disk_quota_mb')->default(2048);      // 2 GB
            $table->unsignedInteger('max_domains')->default(3);
            $table->unsignedInteger('max_subdomains')->default(10);
            $table->unsignedInteger('max_databases')->default(3);
            $table->unsignedInteger('max_cronjobs')->default(5);
            $table->unsignedInteger('max_sftp_accounts')->default(3);
            $table->unsignedInteger('bandwidth_quota_mb')->default(20480); // 20 GB (monitoring)

            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
