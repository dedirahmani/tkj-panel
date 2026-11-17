<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi Git deploy per situs (PRD 9): repo GitHub, branch, perintah build,
 * dan webhook auto-pull. Eksekusi pull/build didelegasikan ke agent (git_deploy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_deploys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained('sites')->cascadeOnDelete();
            $table->string('repo_url');
            $table->string('branch')->default('main');
            $table->text('build_command')->nullable();
            $table->string('webhook_secret', 64);
            $table->string('last_status')->nullable();     // queued | success | failed
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_deploys');
    }
};
