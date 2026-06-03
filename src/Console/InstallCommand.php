<?php

namespace Flowtriq\Pterodactyl\Console;

use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Flowtriq\Pterodactyl\Services\FlowtriqApiClient;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'flowtriq:install';
    protected $description = 'Configure the Flowtriq Pterodactyl addon';

    public function handle(): int
    {
        $this->info('');
        $this->info('  Flowtriq DDoS Protection for Pterodactyl');
        $this->info('  =========================================');
        $this->info('');

        // API URL
        $currentUrl = FlowtriqSetting::get('api_url', config('flowtriq.api_url'));
        $url = $this->ask('Flowtriq API URL', $currentUrl);
        FlowtriqSetting::set('api_url', $url);

        // Deploy Token
        $token = $this->secret('Deploy Token (from Flowtriq > Settings > API)');
        if ($token) {
            FlowtriqSetting::set('deploy_token', $token);
        }

        // Test connection
        $this->info('Testing connection...');
        app()->forgetInstance(FlowtriqApiClient::class);
        $api = app(FlowtriqApiClient::class);
        $result = $api->testConnection();

        if ($result['ok'] ?? false) {
            $workspace = $result['workspace'] ?? $result['data'] ?? [];
            $this->info('Connected to workspace: ' . ($workspace['name'] ?? 'Unknown'));
        } else {
            $this->error('Connection failed: ' . FlowtriqApiClient::errorMessage($result));
            $this->warn('Check your API URL and deploy token, then re-run this command.');
            return 1;
        }

        // Deployment mode
        $mode = $this->choice('Deployment mode', [
            'central' => 'Central (one agent on panel server)',
            'per_wings' => 'Per-Wings (one agent per Wings node)',
        ], FlowtriqSetting::get('deployment_mode', 'per_wings'));

        FlowtriqSetting::set('deployment_mode', $mode);

        $this->info('');
        $this->info('Settings saved. Next steps:');
        $this->info('');

        if ($mode === 'central') {
            $this->info('  1. Go to Admin > Flowtriq > Nodes to link the central node');
            $this->info('  2. Install ftagent on this server');
            $this->info('  3. Service ports will auto-sync from Pterodactyl');
        } else {
            $this->info('  1. Go to Admin > Flowtriq > Nodes to link each Wings node');
            $this->info('  2. Install ftagent on each Wings host');
            $this->info('  3. Service ports will auto-sync per node');
        }

        $this->info('');
        $this->info('Or run: php artisan flowtriq:sync    (force sync all ports)');
        $this->info('        php artisan flowtriq:status  (check node status)');
        $this->info('');

        return 0;
    }
}
