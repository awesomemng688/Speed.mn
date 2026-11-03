<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RankAdminController extends Controller
{
    private const SOURCES = [
        'public-1' => ['label' => 'Public 1', 'connection' => 'rank_public_1', 'table' => 'rank_system_new', 'search' => 'Nick', 'order' => 'XP'],
        'public-2' => ['label' => 'Public 2', 'connection' => 'rank_public_2', 'table' => 'rank_system_new', 'search' => 'Nick', 'order' => 'XP'],
        'knife-1' => ['label' => 'Knife 1', 'connection' => 'rank_knife_1', 'table' => 'weapon_kills_new', 'search' => 'Nick', 'order' => 'Knife'],
        'knife-2' => ['label' => 'Knife 2', 'connection' => 'rank_knife_2', 'table' => 'weapon_kills_new', 'search' => 'Nick', 'order' => 'Knife'],
    ];

    public function index(Request $request): View
    {
        $category = $request->query('category', 'public-1');
        abort_unless(isset(self::SOURCES[$category]), 404);
        $source = self::SOURCES[$category];
        $search = trim((string) $request->query('search', ''));
        $error = null;
        $players = null;

        try {
            $players = DB::connection($source['connection'])->table($source['table'])
                ->when($search !== '', fn ($q) => $q->where($source['search'], 'like', '%'.$search.'%')->orWhere('Player', 'like', '%'.$search.'%'))
                ->orderByDesc($source['order'])
                ->paginate(30)
                ->withQueryString();
        } catch (\Throwable $e) {
            $error = 'Rank өгөгдлийн сантай холбогдож чадсангүй.';
        }

        return view('admin.ranks', compact('category', 'source', 'search', 'players', 'error') + ['sources' => self::SOURCES]);
    }

    public function reset(Request $request, string $category, AdminAuditLogger $audit): RedirectResponse
    {
        abort_unless(isset(self::SOURCES[$category]), 404);
        $request->validate(['player' => ['required', 'string', 'max:100'], 'confirm' => ['required', 'in:RESET']]);
        $source = self::SOURCES[$category];
        $playerKey = (string) $request->input('player');

        $connection = DB::connection($source['connection']);
        $zero = [];
        foreach (Schema::connection($source['connection'])->getColumns($source['table']) as $col) {
            if (in_array($col['type_name'], ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint', 'double', 'float', 'decimal'], true) && $col['name'] !== 'Player') {
                $zero[$col['name']] = 0;
            }
        }

        $row = $connection->table($source['table'])->where('Player', $playerKey)->first();
        abort_if($row === null, 404);

        $connection->table($source['table'])->where('Player', $playerKey)->update($zero);
        $audit->record($request, 'rank.reset', null, ['category' => $category, 'player' => $playerKey], (string) ($row->Nick ?? $playerKey));

        return back()->with('status', ($row->Nick ?? $playerKey).' тоглогчийн статистик тэглэгдлээ.');
    }
}
