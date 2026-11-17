<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrasi Cloudflare (PRD 9): token API (enkripsi at-rest) + Tunnel per situs.
 * DNS record dikelola langsung via API CF (tidak disimpan lokal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_settings', function (Blueprint $table) {
            $table->id();
            $table->text('api_token')->nullable();   // encrypted
            $table->string('account_id')->nullable();
            $table->timestamps();
        });

        Schema::create('cloudflare_tunnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('hostname');
            $table->string('tunnel_name');
            $table->string('tunnel_id')->nullable();
            $table->string('status')->default('provisioning'); // provisioning | active | error
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_tunnels');
        Schema::dropIfExists('cloudflare_settings');
    }
};
