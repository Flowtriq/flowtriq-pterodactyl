<?php

namespace Flowtriq\Pterodactyl\Jobs;

use Flowtriq\Pterodactyl\Services\ServicePortSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncServicePortsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public int $pterodactylNodeId
    ) {}

    public function handle(ServicePortSyncService $sync): void
    {
        // Debounce: skip if another sync ran for this node in the last 3 seconds
        $lockKey = 'flowtriq:sync:' . $this->pterodactylNodeId;
        if (!Cache::lock($lockKey, 3)->get()) {
            return;
        }

        $sync->syncNode($this->pterodactylNodeId);
    }
}
