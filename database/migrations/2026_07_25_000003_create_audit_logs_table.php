<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log WAJIB (PRD Â§8): siapa, aksi, kapan, IP. Bersifat append-only
 * (hanya kolom created_at, tidak ada updated_at; tidak pernah di-update/dihapus
 * oleh alur aplikasi normal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable();  // snapshot nama/email pelaku
            $table->string('action');                   // mis. user.created, auth.login
            $table->string('target_type')->nullable();  // model/entitas terkait
            $table->string('target_id')->nullable();
            $table->string('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('properties')->nullable();      // konteks tambahan
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
