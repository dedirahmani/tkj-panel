<?php

namespace App\Enums;

/**
 * Peran pengguna panel (RBAC berjenjang).
 * Roles are hierarchical: SuperAdmin > Reseller > User.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Reseller = 'reseller';
    case User = 'user';

    /** Label dwibahasa (dipakai i18n key). */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('roles.super_admin'),
            self::Reseller => __('roles.reseller'),
            self::User => __('roles.user'),
        };
    }

    /** Apakah peran ini punya hak setingkat admin (server-wide). */
    public function isAdminLevel(): bool
    {
        return $this === self::SuperAdmin;
    }

    /** Apakah peran ini boleh membuat/mengelola user lain. */
    public function canManageUsers(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Reseller], true);
    }

    /**
     * Peran yang boleh dibuat oleh peran ini.
     * Reseller hanya boleh membuat User biasa.
     *
     * @return array<int, self>
     */
    public function creatableRoles(): array
    {
        return match ($this) {
            self::SuperAdmin => [self::Reseller, self::User],
            self::Reseller => [self::User],
            self::User => [],
        };
    }

    /**
     * Warna badge (kelas Tailwind) untuk UI. Peran bukan status, jadi tidak memakai merah/kuning
     * (DESIGN.md: warna status hanya untuk status). Tingkat akses dibedakan lewat kepekatan navy.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::SuperAdmin => 'bg-brand-600 text-white',
            self::Reseller => 'bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-300',
            self::User => 'bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-200',
        };
    }
}
