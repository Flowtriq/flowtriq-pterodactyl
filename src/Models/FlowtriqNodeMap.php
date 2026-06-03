<?php

namespace Flowtriq\Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowtriqNodeMap extends Model
{
    protected $table = 'flowtriq_node_map';

    protected $fillable = [
        'pterodactyl_node_id',
        'flowtriq_node_uuid',
        'flowtriq_api_key',
        'flowtriq_workspace_uuid',
        'flowtriq_ip',
        'status',
        'last_pps',
        'last_bps',
        'sp_auto_sync',
        'last_synced_at',
        'last_status_at',
    ];

    protected $casts = [
        'sp_auto_sync' => 'boolean',
        'last_pps' => 'integer',
        'last_bps' => 'integer',
        'last_synced_at' => 'datetime',
        'last_status_at' => 'datetime',
    ];

    /**
     * Get the Pterodactyl node this maps to (if available).
     */
    public function pterodactylNode(): BelongsTo
    {
        return $this->belongsTo('Pterodactyl\\Models\\Node', 'pterodactyl_node_id');
    }

    /**
     * Find the mapping for a given Pterodactyl node ID.
     */
    public static function forPteroNode(int $nodeId): ?self
    {
        return static::where('pterodactyl_node_id', $nodeId)->first();
    }

    /**
     * Find the central node mapping (pterodactyl_node_id is null).
     */
    public static function centralNode(): ?self
    {
        return static::whereNull('pterodactyl_node_id')->first();
    }

    /**
     * Get all per-wings node mappings.
     */
    public static function perWingsNodes()
    {
        return static::whereNotNull('pterodactyl_node_id')->get();
    }

    /**
     * Check if this node is currently under attack.
     */
    public function isUnderAttack(): bool
    {
        return $this->status === 'attack';
    }

    /**
     * Check if agent is online.
     */
    public function isOnline(): bool
    {
        return in_array($this->status, ['online', 'attack', 'elevated']);
    }

    /**
     * Status badge color for the admin panel.
     */
    public function statusColor(): string
    {
        return match ($this->status) {
            'online' => 'green',
            'attack' => 'red',
            'elevated' => 'orange',
            'offline' => 'gray',
            default => 'gray',
        };
    }
}
