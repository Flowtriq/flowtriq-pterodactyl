<?php

use Flowtriq\Pterodactyl\Http\Controllers\Admin\NodeController;
use Flowtriq\Pterodactyl\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

// Settings
Route::get('/settings', [SettingsController::class, 'index'])->name('admin.flowtriq.settings');
Route::post('/settings', [SettingsController::class, 'store']);
Route::post('/settings/test', [SettingsController::class, 'testConnection'])->name('admin.flowtriq.settings.test');

// Nodes
Route::get('/nodes', [NodeController::class, 'index'])->name('admin.flowtriq.nodes');
Route::get('/nodes/{nodeId}', [NodeController::class, 'show'])->name('admin.flowtriq.nodes.show');
Route::post('/nodes/{nodeId}/link', [NodeController::class, 'link'])->name('admin.flowtriq.nodes.link');
Route::post('/nodes/{nodeId}/unlink', [NodeController::class, 'unlink'])->name('admin.flowtriq.nodes.unlink');
Route::post('/nodes/{nodeId}/sync', [NodeController::class, 'sync'])->name('admin.flowtriq.nodes.sync');

// Central mode
Route::post('/nodes/central/link', [NodeController::class, 'linkCentral'])->name('admin.flowtriq.nodes.central.link');
