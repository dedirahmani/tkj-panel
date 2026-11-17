<?php

namespace App\Enums;

/**
 * DAFTAR PUTIH (whitelist) operasi yang boleh dieksekusi oleh tkjpanel-agentd (root).
 *
 * Prinsip keamanan kunci (PRD Â§4.1 & Â§8): agent TIDAK PERNAH menjalankan perintah
 * shell arbitrer dari panel. Panel hanya boleh mengantre salah satu operasi terdefinisi
 * di bawah ini, lengkap dengan payload terstruktur yang divalidasi kedua sisi.
 *
 * Menambah kemampuan baru = menambah case di sini + handler di agent Go.
 * Tidak ada jalur lain bagi panel untuk menyuruh agent melakukan sesuatu.
 */
enum AgentOperation: string
{
    // --- Situs / Web server (Nginx / Apache / PHP-FPM) ---
    case CreateVhost = 'create_vhost';
    case DeleteVhost = 'delete_vhost';
    case ToggleVhost = 'toggle_vhost';
    case ReloadWebserver = 'reload_webserver';
    case SetPhpVersion = 'set_php_version';
    case WritePhpIni = 'write_php_ini';

    // --- SSL (acme.sh) ---
    case IssueSsl = 'issue_ssl';
    case RenewSsl = 'renew_ssl';
    case UploadSsl = 'upload_ssl';

    // --- Database (MariaDB) ---
    case CreateDatabase = 'create_database';
    case DropDatabase = 'drop_database';
    case CreateDbUser = 'create_db_user';
    case ResetDbPassword = 'reset_db_password';

    // --- User Linux / isolasi ---
    case CreateLinuxUser = 'create_linux_user';
    case DeleteLinuxUser = 'delete_linux_user';
    case SetDiskQuota = 'set_disk_quota';
    case SetSftpAccess = 'set_sftp_access';

    // --- File manager (via agent, chroot ke home user) ---
    case FileOp = 'file_op';

    // --- Cron ---
    case WriteCrontab = 'write_crontab';

    // --- Firewall ---
    case UfwRule = 'ufw_rule';
    case Fail2banAction = 'fail2ban_action';

    // --- Cloudflare / integrasi ---
    case CloudflareTunnel = 'cloudflare_tunnel';

    // --- Git deploy & Backup ---
    case GitDeploy = 'git_deploy';
    case RunBackup = 'run_backup';
    case RestoreBackup = 'restore_backup';

    // --- Sistem / monitoring ---
    case CollectMetrics = 'collect_metrics';
    case ServiceControl = 'service_control';

    /** Kategori untuk pengelompokan UI & audit. */
    public function category(): string
    {
        return match ($this) {
            self::CreateVhost, self::DeleteVhost, self::ToggleVhost,
            self::ReloadWebserver, self::SetPhpVersion, self::WritePhpIni => 'site',
            self::IssueSsl, self::RenewSsl, self::UploadSsl => 'ssl',
            self::CreateDatabase, self::DropDatabase,
            self::CreateDbUser, self::ResetDbPassword => 'database',
            self::CreateLinuxUser, self::DeleteLinuxUser,
            self::SetDiskQuota, self::SetSftpAccess => 'system-user',
            self::FileOp => 'file',
            self::WriteCrontab => 'cron',
            self::UfwRule, self::Fail2banAction => 'firewall',
            self::CloudflareTunnel => 'cloudflare',
            self::GitDeploy => 'git',
            self::RunBackup, self::RestoreBackup => 'backup',
            self::CollectMetrics, self::ServiceControl => 'system',
        };
    }

    /** Operasi destruktif menandai job yang butuh konfirmasi ekstra / audit ketat. */
    public function isDestructive(): bool
    {
        return in_array($this, [
            self::DeleteVhost,
            self::DropDatabase,
            self::DeleteLinuxUser,
        ], true);
    }
}
