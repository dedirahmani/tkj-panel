<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cronjob per user (PRD 6.9), dijalankan di bawah user Linux masing-masing.
 * Panel menyimpan definisi; agent menulis crontab user via write_crontab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('expression');   // ekspresi cron 5-field
            $table->text('command');
            $table->string('comment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
