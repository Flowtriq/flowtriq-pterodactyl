<?php

namespace Flowtriq\Pterodactyl;

use Flowtriq\Pterodactyl\Console\InstallCommand;
use Flowtriq\Pterodactyl\Console\StatusCommand;
use Flowtriq\Pterodactyl\Console\SyncCommand;
use Flowtriq\Pterodactyl\Jobs\PollIncidentsJob;
use Flowtriq\Pterodactyl\Jobs\PollNodeStatusJob;
use Flowtriq\Pterodactyl\Listeners\ServerCreatedListener;
use Flowtriq\Pterodactyl\Listeners\ServerDeletedListener;
use Flowtriq\Pterodactyl\Models\FlowtriqSetting;
use Flowtriq\Pterodactyl\Observers\AllocationObserver;
use Flowtriq\Pterodactyl\Services\FlowtriqApiClient;
use Flowtriq\Pterodactyl\Services\ServicePortSyncService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pterodactyl\Models\Allocation;

class FlowtriqServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/flowtriq.php' => config_path('flowtriq.php'),
        ], 'flowtriq-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'flowtriq');

        // Register routes
        $this->registerRoutes();

        // Register event listeners
        $this->registerEventListeners();

        // Register scheduled jobs
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->job(new PollNodeStatusJob)->everyMinute();
            $schedule->job(new PollIncidentsJob)->everyThirtySeconds();
        });

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SyncCommand::class,
                StatusCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/flowtriq.php', 'flowtriq');

        $this->app->singleton(FlowtriqApiClient::class, function () {
            $url = FlowtriqSetting::get('api_url', config('flowtriq.api_url'));
            $token = FlowtriqSetting::get('deploy_token', config('flowtriq.deploy_token'));

            return new FlowtriqApiClient($url, $token);
        });

        $this->app->singleton(ServicePortSyncService::class);
    }

    protected function registerRoutes(): void
    {
        // Admin routes
        Route::middleware(['web', 'auth', 'admin'])
            ->prefix('admin/flowtriq')
            ->group(__DIR__ . '/../routes/admin.php');

        // Server owner API routes (AJAX)
        Route::middleware(['web', 'auth'])
            ->prefix('api/flowtriq')
            ->group(__DIR__ . '/../routes/api.php');
    }

    protected function registerEventListeners(): void
    {
        // Pterodactyl server lifecycle events
        if (class_exists('Pterodactyl\\Events\\Server\\Created')) {
            Event::listen('Pterodactyl\\Events\\Server\\Created', ServerCreatedListener::class);
        }

        if (class_exists('Pterodactyl\\Events\\Server\\Deleted')) {
            Event::listen('Pterodactyl\\Events\\Server\\Deleted', ServerDeletedListener::class);
        }

        // Allocation model observer for port changes
        if (class_exists(Allocation::class)) {
            Allocation::observe(AllocationObserver::class);
        }
    }
}
