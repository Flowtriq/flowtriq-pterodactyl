<?php

namespace Flowtriq\Pterodactyl\Http\Controllers\Admin;

use Flowtriq\Pterodactyl\Models\FlowtriqNodeMap;
use Flowtriq\Pterodactyl\Models\FlowtriqIncidentCache;
use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Flowtriq\Pterodactyl\Services\FlowtriqApiClient;
use Flowtriq\Pterodactyl\Services\ServicePortSyncService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class NodeController extends Controller
{
    public function index()
    {
        $mode = FlowtriqSetting::get('deployment_mode', config('flowtriq.deployment_mode'));

        // Get all Pterodactyl nodes
        $pteroNodes = DB::table('nodes')->get();

        // Get all Flowtriq mappings
        $mappings = FlowtriqNodeMap::all()->keyBy('pterodactyl_node_id');

        // Central node (if in central mode)
        $centralMap = FlowtriqNodeMap::centralNode();

        // Count service ports per node
        $portCounts = [];
        foreach ($pteroNodes as $node) {
            $portCounts[$node->id] = DB::table('allocations')
                ->where('node_id', $node->id)
                ->whereNotNull('server_id')
                ->count();
        }

        return view('flowtriq::admin.nodes.index', [
            'mode' => $mode,
            'pteroNodes' => $pteroNodes,
            'mappings' => $mappings,
            'centralMap' => $centralMap,
            'portCounts' => $portCounts,
        ]);
    }

    public function show(int $nodeId)
    {
        $pteroNode = DB::table('nodes')->where('id', $nodeId)->first();
        if (!$pteroNode) {
            abort(404, 'Node not found');
        }

        $map = FlowtriqNodeMap::forPteroNode($nodeId);

        // Get assigned allocations with server info
        $allocations = DB::table('allocations')
            ->leftJoin('servers', 'allocations.server_id', '=', 'servers.id')
            ->leftJoin('eggs', 'servers.egg_id', '=', 'eggs.id')
            ->where('allocations.node_id', $nodeId)
            ->whereNotNull('allocations.server_id')
            ->select([
                'allocations.port',
                'allocations.ip',
                'servers.name as server_name',
                'eggs.name as egg_name',
            ])
            ->orderBy('allocations.port')
            ->get();

        // Get cached incidents
        $incidents = $map
            ? FlowtriqIncidentCache::forNode($nodeId, 25)
            : collect();

        return view('flowtriq::admin.nodes.show', [
            'pteroNode' => $pteroNode,
            'map' => $map,
            'allocations' => $allocations,
            'incidents' => $incidents,
            'protocolMap' => config('flowtriq.protocol_map', []),
            'defaultProtocol' => config('flowtriq.default_protocol', 'both'),
        ]);
    }

    public function link(Request $request, int $nodeId)
    {
        $request->validate([
            'action' => 'required|in:create,existing',
            'flowtriq_node_uuid' => 'required_if:action,existing|nullable|string|max:36',
            'flowtriq_api_key' => 'required_if:action,existing|nullable|string|max:64',
        ]);

        $pteroNode = DB::table('nodes')->where('id', $nodeId)->first();
        if (!$pteroNode) {
            abort(404);
        }

        $api = app(FlowtriqApiClient::class);

        if ($request->input('action') === 'create') {
            // Create a new Flowtriq node
            $result = $api->createNode(
                'Pterodactyl: ' . $pteroNode->name,
                $pteroNode->fqdn
            );

            if (!($result['ok'] ?? false)) {
                return back()->with('error', 'Failed to create Flowtriq node: ' . FlowtriqApiClient::errorMessage($result));
            }

            $node = $result['node'] ?? $result['data'] ?? [];

            FlowtriqNodeMap::create([
                'pterodactyl_node_id' => $nodeId,
                'flowtriq_node_uuid' => $node['uuid'] ?? '',
                'flowtriq_api_key' => $node['node_key'] ?? $node['api_key'] ?? '',
                'flowtriq_ip' => $pteroNode->fqdn,
            ]);
        } else {
            // Link to existing Flowtriq node
            FlowtriqNodeMap::create([
                'pterodactyl_node_id' => $nodeId,
                'flowtriq_node_uuid' => $request->input('flowtriq_node_uuid'),
                'flowtriq_api_key' => $request->input('flowtriq_api_key'),
                'flowtriq_ip' => $pteroNode->fqdn,
            ]);
        }

        // Trigger initial sync
        app(ServicePortSyncService::class)->syncNode($nodeId);

        return redirect()->route('admin.flowtriq.nodes.show', $nodeId)
            ->with('success', 'Node linked to Flowtriq.');
    }

    public function unlink(int $nodeId)
    {
        FlowtriqNodeMap::where('pterodactyl_node_id', $nodeId)->delete();

        return redirect()->route('admin.flowtriq.nodes')
            ->with('success', 'Node unlinked from Flowtriq.');
    }

    public function sync(int $nodeId)
    {
        app(ServicePortSyncService::class)->syncNode($nodeId);

        return back()->with('success', 'Service ports synced.');
    }

    /**
     * Link the central node (central mode).
     */
    public function linkCentral(Request $request)
    {
        $request->validate([
            'action' => 'required|in:create,existing',
            'flowtriq_node_uuid' => 'required_if:action,existing|nullable|string|max:36',
            'flowtriq_api_key' => 'required_if:action,existing|nullable|string|max:64',
            'central_ip' => 'required_if:action,create|nullable|string|max:45',
        ]);

        $api = app(FlowtriqApiClient::class);

        // Remove any existing central node
        FlowtriqNodeMap::whereNull('pterodactyl_node_id')->delete();

        if ($request->input('action') === 'create') {
            $result = $api->createNode(
                'Pterodactyl Central',
                $request->input('central_ip')
            );

            if (!($result['ok'] ?? false)) {
                return back()->with('error', 'Failed to create Flowtriq node: ' . FlowtriqApiClient::errorMessage($result));
            }

            $node = $result['node'] ?? $result['data'] ?? [];

            FlowtriqNodeMap::create([
                'pterodactyl_node_id' => null,
                'flowtriq_node_uuid' => $node['uuid'] ?? '',
                'flowtriq_api_key' => $node['node_key'] ?? $node['api_key'] ?? '',
                'flowtriq_ip' => $request->input('central_ip'),
            ]);
        } else {
            FlowtriqNodeMap::create([
                'pterodactyl_node_id' => null,
                'flowtriq_node_uuid' => $request->input('flowtriq_node_uuid'),
                'flowtriq_api_key' => $request->input('flowtriq_api_key'),
            ]);
        }

        // Trigger initial sync
        app(ServicePortSyncService::class)->syncCentral();

        return redirect()->route('admin.flowtriq.nodes')
            ->with('success', 'Central node configured.');
    }
}
