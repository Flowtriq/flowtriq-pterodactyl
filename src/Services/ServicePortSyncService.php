<?php

namespace Flowtriq\Pterodactyl\Services;

use Flowtriq\Pterodactyl\Models\FlowtriqNodeMap;
use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServicePortSyncService
{
    private FlowtriqApiClient $api;

    public function __construct(FlowtriqApiClient $api)
    {
        $this->api = $api;
    }

    /**
     * Sync service ports based on current deployment mode.
     */
    public function syncAll(): void
    {
        $mode = FlowtriqSetting::get('deployment_mode', config('flowtriq.deployment_mode'));

        if ($mode === 'central') {
            $this->syncCentral();
        } else {
            $this->syncAllPerWings();
        }
    }

    /**
     * Central mode: aggregate ALL game server ports from every Pterodactyl node
     * into the single central Flowtriq node.
     */
    public function syncCentral(): void
    {
        $map = FlowtriqNodeMap::centralNode();
        if (!$map) {
            Log::warning('[Flowtriq] Central mode: no central node configured');
            return;
        }

        $ports = $this->buildServicePortsCentral();
        $this->pushServicePorts($map, $ports);
    }

    /**
     * Per-Wings mode: sync a single Pterodactyl node's ports to its mapped Flowtriq node.
     */
    public function syncNode(int $pterodactylNodeId): void
    {
        $mode = FlowtriqSetting::get('deployment_mode', config('flowtriq.deployment_mode'));

        // In central mode, any node change triggers a full central sync
        if ($mode === 'central') {
            $this->syncCentral();
            return;
        }

        $map = FlowtriqNodeMap::forPteroNode($pterodactylNodeId);
        if (!$map || !$map->sp_auto_sync) {
            return;
        }

        $ports = $this->buildServicePorts($pterodactylNodeId);
        $this->pushServicePorts($map, $ports);
    }

    /**
     * Per-Wings mode: sync all mapped nodes.
     */
    public function syncAllPerWings(): void
    {
        $maps = FlowtriqNodeMap::perWingsNodes();

        foreach ($maps as $map) {
            if (!$map->sp_auto_sync) {
                continue;
            }

            $ports = $this->buildServicePorts($map->pterodactyl_node_id);
            $this->pushServicePorts($map, $ports);
        }
    }

    /**
     * Build service ports array for a single Pterodactyl node.
     * Queries allocations assigned to servers on this node.
     */
    public function buildServicePorts(int $pterodactylNodeId): array
    {
        $allocations = DB::table('allocations')
            ->join('servers', 'allocations.server_id', '=', 'servers.id')
            ->leftJoin('eggs', 'servers.egg_id', '=', 'eggs.id')
            ->where('allocations.node_id', $pterodactylNodeId)
            ->whereNotNull('allocations.server_id')
            ->select([
                'allocations.port',
                'servers.name as server_name',
                'eggs.name as egg_name',
            ])
            ->get();

        return $this->buildPortsArray($allocations);
    }

    /**
     * Build service ports from ALL Pterodactyl nodes (central mode).
     */
    public function buildServicePortsCentral(): array
    {
        $allocations = DB::table('allocations')
            ->join('servers', 'allocations.server_id', '=', 'servers.id')
            ->leftJoin('eggs', 'servers.egg_id', '=', 'eggs.id')
            ->leftJoin('nodes', 'allocations.node_id', '=', 'nodes.id')
            ->whereNotNull('allocations.server_id')
            ->select([
                'allocations.port',
                'servers.name as server_name',
                'eggs.name as egg_name',
                'nodes.name as node_name',
            ])
            ->get();

        return $this->buildPortsArray($allocations, true);
    }

    /**
     * Convert allocation rows into the service_ports API format.
     * Groups ports by server+protocol and compresses consecutive ports into ranges.
     */
    private function buildPortsArray($allocations, bool $includeNodeName = false): array
    {
        $protocolMap = config('flowtriq.protocol_map', []);
        $defaultProtocol = config('flowtriq.default_protocol', 'both');

        // Group allocations by server name + protocol
        $groups = [];
        foreach ($allocations as $alloc) {
            $protocol = $this->detectProtocol($alloc->egg_name, $protocolMap, $defaultProtocol);
            $label = $alloc->server_name ?? 'Unknown Server';
            if ($includeNodeName && !empty($alloc->node_name)) {
                $label .= ' @ ' . $alloc->node_name;
            }

            $key = $protocol . ':' . $label;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'protocol' => $protocol,
                    'label' => $label,
                    'ports' => [],
                ];
            }

            $groups[$key]['ports'][] = (int) $alloc->port;
        }

        // Build final array with compressed port ranges
        $servicePorts = [];
        foreach ($groups as $group) {
            sort($group['ports']);
            $portValue = $this->compressPorts($group['ports']);

            $servicePorts[] = [
                'protocol' => $group['protocol'],
                'port_value' => $portValue,
                'label' => $group['label'],
            ];
        }

        // Add system ports (Wings daemon, SFTP)
        foreach (config('flowtriq.system_ports', []) as $sysPort) {
            $servicePorts[] = $sysPort;
        }

        return $servicePorts;
    }

    /**
     * Detect the network protocol for a game server based on its egg name.
     */
    private function detectProtocol(?string $eggName, array $protocolMap, string $default): string
    {
        if (!$eggName) {
            return $default;
        }

        $eggLower = strtolower($eggName);

        foreach ($protocolMap as $pattern => $protocol) {
            if (str_contains($eggLower, strtolower($pattern))) {
                return $protocol;
            }
        }

        return $default;
    }

    /**
     * Compress a sorted array of port numbers into range notation.
     * e.g., [27015, 27016, 27017, 27020] => "27015-27017,27020"
     */
    private function compressPorts(array $ports): string
    {
        if (empty($ports)) {
            return '';
        }

        $ports = array_unique($ports);
        sort($ports);

        $ranges = [];
        $start = $ports[0];
        $end = $ports[0];

        for ($i = 1; $i < count($ports); $i++) {
            if ($ports[$i] === $end + 1) {
                $end = $ports[$i];
            } else {
                $ranges[] = $start === $end ? (string) $start : $start . '-' . $end;
                $start = $ports[$i];
                $end = $ports[$i];
            }
        }

        $ranges[] = $start === $end ? (string) $start : $start . '-' . $end;

        return implode(',', $ranges);
    }

    /**
     * Push service ports to the Flowtriq API for a given node mapping.
     */
    private function pushServicePorts(FlowtriqNodeMap $map, array $ports): void
    {
        $sensitivity = FlowtriqSetting::get('sp_sensitivity', config('flowtriq.sp_sensitivity'));
        $responseMode = FlowtriqSetting::get('sp_response_mode', config('flowtriq.sp_response_mode'));
        $overrideToken = $map->flowtriq_workspace_uuid
            ? ($map->flowtriq_api_key ?? '')
            : '';

        $result = $this->api->updateServicePorts(
            $map->flowtriq_node_uuid,
            $ports,
            $sensitivity,
            $responseMode,
            $overrideToken
        );

        if ($result['ok'] ?? false) {
            $map->update(['last_synced_at' => now()]);
            Log::info('[Flowtriq] Synced ' . count($ports) . ' service ports to node ' . $map->flowtriq_node_uuid);
        } else {
            Log::warning('[Flowtriq] Failed to sync service ports to node ' . $map->flowtriq_node_uuid . ': ' . FlowtriqApiClient::errorMessage($result));
        }
    }
}
