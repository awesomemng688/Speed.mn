@extends('layouts.app')
@section('title', 'Сайтын тохиргоо — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>Сайтын тохиргоо</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if($errors->any())<div class="admin-alert error" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <form class="panel adm-form" method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')
        <h2>Нүүр хуудас</h2>
        <label class="adm-field">Гарчиг (эхний мөр)<input name="hero_title" value="{{ old('hero_title', $values['hero_title']) }}" maxlength="60" required></label>
        <label class="adm-field">Гарчиг (өнгөт мөр)<input name="hero_accent" value="{{ old('hero_accent', $values['hero_accent']) }}" maxlength="60" required></label>
        <label class="adm-field">Тайлбар<textarea name="hero_lead" rows="3" maxlength="300" required>{{ old('hero_lead', $values['hero_lead']) }}</textarea></label>
        <h2>Холбоос</h2>
        <label class="adm-field">Discord линк<input type="url" name="discord_url" value="{{ old('discord_url', $values['discord_url']) }}" required></label>
        <h2>Зарлал (banner)</h2>
        <label class="adm-check"><input type="checkbox" name="banner_enabled" value="1" @checked(old('banner_enabled', $values['banner_enabled']) === '1' || old('banner_enabled') === '1')> Сайтын дээд талд харуулах</label>
        <label class="adm-field">Текст<input name="banner_text" value="{{ old('banner_text', $values['banner_text']) }}" maxlength="200"></label>
        <label class="adm-field">Линк (заавал биш)<input type="url" name="banner_url" value="{{ old('banner_url', $values['banner_url']) }}"></label>
        <button class="button button-primary" type="submit">Хадгалах</button>
    </form>
</section>
@endsection
