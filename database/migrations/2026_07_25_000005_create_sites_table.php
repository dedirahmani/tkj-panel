<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Situs yang dikelola panel (PRD 6.1). Root otomatis /home/<user>/<domain>/public.
 * Aksi nyata (tulis vhost Nginx/Apache) didelegasikan ke agent via agent_jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // pemilik
            $table->string('domain')->unique();
            $table->string('kind')->default('domain');      // domain | subdomain
            $table->json('aliases')->nullable();             // domain alias tambahan

            $table->string('php_version')->default('8.3');
            $table->string('web_backend')->default('nginx_fpm');
            $table->string('document_root');
            $table->string('status')->default('active');     // active|inactive|maintenance

            $table->boolean('force_https')->default(true);
            $table->string('redirect_type')->default('none'); // none|301|302
            $table->string('redirect_target')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
