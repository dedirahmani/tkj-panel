<?php

namespace App\Support;

use App\Enums\AgentOperation;
use App\Models\AgentJob;
use App\Models\User;

/**
 * Menyiapkan isolasi Linux untuk satu user panel (PRD 7.1):
 * user Linux + home + open_basedir + kuota disk. Semua via job whitelist ke agent.
 */
class UserProvisioner
{
    public static function provision(User $user): void
    {
        if (! $user->linux_username) {
            return;
        }

        AgentJob::dispatch(AgentOperation::CreateLinuxUser, [
            'linux_username' => $user->linux_username,
            'panel_user_id' => $user->id,
            'home' => $user->homePath(),
            'open_basedir' => $user->openBasedir(),
            'disk_quota_mb' => $user->diskQuotaMb(),
            'shell_access' => false, // default: tanpa akses shell, hanya SFTP
        ], self::target($user));

        if ($quota = $user->diskQuotaMb()) {
            AgentJob::dispatch(AgentOperation::SetDiskQuota, [
                'linux_username' => $user->linux_username,
                'disk_quota_mb' => $quota,
            ], self::target($user));
        }

        $user->forceFill(['provisioned_at' => now()])->save();
    }

    /**
     * Hapus user Linux beserta semua yang dimilikinya di server. Daftar situs & database
     * dikumpulkan SEBELUM baris panel dihapus (relasinya ikut terhapus cascade).
     */
    public static function deprovision(User $user): void
    {
        if (! $user->linux_username) {
            return;
        }

        AgentJob::dispatch(AgentOperation::DeleteLinuxUser, [
            'linux_username' => $user->linux_username,
            'panel_user_id' => $user->id,
            'sites' => $user->sites()->pluck('domain')->all(),
            'databases' => $user->databases()->get(['name', 'db_user'])
                ->map(fn ($db) => ['db_name' => $db->name, 'db_user' => $db->db_user])->all(),
            'archive_home' => true, // home + dump DB diarsipkan agent sebelum dihapus
        ], self::target($user));
    }

    /** target_domain job yang menyangkut user Linux (untuk riwayat di halaman user). */
    public static function target(User $user): string
    {
        return 'user:'.$user->linux_username;
    }
}
