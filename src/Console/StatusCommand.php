<?php

namespace Flowtriq\Pterodactyl\Console;

use Flowtriq\Pterodactyl\Models\FlowtriqNodeMap;
use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StatusCommand extends Command
{
    protected $signature = 'flowtriq:status';
    protected $description = 'Show Flowtriq protection status for all nodes';

    public function handle(): int
    {
        $mode = FlowtriqSetting::get('deployment_mode', config('flowtriq.deployment_mode'));
        $this->info('Deployment mode: ' . strtoupper($mode));
        $this->info('');

        $maps = FlowtriqNodeMap::all();

        if ($maps->isEmpty()) {
            $this->warn('No nodes linked. Run: php artisan flowtriq:install');
            return 0;
        }

        $rows = [];
        foreach ($maps as $map) {
            $pteroNode = null;
            $portCount = 0;

            if ($map->pterodactyl_node_id) {
                $pteroNode = DB::table('nodes')->where('id', $map->pterodactyl_node_id)->first();
                $portCount = DB::table('allocations')
                    ->where('node_id', $map->pterodactyl_node_id)
                    ->whereNotNull('server_id')
                    ->count();
            } else {
                // Central mode: count all ports
                $portCount = DB::table('allocations')
                    ->whereNotNull('server_id')
                    ->count();
            }

            $rows[] = [
                $pteroNode ? $pteroNode->name : 'Central',
                $map->flowtriq_node_uuid,
                strtoupper($map->status),
                number_format($map->last_pps),
                $portCount,
                $map->last_synced_at ? $map->last_synced_at->diffForHumans() : 'Never',
            ];
        }

        $this->table(
            ['Node', 'Flowtriq UUID', 'Status', 'PPS', 'Ports', 'Last Sync'],
            $rows
        );

        return 0;
    }
}
