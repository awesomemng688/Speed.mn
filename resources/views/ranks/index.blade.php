@extends('layouts.app')

@section('title', $source['label'].' Rank — Speed.mn')
@section('body_class', 'rank-page')

@section('content')
    @php
        $rankValue = fn ($player, string $name, $fallback = 0) => isset($player->{$name}) ? $player->{$name} : $fallback;
        $nameValue = fn ($player) => $player->name ?: ($player->Nick ?? ($player->Player ?? 'Unknown player'));
        $avatarValue = fn ($player) => $player->Avatar ?? null;
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
                                <td>{{ $rankValue($player, 'Rank', '—') }}</td>
                            @else
                                <td class="rank-xp">{{ number_format((int) $rankValue($player, 'Knife')) }}</td>
                                <td>{{ number_format((int) $rankValue($player, 'kills')) }}</td>
                                <td>{{ $player->weapon ?: '—' }}</td>
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
