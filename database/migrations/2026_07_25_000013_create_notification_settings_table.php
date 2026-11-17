<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial notifikasi per user (PRD 9): WhatsApp (Fonnte), Telegram, Email.
 * Token dienkripsi at-rest (cast 'encrypted') dan tidak pernah ditampilkan plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->boolean('wa_enabled')->default(false);
            $table->text('wa_token')->nullable();      // Fonnte token (encrypted)
            $table->string('wa_target')->nullable();    // nomor tujuan

            $table->boolean('tg_enabled')->default(false);
            $table->text('tg_token')->nullable();       // bot token (encrypted)
            $table->string('tg_chat_id')->nullable();

            $table->boolean('email_enabled')->default(false);
            $table->string('email_to')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
