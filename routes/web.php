<?php

use App\Http\Controllers\Auth\SteamController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ServerController as AdminServerController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\FailedJobController as AdminFailedJobController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\TwoFactorController as AdminTwoFactorController;
use App\Http\Controllers\Admin\DemoVideoController as AdminDemoVideoController;
use App\Models\Server;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RankController;
use App\Http\Controllers\SkinBridgeController;
use App\Http\Controllers\ServerFavoriteController;
use App\Http\Controllers\UserNotificationController;
use App\Models\ServerStatus;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    $freshSince = ServerStatus::freshSince();
    $latestStatusIds = ServerStatus::query()
        ->select('server_id')
        ->selectRaw('MAX(id) AS latest_id')
        ->groupBy('server_id');
    $totals = DB::table('servers')
        ->leftJoinSub($latestStatusIds, 'latest_status_ids', fn ($join) => $join->on('latest_status_ids.server_id', '=', 'servers.id'))
        ->leftJoin('server_statuses AS latest_statuses', 'latest_statuses.id', '=', 'latest_status_ids.latest_id')
        ->where('servers.enabled', true)
        ->selectRaw('COUNT(servers.id) AS servers')
        ->selectRaw('COALESCE(SUM(CASE WHEN latest_statuses.created_at >= ? AND latest_statuses.online = 1 THEN 1 ELSE 0 END), 0) AS online', [$freshSince])
        ->selectRaw('COALESCE(SUM(CASE WHEN latest_statuses.created_at >= ? AND latest_statuses.online = 1 THEN latest_statuses.players ELSE 0 END), 0) AS players', [$freshSince])
        ->selectRaw('COALESCE(SUM(CASE WHEN latest_statuses.id IS NOT NULL AND latest_statuses.created_at < ? THEN 1 ELSE 0 END), 0) AS stale', [$freshSince])
        ->selectRaw('COALESCE(SUM(CASE WHEN latest_statuses.id IS NULL THEN 1 ELSE 0 END), 0) AS unknown')
        ->first();
    $stats = [
        'servers' => (int) $totals->servers,
        'online' => (int) $totals->online,
        'players' => (int) $totals->players,
        'stale' => (int) $totals->stale,
        'unknown' => (int) $totals->unknown,
    ];

    $featuredForGame = static fn (string $game) => Server::query()
        ->where('enabled', true)
        ->where('game', $game)
        ->with('latestStatus')
        ->when(auth()->user(), fn ($query, $user) => $query->withExists([
            'favoritedBy as is_favorited' => fn ($favorites) => $favorites->where('users.id', $user->id),
        ]))
        ->orderByDesc(ServerStatus::query()
            ->selectRaw('CASE WHEN server_statuses.online = 1 AND server_statuses.created_at >= ? THEN 1 ELSE 0 END', [$freshSince])
            ->whereColumn('server_id', 'servers.id')
            ->orderByDesc('id')
            ->limit(1))
        ->orderBy('name')
        ->limit(3)
        ->get();
    $servers = $featuredForGame('cs2')->concat($featuredForGame('cs16'))->values();

    return view('home', compact('servers', 'stats'));
})->name('home');
Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
Route::get('/servers/{game}', [ServerController::class, 'index'])->where('game', 'cs2|cs16')->name('servers.game');
Route::get('/server/{server}', [ServerController::class, 'show'])->name('servers.show');
Route::get('/rank/{category?}', [RankController::class, 'index'])
    ->where('category', 'public-1|public-2|knife-1|knife-2')
    ->name('ranks.index');
Route::get('/auth/steam', [SteamController::class, 'redirect'])->middleware('throttle:10,1')->name('steam.login');
Route::get('/login', [SteamController::class, 'redirect'])->middleware('throttle:10,1')->name('login');
Route::get('/auth/steam/callback', [SteamController::class, 'callback'])->middleware('throttle:20,1')->name('steam.callback');
Route::get('/auth/logout', [SteamController::class, 'logout'])->middleware('auth')->name('steam.logout');
Route::get('/skins/bridge', SkinBridgeController::class)->middleware('auth')->name('skins.bridge');
Route::get('/profile', [ProfileController::class, 'show'])->middleware('auth')->name('profile');
Route::post('/servers/{server}/favorite', [ServerFavoriteController::class, 'store'])->middleware('auth')->name('servers.favorite');
Route::delete('/servers/{server}/favorite', [ServerFavoriteController::class, 'destroy'])->middleware('auth')->name('servers.unfavorite');
Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'read'])->middleware('auth')->name('notifications.read');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('two-factor/setup', [AdminTwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('two-factor/setup', [AdminTwoFactorController::class, 'confirmSetup'])->name('two-factor.confirm');
    Route::get('two-factor/challenge', [AdminTwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [AdminTwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify');
    Route::delete('two-factor', [AdminTwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::resource('servers', AdminServerController::class)->except(['show']);
    Route::patch('servers/{server}/toggle', [AdminServerController::class, 'toggle'])->name('servers.toggle');
    Route::post('servers/{server}/test', [AdminServerController::class, 'test'])->name('servers.test');
    Route::post('servers/{server}/poll', [AdminServerController::class, 'pollNow'])->name('servers.poll');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/toggle', [AdminUserController::class, 'toggle'])->name('users.toggle');
    Route::get('failed-jobs', [AdminFailedJobController::class, 'index'])->name('failed-jobs.index');
    Route::post('failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'retry'])->name('failed-jobs.retry');
    Route::delete('failed-jobs/{uuid}', [AdminFailedJobController::class, 'destroy'])->name('failed-jobs.destroy');
    Route::get('audit', [AdminAuditLogController::class, 'index'])->name('audit.index');
    Route::get('demos', [AdminDemoVideoController::class, 'index'])->name('demos.index');
    Route::post('demos', [AdminDemoVideoController::class, 'store'])->name('demos.store');
    Route::get('demos/{demo}/video', [AdminDemoVideoController::class, 'stream'])->name('demos.stream');
    Route::delete('demos/{demo}', [AdminDemoVideoController::class, 'destroy'])->name('demos.destroy');
});
