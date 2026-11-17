<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Paket default (kuota mengikuti PRD 7.2).
        $package = Package::firstOrCreate(
            ['name' => 'Starter'],
            [
                'description' => 'Paket default siswa TKJ',
                'disk_quota_mb' => 2048,
                'max_domains' => 3,
                'max_subdomains' => 10,
                'max_databases' => 3,
                'max_cronjobs' => 5,
                'max_sftp_accounts' => 3,
                'bandwidth_quota_mb' => 20480,
                'is_default' => true,
            ]
        );

        // Super Admin awal. GANTI PASSWORD setelah login pertama.
        User::firstOrCreate(
            ['email' => env('TKJPANEL_ADMIN_EMAIL', 'admin@tkjpanel.local')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('TKJPANEL_ADMIN_PASSWORD', 'password')),
                'role' => UserRole::SuperAdmin,
                'linux_username' => 'tkjadmin',
                'locale' => 'id',
                'is_active' => true,
            ]
        );

        // Contoh Reseller + User untuk demonstrasi RBAC (opsional, hanya di dev).
        if (app()->environment('local')) {
            $reseller = User::firstOrCreate(
                ['email' => 'guru@tkjpanel.local'],
                [
                    'name' => 'Guru Pembina',
                    'password' => Hash::make('password'),
                    'role' => UserRole::Reseller,
                    'linux_username' => 'gurupembina',
                    'locale' => 'id',
                    'is_active' => true,
                ]
            );

            User::firstOrCreate(
                ['email' => 'siswa@tkjpanel.local'],
                [
                    'name' => 'Siswa Demo',
                    'password' => Hash::make('password'),
                    'role' => UserRole::User,
                    'parent_id' => $reseller->id,
                    'package_id' => $package->id,
                    'linux_username' => 'siswademo',
                    'locale' => 'id',
                    'is_active' => true,
                ]
            );
        }
    }
}
