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

    /** Warna badge (kelas Tailwind) untuk UI. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::SuperAdmin => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            self::Reseller => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            self::User => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        };
    }
}
