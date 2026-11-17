<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat SSL per situs (PRD 6.4). Diterbitkan via acme.sh (Let's Encrypt)
 * HTTP-01 / DNS-01 (wildcard via Cloudflare), atau diunggah manual.
 *
 * Private key TIDAK disimpan di panel (diteruskan ke agent via job terenkripsi).
 * Panel hanya menyimpan metadata + PEM sertifikat publik (opsional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();

            $table->string('primary_domain');
            $table->json('domains');                 // daftar SAN
            $table->string('type')->default('single'); // single | wildcard | custom
            $table->string('method')->default('http-01'); // http-01 | dns-01 | upload
            $table->string('status')->default('pending');

            $table->boolean('auto_renew')->default(true);
            $table->string('issuer')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
