<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alokasi kuota Reseller (PRD 3 & 7.2). Super Admin menetapkan total sumber daya
 * yang boleh dibagikan Reseller ke user di bawahnya. Membuat user menarik dari sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('max_users')->default(5);
            $table->unsignedInteger('disk_quota_mb')->default(10240);   // total 10 GB
            $table->unsignedInteger('max_domains')->default(10);
            $table->unsignedInteger('max_databases')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_allocations');
    }
};
