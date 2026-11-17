<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $fillable = [
        'metric',
        'threshold',
        'is_active',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'threshold' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function label(): string
    {
        return __('monitoring.metric_'.$this->metric);
    }

    /** Unit tampilan ambang (persen atau angka load). */
    public function displayThreshold(): string
    {
        return $this->metric === 'load'
            ? number_format($this->threshold / 100, 2)
            : $this->threshold.'%';
    }
}
