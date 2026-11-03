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
use App\Models\ServerRating;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RankController;
use App\Http\Controllers\SkinBridgeController;
use App\Http\Controllers\ServerFavoriteController;
use App\Http\Controllers\UserNotificationController;
use App\Models\ServerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
    $weeklyWinnerStats = Cache::remember('weekly-best-server', now()->addMinutes(15), fn () => ServerRating::query()
        ->where('created_at', '>=', now()->subDays(7))
        ->selectRaw('server_id, AVG(rating) AS average_rating, COUNT(*) AS rating_count')
        ->groupBy('server_id')
        ->havingRaw('COUNT(*) >= 3')
        ->orderByDesc('average_rating')
        ->orderByDesc('rating_count')
        ->first());
    $weeklyBest = $weeklyWinnerStats
        ? Server::query()->where('enabled', true)->with('latestStatus')->find($weeklyWinnerStats->server_id)
        : null;

    return view('home', compact('servers', 'stats', 'weeklyBest', 'weeklyWinnerStats'));
})->name('home');
Route::get('/sitemap.xml', function () {
    $urls = collect([url('/'), route('servers.index'), route('servers.game', 'cs2'), route('servers.game', 'cs16'), url('/rank')])
        ->merge(Server::query()->where('enabled', true)->pluck('id')->map(fn ($id) => route('servers.show', $id)));

    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .$urls->map(fn ($u) => '<url><loc>'.e($u).'</loc></url>')->implode('')
        .'</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');
Route::get('/vip', fn () => view('vip'))->name('vip');
Route::get('/shop', [\App\Http\Controllers\ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/{product}', [\App\Http\Controllers\ShopController::class, 'show'])->whereNumber('product')->name('shop.show');
Route::post('/shop/orders/{order}/cancel', [\App\Http\Controllers\ShopController::class, 'cancel'])->middleware(['auth', 'throttle:20,1'])->name('shop.orders.cancel');
Route::post('/shop/orders/{order}/receipt', [\App\Http\Controllers\ShopController::class, 'receipt'])->middleware(['auth', 'throttle:10,1'])->name('shop.orders.receipt');
Route::get('/shop/orders/{order}/receipt', [\App\Http\Controllers\ShopController::class, 'showReceipt'])->middleware('auth')->name('shop.orders.receipt.show');
Route::post('/shop/{product}/order', [\App\Http\Controllers\ShopController::class, 'order'])->middleware(['auth', 'throttle:10,1'])->name('shop.order');
Route::get('/news', [\App\Http\Controllers\PostController::class, 'index'])->name('news.index');
Route::get('/news/{post}', [\App\Http\Controllers\PostController::class, 'show'])->name('news.show');
Route::post('/news/{post}/register', [\App\Http\Controllers\TournamentRegistrationController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('tournaments.register');
Route::post('/servers/{server}/rating', [\App\Http\Controllers\ServerRatingController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('servers.rating');
Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
Route::get('/servers/{game}', [ServerController::class, 'index'])->where('game', 'cs2|cs16')->name('servers.game');
Route::get('/server/{server}', [ServerController::class, 'show'])->name('servers.show');
Route::get('/rank/{category?}', [RankController::class, 'index'])
    ->where('category', 'public-1|public-2|knife-1|knife-2')
    ->name('ranks.index');
Route::get('/rank/{category}/player/{player}', [RankController::class, 'player'])
    ->where('category', 'public-1|public-2|knife-1|knife-2')
    ->where('player', '[^/]+')
    ->name('ranks.player');
Route::get('/auth/steam', [SteamController::class, 'redirect'])->middleware('throttle:10,1')->name('steam.login');
Route::get('/login', [SteamController::class, 'redirect'])->middleware('throttle:10,1')->name('login');
Route::get('/auth/steam/callback', [SteamController::class, 'callback'])->middleware('throttle:20,1')->name('steam.callback');
Route::get('/auth/logout', [SteamController::class, 'logout'])->middleware('auth')->name('steam.logout');
Route::get('/skins/bridge', SkinBridgeController::class)->middleware('auth')->name('skins.bridge');
Route::get('/profile', [ProfileController::class, 'show'])->middleware('auth')->name('profile');
Route::get('/submit-server', [\App\Http\Controllers\ServerSubmissionController::class, 'create'])->middleware('auth')->name('submissions.create');
Route::post('/submit-server', [\App\Http\Controllers\ServerSubmissionController::class, 'store'])->middleware(['auth', 'throttle:5,1'])->name('submissions.store');
Route::post('/servers/{server}/favorite', [ServerFavoriteController::class, 'store'])->middleware(['auth', 'throttle:60,1'])->name('servers.favorite');
Route::delete('/servers/{server}/favorite', [ServerFavoriteController::class, 'destroy'])->middleware(['auth', 'throttle:60,1'])->name('servers.unfavorite');
Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'read'])->middleware(['auth', 'throttle:60,1'])->name('notifications.read');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::resource('posts', \App\Http\Controllers\Admin\PostController::class)->except(['show']);
    Route::get('shop/export', [\App\Http\Controllers\Admin\ShopController::class, 'export'])->name('shop.export');
    Route::get('shop/products/{product}/edit', [\App\Http\Controllers\Admin\ShopController::class, 'editProduct'])->name('shop.products.edit');
    Route::put('shop/products/{product}', [\App\Http\Controllers\Admin\ShopController::class, 'updateProduct'])->name('shop.products.update');
    Route::get('shop', [\App\Http\Controllers\Admin\ShopController::class, 'index'])->name('shop.index');
    Route::put('shop/bank', [\App\Http\Controllers\Admin\ShopController::class, 'bankInfo'])->name('shop.bank');
    Route::post('shop/products', [\App\Http\Controllers\Admin\ShopController::class, 'storeProduct'])->name('shop.products.store');
    Route::patch('shop/products/{product}', [\App\Http\Controllers\Admin\ShopController::class, 'toggleProduct'])->name('shop.products.toggle');
    Route::delete('shop/products/{product}', [\App\Http\Controllers\Admin\ShopController::class, 'destroyProduct'])->name('shop.products.destroy');
    Route::post('shop/orders/{order}/approve', [\App\Http\Controllers\Admin\ShopController::class, 'approve'])->name('shop.orders.approve');
    Route::post('shop/orders/{order}/cancel', [\App\Http\Controllers\Admin\ShopController::class, 'cancel'])->name('shop.orders.cancel');
    Route::get('backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [\App\Http\Controllers\Admin\BackupController::class, 'run'])->name('backups.run');
    Route::get('backups/{name}', [\App\Http\Controllers\Admin\BackupController::class, 'download'])->name('backups.download');
    Route::get('vip/export', [\App\Http\Controllers\Admin\VipController::class, 'export'])->name('vip.export');
    Route::get('vip', [\App\Http\Controllers\Admin\VipController::class, 'index'])->name('vip.index');
    Route::post('vip', [\App\Http\Controllers\Admin\VipController::class, 'store'])->name('vip.store');
    Route::delete('vip/{member}', [\App\Http\Controllers\Admin\VipController::class, 'destroy'])->name('vip.destroy');
    Route::get('ranks', [\App\Http\Controllers\Admin\RankAdminController::class, 'index'])->name('ranks.index');
    Route::post('ranks/{category}/reset', [\App\Http\Controllers\Admin\RankAdminController::class, 'reset'])->where('category', 'public-1|public-2|knife-1|knife-2')->name('ranks.reset');
    Route::get('submissions', [\App\Http\Controllers\Admin\ServerSubmissionController::class, 'index'])->name('submissions.index');
    Route::post('submissions/{submission}/approve', [\App\Http\Controllers\Admin\ServerSubmissionController::class, 'approve'])->name('submissions.approve');
    Route::post('submissions/{submission}/reject', [\App\Http\Controllers\Admin\ServerSubmissionController::class, 'reject'])->name('submissions.reject');
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
    Route::post('demos/import-matchzy', [AdminDemoVideoController::class, 'importFromMatchZy'])->name('demos.import-matchzy');
    Route::get('demos/{demo}/video', [AdminDemoVideoController::class, 'stream'])->name('demos.stream');
    Route::delete('demos/{demo}', [AdminDemoVideoController::class, 'destroy'])->name('demos.destroy');
});
