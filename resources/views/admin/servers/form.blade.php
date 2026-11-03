<form class="panel admin-form admin-server-form" method="POST" action="{{ $formAction }}">@csrf @if($formMethod !== 'POST') @method($formMethod) @endif
    @if($errors->any())<div class="admin-alert error" role="alert"><strong>Оруулсан мэдээллээ шалгана уу.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="admin-form-intro"><span class="admin-form-step">1</span><div><h2>Үндсэн мэдээлэл</h2><p class="muted">Серверийн нэр болон тоглоомын төрлийг сонгоно уу.</p></div></div>
    <div class="form-grid">
        <label>Серверийн нэр<input name="name" value="{{ old('name', $server->name) }}" required maxlength="255" placeholder="Жишээ: Speed.mn CS2 #1"></label>
        <label>Тоглоом<select name="game" required>@foreach(['cs2' => 'Counter-Strike 2', 'cs16' => 'Counter-Strike 1.6'] as $value => $label)<option value="{{ $value }}" @selected(old('game', $server->game) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>CS 1.6 ангилал<select name="category"><option value="">Сонгохгүй</option>@foreach(['public-1' => 'Public 1', 'public-2' => 'Public 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'] as $value => $label)<option value="{{ $value }}" @selected(old('category', $server->category) === $value)>{{ $label }}</option>@endforeach</select></label>
    </div>
    <div class="admin-form-intro"><span class="admin-form-step">2</span><div><h2>Холболтын мэдээлэл</h2><p class="muted">Серверийн IPv4 эсвэл IPv6 хаяг болон query портыг оруулна уу.</p></div></div>
    <div class="form-grid">
        <label>IP хаяг<input name="ip" value="{{ old('ip', $server->ip) }}" required maxlength="45" placeholder="203.0.113.10"></label>
        <label>Порт<input type="number" name="port" min="1" max="65535" value="{{ old('port', $server->port) }}" required placeholder="27015"></label>
        <label>Query төрөл<input name="query_type" value="{{ old('query_type', $server->query_type ?: 'a2s') }}" required maxlength="50" placeholder="a2s"></label>
    </div>
    <div class="admin-form-intro"><span class="admin-form-step">3</span><div><h2>Нэмэлт мэдээлэл</h2><p class="muted">Нийтийн жагсаалт дээр харуулах бүс болон багтаамжийг тохируулна.</p></div></div>
    <div class="form-grid">
        <label>Бүс нутаг<input name="region" value="{{ old('region', $server->region) }}" maxlength="255" placeholder="Улаанбаатар"></label>
        <label>Улсын код<input name="country" maxlength="2" value="{{ old('country', $server->country) }}" placeholder="MN"></label>
        <label>Тоглогчийн дээд тоо<input type="number" name="max_players" min="0" max="65535" value="{{ old('max_players', $server->max_players) }}" required></label>
    </div>
    <label class="checkbox admin-server-visibility"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $server->exists ? $server->enabled : true))> Серверийг нийтэд харагдуулах</label>
    <div class="form-actions"><a class="button button-ghost" href="{{ route('admin.servers.index') }}">Буцах</a><button class="button button-primary" type="submit">{{ $server->exists ? 'Өөрчлөлт хадгалах' : '＋ Сервер бүртгэх' }}</button></div>
</form>
