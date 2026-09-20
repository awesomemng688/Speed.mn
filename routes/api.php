<?php

use App\Http\Controllers\Api\ServerController;
use Illuminate\Support\Facades\Route;

Route::get('/servers', [ServerController::class, 'index']);
Route::get('/servers/{server}', [ServerController::class, 'show']);
Route::get('/servers/{server}/monitoring', [ServerController::class, 'monitoring']);
