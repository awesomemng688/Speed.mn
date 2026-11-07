@extends('layouts.app')
@section('title', 'Admin security — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><h1>Admin security</h1><p class="muted">Require an authenticator code before accessing admin tools.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="admin-alert error" role="alert">{{ $errors->first() }}</div>@endif
    @if($enabled)
        <div class="panel" style="padding:24px;max-width:640px">
            <p class="admin-badge enabled">Authenticator enabled</p>
            <p class="muted">Admin access is verified for this session. Disabling requires a fresh authenticator code.</p>
            <form method="POST" action="{{ route('admin.two-factor.disable') }}" class="admin-actions">
                @csrf @method('DELETE')
                <label class="filter-field"><span>Authenticator code</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                <button class="button button-ghost" type="submit">Disable 2FA</button>
            </form>
        </div>
    @else
        <div class="panel" style="padding:24px;max-width:640px">
            @if($secretRotated)
                <div class="admin-alert success" role="status">Аюулгүй байдлын үүднээс өмнөх тохиргооны түлхүүрийг шинэчиллээ. Доорх QR кодыг authenticator app-аараа уншуулна уу.</div>
            @endif
            <h2>1. Authenticator app-д QR код уншуулна уу</h2>
            <p class="muted">Authenticator app-аараа QR кодыг уншуулна. QR нь таны браузерт локалаар үүсэх тул гаднын QR үйлчилгээнд түлхүүр илгээгдэхгүй.</p>
            <div style="display:grid;justify-items:start;gap:12px">
                <canvas data-totp-qr data-otpauth-url="{{ $otpauthUrl }}" aria-label="Authenticator тохируулах QR код"></canvas>
                <p class="muted" data-totp-qr-status role="status">QR код ачаалж байна…</p>
            </div>
            <details style="margin-top:16px">
                <summary>QR уншуулах боломжгүй бол түлхүүрийг гараар оруулах</summary>
                <p class="muted">Account: {{ auth()->user()->email }} · Issuer: {{ config('app.name') }}</p>
                <p><code>{{ $secret }}</code></p>
            </details>
            <h2>2. Confirm setup</h2>
            <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="admin-actions">
                @csrf
                <label class="filter-field"><span>6-digit code</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label>
                <button class="button button-primary" type="submit">Enable 2FA</button>
            </form>
        </div>
        @vite('resources/js/admin-totp-setup.js')
    @endif
</section>
@endsection