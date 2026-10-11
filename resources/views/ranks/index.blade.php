@extends('layouts.app')

@section('title', $source['label'].' чансаа — Speed.mn')
@section('description', $source['label'].' серверийн тоглогчдын чансаа, оноо болон тоглолтын статистикийг хараарай.')
@section('body_class', 'rank-page')

@section('content')
    @php
        $rankValue = fn ($player, string $name, $fallback = 0) => isset($player->{$name}) ? $player->{$name} : $fallback;
        $nameValue = fn ($player) => ($player->Nick ?? null) ?: ($player->name ?: ($player->Player ?? 'Тоглогч'));
        $avatarValue = function ($player) use ($avatars) {
            $storedAvatar = trim((string) ($player->Avatar ?? ''));
            if ($storedAvatar !== '') {
                return $storedAvatar;
            }

            $steamId = (string) ($player->steamid ?? $player->{'Steam ID'} ?? $player->Steam ?? '');
            return $avatars[$steamId] ?? $avatars[strtolower(trim((string) ($player->Nick ?? $player->name ?? $player->Player ?? '')))] ?? null;
        };
        $rankImage = [
            'public-1' => 'img/hero/pubrank1.jfif',
            'public-2' => 'img/hero/pubrank2.jfif',
            'knife-1' => 'img/hero/kniferank1.jfif',
            'knife-2' => 'img/hero/kniferank2.jfif',
        ][$category];
        $weaponColumns = ['Knife', 'Glock', 'USP', 'Deagle', 'P228', 'Elite', 'Fiveseven', 'AWP', 'AK47', 'M4A1', 'AUG', 'SG552', 'Scout', 'G3SG1', 'SG550', 'Galil', 'Famas', 'MP5', 'M249', 'Grenade', 'Glock18', 'M3', 'XM1014', 'MAC10', 'UMP45', 'P90', 'TMP', 'MP5 Navy', 'HE Grenade', 'Flashbang', 'Smoke Grenade', 'C4'];
    @endphp

    <section class="page-head rank-head">
        <img class="rank-background" src="{{ asset($rankImage) }}" alt="" aria-hidden="true">
        <div class="rank-background-overlay" aria-hidden="true"></div>
        <div class="shell rank-head-content">
            <div class="rank-head-grid">
                <div>
                    <p class="eyebrow">SPEED.MN ЧАНСАА</p>
                    <h1>{{ $source['label'] }} чансаа</h1>
                    <p class="muted">{{ $source['label'] }} серверийн тоглогчдын бодит статистик.</p>
                </div>
            </div>
            <div class="rank-tabs">
                @foreach (['public-1' => 'Нийтийн 1', 'public-2' => 'Нийтийн 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'] as $value => $label)
                    <a class="{{ $category === $value ? 'active' : '' }}" href="{{ route('ranks.index', $value) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="shell section">
        <div class="rank-toolbar">
            <form class="rank-search" method="GET" action="{{ route('ranks.index', $category) }}">
                <span aria-hidden="true">⌕</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Тоглогч хайх..." autocomplete="off">
                <button class="button button-primary" type="submit">Хайх</button>
                @if (request('search'))
                    <a class="text-link" href="{{ route('ranks.index', $category) }}">Цэвэрлэх</a>
                @endif
            </form>
            <span class="rank-count">{{ $players->total() }} тоглогч</span>
        </div>
        <div class="rank-table-wrap">
            <table class="rank-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Тоглогч</th>
                        @if ($source['type'] === 'public')
                            <th>XP</th><th>Аллага</th><th class="rank-secondary">Үхэл</th><th class="rank-secondary">Толгой оносон</th><th class="rank-secondary">Ур чадвар</th><th class="rank-secondary">Байр</th>
                        @else
                            <th>Хутганы аллага</th><th>Нийт аллага</th><th class="rank-secondary">Түгээмэл зэвсэг</th><th class="rank-secondary">Тоглосон хугацаа</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($players as $player)
                        @php
                            $position = $players->firstItem() + $loop->index;
                            $weaponKills = null;
                            $topWeapon = null;
                            if ($source['type'] !== 'public') {
                                $weaponKills = collect($weaponColumns)->sum(fn ($weapon) => (int) $rankValue($player, $weapon));
                                $weaponValues = collect($weaponColumns)->mapWithKeys(fn ($weapon) => [$weapon => (int) $rankValue($player, $weapon)])->sortDesc();
                                $topWeapon = $weaponValues->keys()->first(fn ($weapon) => $weaponValues[$weapon] > 0);
                            }
                        @endphp
                        <tr>
                            <td class="rank-position">{{ $position }}</td>
                            <td class="rank-player">
                                @if ($avatarValue($player))
                                    <img src="{{ $avatarValue($player) }}" alt="" loading="lazy">
                                @else
                                    <span class="rank-avatar">{{ strtoupper(substr($nameValue($player), 0, 1)) }}</span>
                                @endif
                                <strong>{{ $nameValue($player) }}</strong>
                                <details class="rank-mobile-details">
                                    <summary>Дэлгэрэнгүй</summary>
                                    @if ($source['type'] === 'public')
                                        <dl>
                                            <div><dt>Үхэл</dt><dd>{{ number_format((int) $rankValue($player, 'deaths')) }}</dd></div>
                                            <div><dt>Толгой оносон</dt><dd>{{ number_format((int) $rankValue($player, 'headshots')) }}</dd></div>
                                            <div><dt>Ур чадвар</dt><dd>{{ $rankValue($player, 'Skill Range', $rankValue($player, 'points', '—')) }}</dd></div>
                                            <div><dt>Байр</dt><dd>{{ (int) $rankValue($player, 'Rank') > 0 ? $rankValue($player, 'Rank') : $position }}</dd></div>
                                        </dl>
                                    @else
                                        <dl>
                                            <div><dt>Түгээмэл зэвсэг</dt><dd>{{ $topWeapon ?: ($player->weapon ?: '—') }}</dd></div>
                                            <div><dt>Тоглосон хугацаа</dt><dd>{{ number_format((int) $rankValue($player, 'Played Time', $rankValue($player, 'playtime'))) }} мин</dd></div>
                                        </dl>
                                    @endif
                                </details>
                            </td>
                            @if ($source['type'] === 'public')
                                <td class="rank-xp">{{ number_format((int) $rankValue($player, 'XP', $rankValue($player, 'points'))) }}</td>
                                <td>{{ number_format((int) $rankValue($player, 'kills')) }}</td>
                                <td class="rank-secondary">{{ number_format((int) $rankValue($player, 'deaths')) }}</td>
                                <td class="rank-secondary">{{ number_format((int) $rankValue($player, 'headshots')) }}</td>
                                <td class="rank-secondary">{{ $rankValue($player, 'Skill Range', $rankValue($player, 'points', '—')) }}</td>
                                <td class="rank-secondary">{{ (int) $rankValue($player, 'Rank') > 0 ? $rankValue($player, 'Rank') : $position }}</td>
                            @else
                                <td class="rank-xp">{{ number_format((int) $rankValue($player, 'Knife')) }}</td>
                                <td>{{ number_format($weaponKills ?: (int) $rankValue($player, 'kills')) }}</td>
                                <td class="rank-secondary">{{ $topWeapon ?: ($player->weapon ?: '—') }}</td>
                                <td class="rank-secondary">{{ number_format((int) $rankValue($player, 'Played Time', $rankValue($player, 'playtime'))) }} мин</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">Чансааны мэдээлэл олдсонгүй.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $players->links() }}</div>
    </section>
@endsection
