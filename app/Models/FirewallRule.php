<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirewallRule extends Model
{
    protected $fillable = [
        'action',
        'port',
        'protocol',
        'source_ip',
        'direction',
        'comment',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Profil layanan umum untuk tombol tambah-cepat. */
    public static function serviceProfiles(): array
    {
        return [
            'SSH' => ['port' => 22, 'protocol' => 'tcp'],
            'HTTP' => ['port' => 80, 'protocol' => 'tcp'],
            'HTTPS' => ['port' => 443, 'protocol' => 'tcp'],
            'MySQL' => ['port' => 3306, 'protocol' => 'tcp'],
            'FTP' => ['port' => 21, 'protocol' => 'tcp'],
            'DNS' => ['port' => 53, 'protocol' => 'udp'],
        ];
    }

    /**
     * Payload terstruktur untuk agent ufw_rule.
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(string $action): array
    {
        return [
            'op' => $action, // add | delete
            'action' => $this->action,
            'port' => $this->port,
            'protocol' => $this->protocol,
            'source_ip' => $this->source_ip,
            'direction' => $this->direction,
        ];
    }

    /** Nilai target_domain job agent untuk aturan ini (melacak hasil per aturan). */
    public function agentTarget(): string
    {
        return 'firewall:'.$this->id;
    }

    public function summary(): string
    {
        $parts = [strtoupper($this->action)];
        $parts[] = $this->source_ip ? "from {$this->source_ip}" : 'any';
        $parts[] = $this->port ? "port {$this->port}/{$this->protocol}" : 'any port';

        return implode(' · ', $parts);
    }
}
