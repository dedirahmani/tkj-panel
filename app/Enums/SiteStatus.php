<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return __('sites.status.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::Inactive => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
            self::Maintenance => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-500',
            self::Inactive => 'bg-slate-400',
            self::Maintenance => 'bg-amber-500',
        };
    }
}
