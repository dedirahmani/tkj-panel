<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan alert ambang batas (PRD 6.11). Bila metrik melampaui threshold, panel
 * mengirim notifikasi (WhatsApp/Telegram/Email) ke Super Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('metric');          // cpu | ram | disk | load
            $table->unsignedInteger('threshold'); // persen (cpu/ram/disk) atau load*100
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
