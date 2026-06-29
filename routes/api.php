<?php

use Flowtriq\Pterodactyl\Http\Controllers\Server\DdosController;
use Illuminate\Support\Facades\Route;

// Server owner DDoS page (rendered view)
Route::get('/servers/{server}/ddos/view', [DdosController::class, 'index'])->name('flowtriq.server.ddos');

// Server owner DDoS status (AJAX)
Route::get('/servers/{server}/ddos', [DdosController::class, 'status'])->name('api.flowtriq.server.ddos');
