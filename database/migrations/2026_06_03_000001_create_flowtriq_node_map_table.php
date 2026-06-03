<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flowtriq_node_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pterodactyl_node_id')->nullable()->index();
            $table->string('flowtriq_node_uuid', 36);
            $table->string('flowtriq_api_key', 64);
            $table->string('flowtriq_workspace_uuid', 36)->nullable();
            $table->string('flowtriq_ip', 45)->nullable();
            $table->string('status', 20)->default('unknown');
            $table->unsignedBigInteger('last_pps')->default(0);
            $table->unsignedBigInteger('last_bps')->default(0);
            $table->boolean('sp_auto_sync')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flowtriq_node_map');
    }
};
