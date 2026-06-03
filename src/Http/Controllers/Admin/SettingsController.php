<?php

namespace Flowtriq\Pterodactyl\Http\Controllers\Admin;

use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Flowtriq\Pterodactyl\Services\FlowtriqApiClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = FlowtriqSetting::allSettings();

        return view('flowtriq::admin.settings', [
            'settings' => $settings,
            'defaults' => config('flowtriq'),
        ]);
    }

    public function store(Request $request)
    {
        $fields = [
            'api_url',
            'deploy_token',
            'deployment_mode',
            'central_node_uuid',
            'sp_sensitivity',
            'sp_response_mode',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                FlowtriqSetting::set($field, $request->input($field));
            }
        }

        // Clear the API client singleton so it picks up new credentials
        app()->forgetInstance(FlowtriqApiClient::class);

        return redirect()->route('admin.flowtriq.settings')
            ->with('success', 'Settings saved.');
    }

    public function testConnection()
    {
        $api = app(FlowtriqApiClient::class);
        $result = $api->testConnection();

        if ($result['ok'] ?? false) {
            $workspace = $result['workspace'] ?? $result['data'] ?? [];
            $name = $workspace['name'] ?? 'Unknown';

            return response()->json([
                'ok' => true,
                'message' => 'Connected to workspace: ' . $name,
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => FlowtriqApiClient::errorMessage($result),
        ], 422);
    }
}
