<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flowtriq API Configuration
    |--------------------------------------------------------------------------
    |
    | These values can be overridden via the admin panel settings page.
    | Database settings (flowtriq_settings table) take priority over these.
    |
    */

    'api_url' => env('FLOWTRIQ_API_URL', 'https://flowtriq.com'),

    'deploy_token' => env('FLOWTRIQ_DEPLOY_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Deployment Mode
    |--------------------------------------------------------------------------
    |
    | "central"   - One ftagent on the panel server, all ports aggregated into
    |               one Flowtriq node.
    | "per_wings" - One ftagent per Wings node, each mapped to its own Flowtriq
    |               node. Supports optional white-label sub-workspaces.
    |
    */

    'deployment_mode' => env('FLOWTRIQ_MODE', 'per_wings'),

    /*
    |--------------------------------------------------------------------------
    | Central Mode Settings
    |--------------------------------------------------------------------------
    */

    'central_node_uuid' => env('FLOWTRIQ_CENTRAL_NODE_UUID', ''),

    /*
    |--------------------------------------------------------------------------
    | Service Port Sync
    |--------------------------------------------------------------------------
    */

    'sp_auto_sync' => true,

    'sp_sensitivity' => 'standard', // standard, aggressive, relaxed, custom

    'sp_response_mode' => 'full', // full, pipeline, onnode

    /*
    |--------------------------------------------------------------------------
    | Polling Intervals (seconds)
    |--------------------------------------------------------------------------
    */

    'poll_status_interval' => 60,

    'poll_incidents_interval' => 30,

    /*
    |--------------------------------------------------------------------------
    | Protocol Detection
    |--------------------------------------------------------------------------
    |
    | Maps egg names (lowercase partial match) to default protocols.
    | Used when auto-detecting game server protocols for service port config.
    |
    */

    'protocol_map' => [
        'minecraft'       => 'tcp',
        'bedrock'         => 'udp',
        'fivem'           => 'both',
        'redm'            => 'both',
        'rust'            => 'udp',
        'ark'             => 'udp',
        'cs2'             => 'both',
        'csgo'            => 'both',
        'counter-strike'  => 'both',
        'garry'           => 'udp',
        'gmod'            => 'udp',
        'terraria'        => 'tcp',
        'valheim'         => 'udp',
        'palworld'        => 'udp',
        'project zomboid' => 'udp',
        'unturned'        => 'udp',
        'squad'           => 'udp',
        'teamspeak'       => 'udp',
        'mumble'          => 'udp',
    ],

    'default_protocol' => 'both',

    /*
    |--------------------------------------------------------------------------
    | System Ports
    |--------------------------------------------------------------------------
    |
    | Always included as service ports so ftagent never blocks panel traffic.
    |
    */

    'system_ports' => [
        ['protocol' => 'tcp', 'port_value' => '8080', 'label' => 'Wings Daemon'],
        ['protocol' => 'tcp', 'port_value' => '2022', 'label' => 'Wings SFTP'],
    ],
];
