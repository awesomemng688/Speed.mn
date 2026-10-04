<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerFavoriteController extends Controller
{
    public function store(Request $request, Server $server): RedirectResponse
    {
        abort_unless($server->enabled, 404);
        $request->user()->favoriteServers()->syncWithoutDetaching([$server->id]);

        return back()->with('status', 'Серверийг дуртай жагсаалтад нэмлээ.');
    }

    public function destroy(Request $request, Server $server): RedirectResponse
    {
        $request->user()->favoriteServers()->detach($server->id);

        return back()->with('status', 'Серверийг дуртай жагсаалтаас хаслаа.');
    }
}