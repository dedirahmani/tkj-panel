<?php

namespace App\Enums;

enum CertStatus: string
{
    case Pending = 'pending';   // permintaan diantre ke agent (acme.sh)
    case Issued = 'issued';     // terbit & terpasang
    case Failed = 'failed';     // gagal terbit/renew
    case Expired = 'expired';

    public function label(): string
    {
        return __('ssl.status.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
            self::Issued => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::Failed => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            self::Expired => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        };
    }

    public function dotClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-400',
            self::Issued => 'bg-emerald-500',
            self::Failed => 'bg-rose-500',
            self::Expired => 'bg-amber-500',
        };
    }
}
