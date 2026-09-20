<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RankController extends Controller
{
    private const SOURCES = [
        'public-1' => [
            'label' => 'Public 1',
            'connection' => 'rank_public_1',
            'table' => 'rank_system_new',
            'type' => 'public',
        ],
        'public-2' => [
            'label' => 'Public 2',
            'connection' => 'rank_public_2',
            'table' => 'rank_system_new',
            'type' => 'public',
        ],
        'knife-1' => [
            'label' => 'Knife 1',
            'connection' => 'rank_knife_1',
            'table' => 'weapon_kills_new',
            'type' => 'knife',
        ],
        'knife-2' => [
            'label' => 'Knife 2',
            'connection' => 'rank_knife_2',
            'table' => 'weapon_kills_new',
            'type' => 'knife',
        ],
    ];

    public function index(Request $request, string $category = 'public-1'): View
    {
        abort_unless(isset(self::SOURCES[$category]), 404);

        $source = self::SOURCES[$category];
        $connection = DB::connection($source['connection']);
        $columns = Schema::connection($source['connection'])->getColumnListing($source['table']);
        $column = static fn (string $name): ?string => in_array($name, $columns, true) ? $name : null;

        $query = $connection->table($source['table']);
        $orderColumn = $source['type'] === 'public'
            ? ($column('XP') ?: ($column('points') ?: 'id'))
            : ($column('Knife') ?: ($column('kills') ?: 'id'));

        $search = trim((string) $request->query('search', ''));
        if ($source['type'] === 'knife') {
            $query->where($column('Knife') ?: 'kills', '>', 0);
        }
        $query->when($search !== '', function ($query) use ($search, $column): void {
            $query->where($column('Nick') ?: ($column('name') ?: 'id'), 'like', '%'.$search.'%');
        });

        $players = $query
            ->orderByDesc($orderColumn)
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('ranks.index', [
            'category' => $category,
            'source' => $source,
            'players' => $players,
            'columns' => $columns,
            'column' => $column,
        ]);
    }
}
