@extends('layouts.app')
@section('title', 'Demo videos — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><h1>Demo videos</h1><p class="muted">CS2 MP4 бичлэгүүдийг private сангаас үзнэ.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
    <div class="panel admin-form-panel">
        <h2>MP4 нэмэх</h2>
        <form class="admin-form" method="POST" action="{{ route('admin.demos.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <label>Тоглолтын нэр<input name="title" value="{{ old('title') }}" maxlength="180" required placeholder="Team A vs Team B"></label>
                <label>Map<input name="map" value="{{ old('map') }}" maxlength="64" placeholder="de_dust2"></label>
                <label>Тоглосон огноо<input name="recorded_at" type="datetime-local" value="{{ old('recorded_at') }}"></label>
                <label>MP4 видео<input name="video" type="file" accept="video/mp4,.mp4"><small>Вэб дээр шууд тоглоно, дээд хэмжээ 1 GB.</small></label>
                <label>CS2 demo (.dem)<input name="demo" type="file" accept=".dem,application/octet-stream"><small>500 MB хүртэл. Queue parser round, kill, player statistics гаргана; 3D replay биш.</small></label>
            </div>
            @error('video')<p class="field-error">{{ $message }}</p>@enderror
            @error('demo')<p class="field-error">{{ $message }}</p>@enderror
            @error('title')<p class="field-error">{{ $message }}</p>@enderror
            @error('map')<p class="field-error">{{ $message }}</p>@enderror
            @error('recorded_at')<p class="field-error">{{ $message }}</p>@enderror
            <button class="button button-primary" type="submit">MP4 хадгалах</button>
        </form>
    </div>
    <div class="panel admin-form-panel">
        <h2>MatchZy серверээс import</h2>
        @if(!$sourceAvailable)
            <p class="admin-alert error">MatchZy demo хавтас уншигдахгүй байна. `CS2_DEMO_SOURCE_DIR` болон www-data эрхийг шалга.</p>
        @elseif($availableDemFiles->isEmpty())
            <p class="muted">Import хийх боломжтой шинэ `.dem` файл алга. 500 MiB-аас бага файлууд харагдана.</p>
        @else
            <div class="demo-source-list">@foreach($availableDemFiles as $sourceFile)<form method="POST" action="{{ route('admin.demos.import-matchzy') }}" class="demo-source-row">@csrf<input type="hidden" name="filename" value="{{ $sourceFile['name'] }}"><span><strong>{{ $sourceFile['name'] }}</strong><small>{{ number_format($sourceFile['size'] / 1048576, 1) }} MiB · {{ date('Y-m-d H:i', $sourceFile['modified_at']) }}</small></span>@if($sourceFile['imported'])<span class="status online">Imported</span>@else<button class="button button-primary" type="submit">Import & parse</button>@endif</form>@endforeach</div>
        @endif
        @error('filename')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="demo-video-list">
        @forelse($demos as $demo)
            <article class="panel demo-video-item">
                <div class="panel-heading"><div><h2>{{ $demo->title }}</h2><small class="muted">{{ $demo->map ?: 'Map тодорхойгүй' }} · {{ $demo->recorded_at?->format('Y-m-d H:i') ?: 'Огноо тодорхойгүй' }} · {{ $demo->uploadedBy?->name ?: 'Админ' }}</small></div>
                    <form method="POST" action="{{ route('admin.demos.destroy', $demo) }}" onsubmit="return confirm('Энэ demo-г устгах уу?')">@csrf @method('DELETE')<button class="text-button danger" type="submit">Устгах</button></form>
                </div>
                @if($demo->media_type === 'mp4')
                    <video controls preload="metadata" playsinline src="{{ route('admin.demos.stream', $demo) }}">Таны browser MP4 тоглуулах боломжгүй байна.</video>
                @elseif($demo->processing_status === 'queued' || $demo->processing_status === 'processing')
                    <p class="demo-processing">Demo статистик боловсруулж байна…</p>
                @elseif($demo->processing_status === 'failed')
                    <div class="admin-alert error" role="alert">Demo parse амжилтгүй: {{ $demo->processing_error ?: 'Алдаа бүртгэгдсэн.' }}</div>
                @elseif($demo->analysis_data)
                    @php($analysis = $demo->analysis_data)
                    @php($matchInfo = $analysis['match_info'] ?? [])
                    @php($stats = $analysis['statistics'] ?? [])
                    <div class="demo-analysis-summary">
                        <div><small>WINNER</small><strong>{{ $matchInfo['match_winner'] ?? '—' }}</strong></div>
                        <div><small>SCORE</small><strong>{{ $matchInfo['final_score']['team1'] ?? '—' }} : {{ $matchInfo['final_score']['team2'] ?? '—' }}</strong></div>
                        <div><small>ROUNDS</small><strong>{{ $stats['total_rounds'] ?? count($analysis['rounds'] ?? []) }}</strong></div>
                        <div><small>KILLS</small><strong>{{ $stats['total_kills'] ?? '—' }}</strong></div>
                        <div><small>DURATION</small><strong>{{ $stats['match_duration_minutes'] ?? '—' }} мин</strong></div>
                    </div>
                    <div class="demo-analysis-teams"><span>{{ $matchInfo['team1']['name'] ?? 'Team 1' }} · {{ $matchInfo['team1']['players'] ? implode(', ', $matchInfo['team1']['players']) : '—' }}</span><span>{{ $matchInfo['team2']['name'] ?? 'Team 2' }} · {{ $matchInfo['team2']['players'] ? implode(', ', $matchInfo['team2']['players']) : '—' }}</span></div>
                    @if(!empty($analysis['player_statistics']))<div class="demo-analysis-players"><h3>Тоглогчдын үзүүлэлт</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Тоглогч</th><th>Баг</th><th>K/D</th><th>HS</th><th>K/D ratio</th><th>Weapon</th></tr></thead><tbody>@foreach($analysis['player_statistics'] as $playerName => $playerStats)<tr><td>{{ $playerName }}</td><td>{{ $playerStats['team'] ?? '—' }}</td><td>{{ $playerStats['kills'] ?? 0 }}/{{ $playerStats['deaths'] ?? 0 }}</td><td>{{ $playerStats['headshots'] ?? 0 }}</td><td>{{ $playerStats['kd_ratio'] ?? '—' }}</td><td>{{ $playerStats['favorite_weapon'] ?? '—' }}</td></tr>@endforeach</tbody></table></div></div>@endif
                    <div class="demo-analysis-rounds"><h3>Rounds</h3>@foreach(array_slice($analysis['rounds'] ?? [], 0, 30) as $round)<details><summary>Round {{ $round['round_number'] ?? $loop->iteration }} · {{ $round['round_winner'] ?? '—' }} · {{ count($round['kills'] ?? []) }} kill</summary><div class="demo-round-kills">@forelse($round['kills'] ?? [] as $kill)<span>{{ $kill['killer'] ?? '—' }} → {{ $kill['victim'] ?? '—' }} · {{ $kill['weapon'] ?? '—' }}{{ !empty($kill['headshot']) ? ' · HS' : '' }}</span>@empty<span class="muted">Kill бүртгэгдээгүй.</span>@endforelse</div></details>@endforeach</div>
                @endif
            </article>
        @empty
            <p class="empty">Одоогоор MP4 demo алга.</p>
        @endforelse
    </div>
    {{ $demos->links() }}
</section>
<style>
    .admin-form-panel{padding:22px;margin-bottom:18px}.admin-form-panel h2{margin:0 0 16px;font-size:18px}.demo-video-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:14px}.demo-video-item{padding:16px;min-width:0}.demo-video-item .panel-heading{align-items:flex-start;gap:12px}.demo-video-item h2{margin:0;font-size:16px}.demo-video-item video{display:block;width:100%;max-height:440px;margin-top:14px;border-radius:8px;background:#05070b}.field-error{color:#ff8998;font-size:12px}.demo-processing{padding:18px;border:1px solid var(--line);border-radius:8px;color:var(--muted)}.demo-analysis-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;margin-top:16px}.demo-analysis-summary>div{padding:10px;border:1px solid var(--line);border-radius:7px;background:#0b0f17}.demo-analysis-summary small,.demo-analysis-summary strong{display:block}.demo-analysis-summary small{color:var(--muted);font-size:9px}.demo-analysis-summary strong{margin-top:4px;font-size:14px}.demo-analysis-teams{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;color:var(--muted);font-size:12px}.demo-analysis-teams span{padding:8px 10px;border:1px solid var(--line);border-radius:7px}.demo-analysis-players,.demo-analysis-rounds{margin-top:18px}.demo-analysis-players h3,.demo-analysis-rounds h3{font-size:14px}.demo-analysis-rounds details{border-top:1px solid var(--line);padding:9px 2px;font-size:12px}.demo-analysis-rounds summary{cursor:pointer}.demo-round-kills{display:grid;gap:5px;padding:9px 12px;color:var(--muted)}.demo-source-list{display:grid;gap:8px}.demo-source-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid var(--line)}.demo-source-row span:first-child{min-width:0;overflow-wrap:anywhere}.demo-source-row strong,.demo-source-row small{display:block}.demo-source-row small{color:var(--muted);margin-top:3px}.demo-source-row .button{flex-shrink:0}@media(max-width:640px){.demo-source-row{align-items:flex-start;flex-direction:column}.demo-source-row .button{width:100%}}
</style>
@endsection