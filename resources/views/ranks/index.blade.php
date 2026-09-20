@extends('layouts.app')

@section('title', $source['label'].' Rank — Speed.mn')
@section('body_class', 'rank-page')

@section('content')
    @php
        $rankValue = fn ($player, string $name, $fallback = 0) => isset($player->{$name}) ? $player->{$name} : $fallback;
        $nameValue = fn ($player) => ($player->Nick ?? null) ?: ($player->name ?: ($player->Player ?? 'Unknown player'));
        $avatarValue = fn ($player) => $player->Avatar ?? null;
        $weaponColumns = ['Knife', 'Glock', 'USP', 'Deagle', 'P228', 'Elite', 'Fiveseven', 'AWP', 'AK47', 'M4A1', 'AUG', 'SG552', 'Scout', 'G3SG1', 'SG550', 'Galil', 'Famas', 'MP5', 'M249', 'Grenade', 'Glock18', 'M3', 'XM1014', 'MAC10', 'UMP45', 'P90', 'TMP', 'MP5 Navy', 'HE Grenade', 'Flashbang', 'Smoke Grenade', 'C4'];
    @endphp

    <section class="page-head rank-head">
        <div class="shell">
            <p class="eyebrow">SPEED.MN RANKING</p>
            <h1>{{ $source['label'] }} rank</h1>
            <p class="muted">Live player statistics from the {{ $source['label'] }} server database.</p>
            <div class="rank-tabs">
                @foreach (['public-1' => 'Public 1', 'public-2' => 'Public 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'] as $value => $label)
                    <a class="{{ $category === $value ? 'active' : '' }}" href="{{ route('ranks.index', $value) }}">{{ $label }}</a>
                @endforeach
            </div>
            <form class="rank-search" method="GET" action="{{ route('ranks.index', $category) }}">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Тоглогч хайх..." autocomplete="off">
                <button class="button button-primary" type="submit">Хайх</button>
                @if (request('search'))
                    <a class="text-link" href="{{ route('ranks.index', $category) }}">Цэвэрлэх</a>
                @endif
            </form>
        </div>
    </section>

    <section class="shell section">
        <div class="rank-table-wrap">
            <table class="rank-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Player</th>
                        @if ($source['type'] === 'public')
                            <th>XP</th><th>Kills</th><th>Deaths</th><th>Headshots</th><th>Skill</th><th>Rank</th>
                        @else
                            <th>Knife kills</th><th>All kills</th><th>Most used weapon</th><th>Played time</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($players as $player)
                        @php($position = $players->firstItem() + $loop->index)
                        <tr>
                            <td class="rank-position">{{ $position }}</td>
                            <td class="rank-player">
                                @if ($avatarValue($player))
                                    <img src="{{ $avatarValue($player) }}" alt="" loading="lazy">
                                @else
                                    <span class="rank-avatar">{{ strtoupper(substr($nameValue($player), 0, 1)) }}</span>
                                @endif
                                <strong>{{ $nameValue($player) }}</strong>
                            </td>
                            @if ($source['type'] === 'public')
                                <td class="rank-xp">{{ number_format((int) $rankValue($player, 'XP', $rankValue($player, 'points'))) }}</td>
                                <td>{{ number_format((int) $rankValue($player, 'kills')) }}</td>
                                <td>{{ number_format((int) $rankValue($player, 'deaths')) }}</td>
                                <td>{{ number_format((int) $rankValue($player, 'headshots')) }}</td>
                                <td>{{ $rankValue($player, 'Skill Range', $rankValue($player, 'points', '—')) }}</td>
                                <td>{{ (int) $rankValue($player, 'Rank') > 0 ? $rankValue($player, 'Rank') : $position }}</td>
                            @else
                                <td class="rank-xp">{{ number_format((int) $rankValue($player, 'Knife')) }}</td>
                                @php
                                    $weaponKills = collect($weaponColumns)->sum(fn ($weapon) => (int) $rankValue($player, $weapon));
                                    $weaponValues = collect($weaponColumns)->mapWithKeys(fn ($weapon) => [$weapon => (int) $rankValue($player, $weapon)])->sortDesc();
                                    $topWeapon = $weaponValues->keys()->first(fn ($weapon) => $weaponValues[$weapon] > 0);
                                @endphp
                                <td>{{ number_format($weaponKills ?: (int) $rankValue($player, 'kills')) }}</td>
                                <td>{{ $topWeapon ?: ($player->weapon ?: '—') }}</td>
                                <td>{{ number_format((int) $rankValue($player, 'Played Time', $rankValue($player, 'playtime'))) }} min</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">No ranking data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $players->links() }}</div>
    </section>
@endsection
