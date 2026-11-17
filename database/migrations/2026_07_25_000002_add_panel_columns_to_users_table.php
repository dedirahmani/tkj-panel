<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom khusus panel pada users: peran (RBAC), hubungan reseller->user (parent_id),
 * paket kuota, identitas user Linux terisolasi, preferensi bahasa, status aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email');
            $table->foreignId('parent_id')->nullable()->after('role')
                ->constrained('users')->nullOnDelete(); // reseller pemilik akun
            $table->foreignId('package_id')->nullable()->after('parent_id')
                ->constrained('packages')->nullOnDelete();

            $table->string('linux_username')->nullable()->unique()->after('package_id');
            $table->string('phone')->nullable()->after('linux_username'); // untuk notifikasi WA
            $table->string('locale', 5)->default('id')->after('phone');    // ID/EN

            $table->boolean('is_active')->default(true)->after('locale');
            $table->timestamp('suspended_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn(['role', 'linux_username', 'phone', 'locale', 'is_active', 'suspended_at']);
        });
    }
};
