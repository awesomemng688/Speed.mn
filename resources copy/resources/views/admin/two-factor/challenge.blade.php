@extends('layouts.app')
@section('title', 'Verify admin access — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATOR VERIFICATION</p><h1>Verify it is you</h1><p class="muted">Enter an authenticator code or a one-time recovery code to continue.</p></div></section>
<section class="shell section admin-section">
    @if($errors->any())<div class="admin-alert error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="panel" style="padding:24px;max-width:480px">
        @csrf
        <label class="filter-field"><span>Authenticator or recovery code</span><input name="code" autocomplete="one-time-code" inputmode="numeric" maxlength="32" required autofocus></label>
        <button class="button button-primary" type="submit">Verify</button>
    </form>
</section>
@endsection