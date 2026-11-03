<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServerRatingController extends Controller
{
    public function store(Request $request, Server $server): RedirectResponse
    {
        abort_unless($server->enabled, 404);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:300'],
        ]);
        ServerRating::query()->updateOrCreate(
            ['server_id' => $server->id, 'user_id' => $request->user()->id],
            $data,
        );
        Cache::forget('weekly-best-server');

        return back()->with('status', 'Үнэлгээ хадгалагдлаа.');
    }
}
