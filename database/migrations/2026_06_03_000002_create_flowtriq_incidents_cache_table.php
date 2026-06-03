<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flowtriq_incidents_cache', function (Blueprint $table) {
            $table->id();
            $table->string('flowtriq_incident_uuid', 36)->unique();
            $table->unsignedInteger('pterodactyl_node_id')->nullable()->index();
            $table->string('flowtriq_node_uuid', 36)->index();
            $table->string('attack_family', 50)->nullable();
            $table->string('severity', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('peak_pps')->default(0);
            $table->unsignedBigInteger('peak_bps')->default(0);
            $table->json('target_ports')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['pterodactyl_node_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flowtriq_incidents_cache');
    }
};
