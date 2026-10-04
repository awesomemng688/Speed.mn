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

    <section class="page-head rank-head" style="position:relative;isolation:isolate;overflow:hidden;min-height:294px;background:#101827;">
        <img class="rank-background" src="{{ asset($rankImage) }}" alt="" aria-hidden="true" style="position:absolute;inset:0;width:100%;height:100%;display:block;z-index:0;object-fit:cover;object-position:center 35%;opacity:.8;pointer-events:none;">
        <div class="rank-background-overlay" aria-hidden="true" style="position:absolute;inset:0;width:100%;height:100%;z-index:1;pointer-events:none;background:linear-gradient(90deg,#080a0fe8 0%,#080a0fb8 43%,#080a0f5c 100%),linear-gradient(180deg,#080a0f55 0%,transparent 42%,#080a0fd9 100%);"></div>
        <div class="shell" style="position:relative;z-index:2;">
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
                            <th>XP</th><th>Аллага</th><th>Үхэл</th><th>Толгой оносон</th><th>Ур чадвар</th><th>Байр</th>
                        @else
                            <th>Хутганы аллага</th><th>Нийт аллага</th><th>Түгээмэл зэвсэг</th><th>Тоглосон хугацаа</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($players as $player)
                        @php
                            $position = $players->firstItem() + $loop->index;
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
                                <td>{{ number_format((int) $rankValue($player, 'Played Time', $rankValue($player, 'playtime'))) }} мин</td>
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
