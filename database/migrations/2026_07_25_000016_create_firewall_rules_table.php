<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan firewall UFW (PRD 6.12). Termasuk whitelist/blacklist IP (source_ip + action).
 * Panel menyimpan definisi; agent menerapkan via ufw_rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->string('action');                 // allow | deny
            $table->unsignedInteger('port')->nullable(); // null = semua port
            $table->string('protocol')->default('tcp');  // tcp | udp | any
            $table->string('source_ip')->nullable();     // null = semua; utk whitelist/blacklist
            $table->string('direction')->default('in');
            $table->string('comment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
