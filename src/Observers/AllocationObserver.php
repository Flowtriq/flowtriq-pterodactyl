<?php

namespace Flowtriq\Pterodactyl\Observers;

use Flowtriq\Pterodactyl\Jobs\SyncServicePortsJob;

class AllocationObserver
{
    /**
     * When an allocation is assigned to or removed from a server,
     * re-sync service ports for that node.
     */
    public function updated($allocation): void
    {
        // Only trigger if server_id changed (port assigned/unassigned)
        if (!$allocation->isDirty('server_id')) {
            return;
        }

        SyncServicePortsJob::dispatch($allocation->node_id)->delay(5);
    }

    public function created($allocation): void
    {
        if ($allocation->server_id) {
            SyncServicePortsJob::dispatch($allocation->node_id)->delay(5);
        }
    }

    public function deleted($allocation): void
    {
        if ($allocation->server_id) {
            SyncServicePortsJob::dispatch($allocation->node_id)->delay(5);
        }
    }
}
