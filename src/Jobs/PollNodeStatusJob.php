<?php

namespace Flowtriq\Pterodactyl\Jobs;

use Flowtriq\Pterodactyl\Models\FlowtriqNodeMap;
use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Flowtriq\Pterodactyl\Services\FlowtriqApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollNodeStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(FlowtriqApiClient $api): void
    {
        $maps = FlowtriqNodeMap::all();

        foreach ($maps as $map) {
            $overrideToken = $map->flowtriq_workspace_uuid ? ($map->flowtriq_api_key ?? '') : '';

            $result = $api->getNode($map->flowtriq_node_uuid, $overrideToken);

            if (!($result['ok'] ?? false)) {
                $map->update([
                    'status' => 'offline',
                    'last_status_at' => now(),
                ]);
                continue;
            }

            $node = $result['node'] ?? $result['data'] ?? $result;

            $map->update([
                'status' => $node['status'] ?? 'unknown',
                'last_pps' => $node['last_pps'] ?? $node['pps'] ?? 0,
                'last_bps' => $node['last_bps'] ?? $node['bps'] ?? 0,
                'last_status_at' => now(),
            ]);
        }
    }
}
