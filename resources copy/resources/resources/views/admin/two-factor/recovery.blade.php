@extends('layouts.app')
@section('title', 'Save recovery codes — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATOR SECURITY</p><h1>Save your recovery codes</h1><p class="muted">Each code works once. Store them somewhere private; they will not be shown again.</p></div></section>
<section class="shell section admin-section">
    <div class="panel" style="padding:24px;max-width:560px">
        <ul>@foreach($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
        <a class="button button-primary" href="{{ route('admin.servers.index') }}">Continue to admin</a>
    </div>
</section>
@endsection