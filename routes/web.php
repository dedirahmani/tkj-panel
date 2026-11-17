<?php

use App\Http\Controllers\AdminerController;
use App\Http\Controllers\AgentJobController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CloudflareController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\GitDeployController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SftpAccountController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Pengalih bahasa boleh diakses tamu (agar bisa ganti bahasa di halaman login).
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'));

// Webhook Git deploy (publik; diverifikasi via secret di URL, di luar CSRF/auth).
Route::post('/webhooks/git/{site}/{secret}', [GitDeployController::class, 'webhook'])->name('git.webhook');

// Area panel: wajib login + akun aktif. (Rute auth & 2FA disediakan Fortify.)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Manajemen situs (semua peran; dilingkupi per kepemilikan di controller).
    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::get('/sites/create', [SiteController::class, 'create'])->name('sites.create');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::get('/sites/{site}/edit', [SiteController::class, 'edit'])->name('sites.edit');
    Route::put('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::patch('/sites/{site}/status', [SiteController::class, 'setStatus'])->name('sites.status');
    Route::patch('/sites/{site}/php-ini', [SiteController::class, 'updatePhpIni'])->name('sites.phpini');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');

    // Database (MariaDB) + Adminer.
    Route::get('/databases', [DatabaseController::class, 'index'])->name('databases.index');
    Route::get('/databases/create', [DatabaseController::class, 'create'])->name('databases.create');
    Route::post('/databases', [DatabaseController::class, 'store'])->name('databases.store');
    Route::patch('/databases/{database}/reset-password', [DatabaseController::class, 'resetPassword'])->name('databases.reset');
    Route::patch('/databases/{database}/access', [DatabaseController::class, 'updateAccess'])->name('databases.access');
    Route::delete('/databases/{database}', [DatabaseController::class, 'destroy'])->name('databases.destroy');

    // Adminer disajikan di balik login panel (GET+POST; Adminer kelola output sendiri).
    Route::match(['get', 'post'], '/db-admin', [AdminerController::class, 'launch'])->name('adminer');

    // Akun SFTP (chroot ke home; dilingkupi per kepemilikan di controller).
    Route::get('/sftp', [SftpAccountController::class, 'index'])->name('sftp.index');
    Route::get('/sftp/create', [SftpAccountController::class, 'create'])->name('sftp.create');
    Route::post('/sftp', [SftpAccountController::class, 'store'])->name('sftp.store');
    Route::patch('/sftp/{sftp}/reset-password', [SftpAccountController::class, 'resetPassword'])->name('sftp.reset');
    Route::delete('/sftp/{sftp}', [SftpAccountController::class, 'destroy'])->name('sftp.destroy');

    // Cron per user.
    Route::get('/cron', [CronController::class, 'index'])->name('cron.index');
    Route::get('/cron/create', [CronController::class, 'create'])->name('cron.create');
    Route::post('/cron', [CronController::class, 'store'])->name('cron.store');
    Route::patch('/cron/{cron}/toggle', [CronController::class, 'toggle'])->name('cron.toggle');
    Route::delete('/cron/{cron}', [CronController::class, 'destroy'])->name('cron.destroy');

    // File manager (terjail ke home user; chmod/chown via agent).
    Route::get('/files', [FileManagerController::class, 'index'])->name('files.index');
    Route::get('/files/edit', [FileManagerController::class, 'edit'])->name('files.edit');
    Route::get('/files/download', [FileManagerController::class, 'download'])->name('files.download');
    Route::post('/files/save', [FileManagerController::class, 'save'])->name('files.save');
    Route::post('/files/mkdir', [FileManagerController::class, 'mkdir'])->name('files.mkdir');
    Route::post('/files/rename', [FileManagerController::class, 'rename'])->name('files.rename');
    Route::post('/files/upload', [FileManagerController::class, 'upload'])->name('files.upload');
    Route::post('/files/chmod', [FileManagerController::class, 'chmod'])->name('files.chmod');
    Route::delete('/files', [FileManagerController::class, 'delete'])->name('files.delete');

    // Terminal web (UI xterm.js; PTY via agent).
    Route::get('/terminal', [TerminalController::class, 'index'])->name('terminal.index');
    Route::post('/terminal/token', [TerminalController::class, 'token'])->middleware('throttle:20,1')->name('terminal.token');

    // Pengaturan notifikasi (per user): Fonnte/Telegram/Email.
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test', [SettingsController::class, 'test'])->name('settings.test');

    // REST API token management (per user).
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    // Git deploy per situs.
    Route::get('/sites/{site}/git', [GitDeployController::class, 'edit'])->name('git.edit');
    Route::put('/sites/{site}/git', [GitDeployController::class, 'update'])->name('git.update');
    Route::post('/sites/{site}/git/deploy', [GitDeployController::class, 'deploy'])->name('git.deploy');

    // Backup (file + DB) ke lokal/S3/GDrive.
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup/destinations', [BackupController::class, 'storeDestination'])->name('backup.destinations.store');
    Route::delete('/backup/destinations/{destination}', [BackupController::class, 'destroyDestination'])->name('backup.destinations.destroy');
    Route::post('/backup/run', [BackupController::class, 'run'])->name('backup.run');
    Route::post('/backup/{backup}/restore', [BackupController::class, 'restore'])->name('backup.restore');

    // Monitoring & Firewall & Cloudflare: Super Admin (server-wide).
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::post('/monitoring/collect', [MonitoringController::class, 'collect'])->name('monitoring.collect');
        Route::put('/monitoring/alerts', [MonitoringController::class, 'updateAlerts'])->name('monitoring.alerts');
        Route::post('/monitoring/services', [MonitoringController::class, 'service'])->name('monitoring.service');

        Route::get('/firewall', [FirewallController::class, 'index'])->name('firewall.index');
        Route::post('/firewall', [FirewallController::class, 'store'])->name('firewall.store');
        Route::patch('/firewall/{rule}/toggle', [FirewallController::class, 'toggle'])->name('firewall.toggle');
        Route::delete('/firewall/{rule}', [FirewallController::class, 'destroy'])->name('firewall.destroy');
        Route::post('/firewall/fail2ban', [FirewallController::class, 'fail2ban'])->name('firewall.fail2ban');

        // Cloudflare Tunnel + DNS.
        Route::get('/cloudflare', [CloudflareController::class, 'index'])->name('cloudflare.index');
        Route::put('/cloudflare/token', [CloudflareController::class, 'saveToken'])->name('cloudflare.token');
        Route::post('/cloudflare/dns', [CloudflareController::class, 'dnsStore'])->name('cloudflare.dns.store');
        Route::delete('/cloudflare/dns', [CloudflareController::class, 'dnsDestroy'])->name('cloudflare.dns.destroy');
        Route::post('/cloudflare/tunnel', [CloudflareController::class, 'tunnelStore'])->name('cloudflare.tunnel.store');
        Route::delete('/cloudflare/tunnel/{tunnel}', [CloudflareController::class, 'tunnelDestroy'])->name('cloudflare.tunnel.destroy');
    });

    // SSL (acme.sh / Let's Encrypt + upload kustom).
    Route::get('/ssl', [CertificateController::class, 'index'])->name('ssl.index');
    Route::get('/ssl/create', [CertificateController::class, 'create'])->name('ssl.create');
    Route::post('/ssl', [CertificateController::class, 'store'])->name('ssl.store');
    Route::get('/ssl/upload', [CertificateController::class, 'upload'])->name('ssl.upload');
    Route::post('/ssl/upload', [CertificateController::class, 'storeUpload'])->name('ssl.upload.store');
    Route::patch('/ssl/{certificate}/renew', [CertificateController::class, 'renew'])->name('ssl.renew');
    Route::patch('/ssl/{certificate}/auto-renew', [CertificateController::class, 'toggleAutoRenew'])->name('ssl.auto');
    Route::delete('/ssl/{certificate}', [CertificateController::class, 'destroy'])->name('ssl.destroy');

    Route::get('/security', [SecurityController::class, 'show'])->name('security.show');

    Route::get('/jobs', [AgentJobController::class, 'index'])->name('jobs.index');

    // Manajemen pengguna: Super Admin & Reseller.
    Route::middleware('role:super_admin,reseller')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/provision', [UserController::class, 'provision'])->name('users.provision');
        Route::patch('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Paket & audit: Super Admin saja.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('/packages/create', [PackageController::class, 'create'])->name('packages.create');
        Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');

        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});
