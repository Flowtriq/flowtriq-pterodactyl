<?php

namespace Flowtriq\Pterodactyl\Listeners;

use Flowtriq\Pterodactyl\Jobs\SyncServicePortsJob;

class ServerCreatedListener
{
    public function handle($event): void
    {
        $server = $event->server ?? null;
        if (!$server || !$server->node_id) {
            return;
        }

        SyncServicePortsJob::dispatch($server->node_id)->delay(5);
    }
}
