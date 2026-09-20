<?php

use App\Http\Controllers\Auth\SteamController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\Admin\ServerController as AdminServerController;
use App\Models\Server;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $servers = Server::where('enabled', true)->with('latestStatus')->orderBy('game')->get();
    $stats = [
        'servers' => $servers->count(),
        'online' => $servers->filter(fn ($server) => $server->latestStatus?->online)->count(),
        'players' => $servers->sum(fn ($server) => $server->latestStatus?->players ?? 0),
    ];
    return view('home', compact('servers', 'stats'));
})->name('home');
Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
Route::get('/servers/{game}', [ServerController::class, 'index'])->where('game', 'cs2|cs16')->name('servers.game');
Route::get('/server/{server}', [ServerController::class, 'show'])->name('servers.show');
Route::get('/auth/steam', [SteamController::class, 'redirect'])->name('steam.login');
Route::get('/auth/steam/callback', [SteamController::class, 'callback'])->name('steam.callback');
Route::get('/auth/logout', [SteamController::class, 'logout'])->middleware('auth')->name('steam.logout');
Route::get('/profile', fn () => view('profile'))->middleware('auth')->name('profile');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('servers', AdminServerController::class)->except(['show']);
    Route::patch('servers/{server}/toggle', [AdminServerController::class, 'toggle'])->name('servers.toggle');
    Route::post('servers/{server}/test', [AdminServerController::class, 'test'])->name('servers.test');
});
