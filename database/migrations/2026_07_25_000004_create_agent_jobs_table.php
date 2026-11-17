<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrean job panel -> tkjpanel-agentd (PRD Â§4.1 & Â§5).
 *
 * Panel menulis baris ke sini (operation dari whitelist AgentOperation + payload
 * terstruktur). Agent Go melakukan polling atas baris status 'pending', mengeksekusi
 * operasi, lalu menulis kembali status/hasil/error.
 *
 * payload & result dienkripsi at-rest (cast 'encrypted:json' di model) karena bisa
 * memuat kredensial (mis. password DB) - lihat PRD Â§8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('operation');                 // AgentOperation (whitelist)
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('priority')->default(5);

            $table->text('payload')->nullable();         // encrypted:json
            $table->text('result')->nullable();          // encrypted:json
            $table->text('error')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_domain')->nullable(); // konteks situs (decoupled dari FK)

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index(); // penjadwalan
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_jobs');
    }
};
