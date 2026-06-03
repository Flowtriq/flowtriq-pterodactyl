<?php

namespace Flowtriq\Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

class FlowtriqIncidentCache extends Model
{
    protected $table = 'flowtriq_incidents_cache';

    protected $fillable = [
        'flowtriq_incident_uuid',
        'pterodactyl_node_id',
        'flowtriq_node_uuid',
        'attack_family',
        'severity',
        'status',
        'peak_pps',
        'peak_bps',
        'target_ports',
        'started_at',
        'resolved_at',
        'raw_data',
    ];

    protected $casts = [
        'peak_pps' => 'integer',
        'peak_bps' => 'integer',
        'target_ports' => 'array',
        'raw_data' => 'array',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get incidents for a specific Pterodactyl node.
     */
    public static function forNode(int $nodeId, int $limit = 25)
    {
        return static::where('pterodactyl_node_id', $nodeId)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get incidents for a specific Flowtriq node UUID.
     */
    public static function forFlowtriqNode(string $nodeUuid, int $limit = 25)
    {
        return static::where('flowtriq_node_uuid', $nodeUuid)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get incidents that overlap with specific ports (for server owner view).
     * Returns incidents where target_ports intersects with the given ports,
     * or where target_ports is null (IP-level attack).
     */
    public static function forPorts(int $nodeId, array $ports, int $limit = 25)
    {
        return static::where('pterodactyl_node_id', $nodeId)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->filter(function ($incident) use ($ports) {
                // IP-level attacks affect everyone on the node
                if (empty($incident->target_ports)) {
                    return true;
                }

                // Check for port overlap
                return !empty(array_intersect($incident->target_ports, $ports));
            })
            ->values();
    }

    /**
     * Whether this incident is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Duration in human-readable format.
     */
    public function duration(): string
    {
        if (!$this->started_at) {
            return '-';
        }

        $end = $this->resolved_at ?? now();
        $seconds = $this->started_at->diffInSeconds($end);

        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        }

        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }

    /**
     * Format PPS for display (e.g., "1.2M", "45.3K").
     */
    public function formattedPps(): string
    {
        return self::formatNumber($this->peak_pps);
    }

    /**
     * Format BPS for display.
     */
    public function formattedBps(): string
    {
        return self::formatNumber($this->peak_bps) . 'bps';
    }

    private static function formatNumber(int $n): string
    {
        if ($n >= 1_000_000_000) {
            return round($n / 1_000_000_000, 1) . 'G';
        }
        if ($n >= 1_000_000) {
            return round($n / 1_000_000, 1) . 'M';
        }
        if ($n >= 1_000) {
            return round($n / 1_000, 1) . 'K';
        }

        return (string) $n;
    }
}
