<?php

namespace Flowtriq\Pterodactyl\Jobs;

use Flowtriq\Pterodactyl\Models\FlowtriqIncidentCache;
use Flowtriq\Pterodactyl\Models\FlowtriqNodeMap;
use Flowtriq\Pterodactyl\Services\FlowtriqApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollIncidentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(FlowtriqApiClient $api): void
    {
        $maps = FlowtriqNodeMap::all();

        foreach ($maps as $map) {
            $overrideToken = $map->flowtriq_workspace_uuid ? ($map->flowtriq_api_key ?? '') : '';

            $result = $api->getIncidents($map->flowtriq_node_uuid, 10, $overrideToken);

            if (!($result['ok'] ?? false)) {
                continue;
            }

            $incidents = $result['incidents'] ?? $result['data'] ?? [];

            foreach ($incidents as $incident) {
                $uuid = $incident['uuid'] ?? null;
                if (!$uuid) {
                    continue;
                }

                // Extract target ports from incident data
                $targetPorts = $this->extractTargetPorts($incident);

                FlowtriqIncidentCache::updateOrCreate(
                    ['flowtriq_incident_uuid' => $uuid],
                    [
                        'pterodactyl_node_id' => $map->pterodactyl_node_id,
                        'flowtriq_node_uuid' => $map->flowtriq_node_uuid,
                        'attack_family' => $incident['attack_family'] ?? $incident['type'] ?? null,
                        'severity' => $incident['severity'] ?? null,
                        'status' => $incident['status'] ?? 'active',
                        'peak_pps' => $incident['peak_pps'] ?? $incident['pps'] ?? 0,
                        'peak_bps' => $incident['peak_bps'] ?? $incident['bps'] ?? 0,
                        'target_ports' => $targetPorts,
                        'started_at' => $incident['started_at'] ?? $incident['detection_started_at'] ?? null,
                        'resolved_at' => $incident['resolved_at'] ?? null,
                        'raw_data' => $incident,
                    ]
                );
            }
        }
    }

    /**
     * Extract destination ports from incident metadata.
     */
    private function extractTargetPorts(array $incident): ?array
    {
        // Try top_ports field
        $topPorts = $incident['top_ports'] ?? $incident['destination_ports'] ?? null;
        if (is_array($topPorts) && !empty($topPorts)) {
            return array_map('intval', array_column($topPorts, 'port') ?: array_keys($topPorts) ?: $topPorts);
        }

        // Try dst_port field
        if (!empty($incident['dst_port'])) {
            return [(int) $incident['dst_port']];
        }

        return null;
    }
}
