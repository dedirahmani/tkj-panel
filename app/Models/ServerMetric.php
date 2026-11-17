<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agent_job_id',
        'captured_at',
        'cpu_percent', 'ram_percent', 'ram_used_mb', 'ram_total_mb',
        'disk_percent', 'disk_used_mb', 'disk_total_mb',
        'load_1', 'load_5', 'load_15', 'net_rx_kb', 'net_tx_kb',
        'processes', 'ports',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'cpu_percent' => 'float',
            'ram_percent' => 'float',
            'disk_percent' => 'float',
            'load_1' => 'float',
            'load_5' => 'float',
            'load_15' => 'float',
            'processes' => 'array',
            'ports' => 'array',
        ];
    }

    /** Nilai metrik menurut kunci alert (cpu/ram/disk/load). */
    public function valueFor(string $metric): float
    {
        return match ($metric) {
            'cpu' => $this->cpu_percent,
            'ram' => $this->ram_percent,
            'disk' => $this->disk_percent,
            'load' => $this->load_1 * 100, // dibandingkan threshold (load*100)
            default => 0,
        };
    }
}
