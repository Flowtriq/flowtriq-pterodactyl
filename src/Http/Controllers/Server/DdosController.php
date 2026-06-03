<?php

namespace Flowtriq\Pterodactyl\Http\Controllers\Server;

use Flowtriq\Pterodactyl\Models\FlowtriqIncidentCache;
use Flowtriq\Pterodactyl\Models\FlowtriqNodeMap;
use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DdosController extends Controller
{
    /**
     * DDoS protection tab for a game server owner.
     */
    public function index(Request $request, string $serverUuid)
    {
        $server = $this->resolveServer($serverUuid);
        if (!$server) {
            abort(404, 'Server not found');
        }

        $mode = FlowtriqSetting::get('deployment_mode', config('flowtriq.deployment_mode'));

        // Get Flowtriq node mapping
        if ($mode === 'central') {
            $map = FlowtriqNodeMap::centralNode();
        } else {
            $map = FlowtriqNodeMap::forPteroNode($server->node_id);
        }

        // Get this server's allocated ports
        $allocations = DB::table('allocations')
            ->where('server_id', $server->id)
            ->select(['port', 'ip'])
            ->get();

        $ports = $allocations->pluck('port')->map(fn($p) => (int) $p)->toArray();
        $primaryIp = $allocations->first()->ip ?? $server->ip ?? '-';

        // Get incidents filtered to this server's ports
        $incidents = collect();
        if ($map) {
            $nodeId = $map->pterodactyl_node_id ?? 0;
            $incidents = FlowtriqIncidentCache::forPorts($nodeId, $ports, 20);

            // In central mode, also check by flowtriq_node_uuid
            if ($mode === 'central' && $incidents->isEmpty()) {
                $incidents = FlowtriqIncidentCache::forFlowtriqNode($map->flowtriq_node_uuid, 20)
                    ->filter(function ($incident) use ($ports) {
                        if (empty($incident->target_ports)) {
                            return true;
                        }
                        return !empty(array_intersect($incident->target_ports, $ports));
                    })
                    ->values();
            }
        }

        return view('flowtriq::server.ddos', [
            'server' => $server,
            'map' => $map,
            'allocations' => $allocations,
            'ports' => $ports,
            'primaryIp' => $primaryIp,
            'incidents' => $incidents,
        ]);
    }

    /**
     * JSON endpoint for AJAX status refresh.
     */
    public function status(Request $request, string $serverUuid)
    {
        $server = $this->resolveServer($serverUuid);
        if (!$server) {
            return response()->json(['error' => 'Server not found'], 404);
        }

        $mode = FlowtriqSetting::get('deployment_mode', config('flowtriq.deployment_mode'));

        if ($mode === 'central') {
            $map = FlowtriqNodeMap::centralNode();
        } else {
            $map = FlowtriqNodeMap::forPteroNode($server->node_id);
        }

        if (!$map) {
            return response()->json([
                'protected' => false,
                'status' => 'unlinked',
            ]);
        }

        $allocations = DB::table('allocations')
            ->where('server_id', $server->id)
            ->pluck('port')
            ->map(fn($p) => (int) $p)
            ->toArray();

        $activeIncidents = FlowtriqIncidentCache::where('flowtriq_node_uuid', $map->flowtriq_node_uuid)
            ->where('status', 'active')
            ->get()
            ->filter(function ($incident) use ($allocations) {
                if (empty($incident->target_ports)) {
                    return true;
                }
                return !empty(array_intersect($incident->target_ports, $allocations));
            });

        return response()->json([
            'protected' => true,
            'status' => $map->status,
            'under_attack' => $activeIncidents->isNotEmpty(),
            'active_incidents' => $activeIncidents->count(),
            'pps' => $map->last_pps,
        ]);
    }

    /**
     * Resolve a Pterodactyl server by UUID.
     */
    private function resolveServer(string $uuid)
    {
        return DB::table('servers')
            ->where('uuid', $uuid)
            ->orWhere('uuidShort', $uuid)
            ->first();
    }
}
