<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cloudflare Tunnel: satu tunnel per server (disimpan di cloudflare_settings), setiap baris
 * cloudflare_tunnels = satu hostname publik yang diarahkan ke situs ATAU ke panel sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloudflare_settings', function (Blueprint $table) {
            $table->string('tunnel_id')->nullable()->after('account_id');
            $table->text('tunnel_token')->nullable()->after('tunnel_id'); // encrypted
        });

        Schema::table('cloudflare_tunnels', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->change();
            $table->string('target')->default('site')->after('site_id'); // site | panel
            $table->string('service')->nullable()->after('hostname');   // origin lokal, mis. http://127.0.0.1:80
            $table->string('dns_record_id')->nullable()->after('tunnel_id');
            $table->text('error')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cloudflare_tunnels', function (Blueprint $table) {
            $table->dropColumn(['target', 'service', 'dns_record_id', 'error']);
        });
        Schema::table('cloudflare_settings', function (Blueprint $table) {
            $table->dropColumn(['tunnel_id', 'tunnel_token']);
        });
    }
};
