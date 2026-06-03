<?php

namespace Flowtriq\Pterodactyl\Console;

use Flowtriq\Pterodactyl\Services\ServicePortSyncService;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'flowtriq:sync {--node= : Specific Pterodactyl node ID to sync}';
    protected $description = 'Force sync service ports to Flowtriq';

    public function handle(ServicePortSyncService $sync): int
    {
        $nodeId = $this->option('node');

        if ($nodeId) {
            $this->info('Syncing node #' . $nodeId . '...');
            $sync->syncNode((int) $nodeId);
        } else {
            $this->info('Syncing all nodes...');
            $sync->syncAll();
        }

        $this->info('Done.');

        return 0;
    }
}
