<?php

use Flowtriq\Pterodactyl\Http\Controllers\Server\DdosController;
use Illuminate\Support\Facades\Route;

// Server owner DDoS status (AJAX)
Route::get('/servers/{server}/ddos', [DdosController::class, 'status'])->name('api.flowtriq.server.ddos');
