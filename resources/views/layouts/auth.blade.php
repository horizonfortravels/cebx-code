<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'تسجيل الدخول') — Shipping Gateway</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
    @hasSection('portal-styles')
    <style>@yield('portal-styles')</style>
    @endif
</head>
<body>
<div class="login-page">
    {{-- لوحة العلامة التجارية (يسار) --}}
    <div class="login-brand" style="@yield('brand-bg', 'background:#1E3A5F')">
        @yield('brand-content')
    </div>

    {{-- لوحة النموذج (يمين) --}}
    <div class="login-form-panel">
        <div class="login-form-card">
            <h1 class="login-form-title">@yield('form-title', 'تسجيل الدخول')</h1>
            <p class="login-form-subtitle">@yield('form-subtitle', 'أدخل بياناتك للدخول')</p>

            @if ($errors->any())
                <div class="login-errors" role="alert" aria-live="polite" id="login-errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="@yield('form-action')" class="login-form">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="login-email">البريد الإلكتروني</label>
                    <input
                        id="login-email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="@yield('email-placeholder', 'you@company.sa')"
                        class="form-input"
                        inputmode="email"
                        autocomplete="username"
                        aria-describedby="{{ $errors->any() ? 'login-errors' : 'login-email-hint' }}"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        required
                        autofocus
                        style="@yield('input-focus-style')"
                    >
                    <div id="login-email-hint" class="form-hint">استخدم البريد المرتبط بالحساب الذي تريد الدخول إليه.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="login-password">كلمة المرور</label>
                    <input
                        id="login-password"
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        class="form-input"
                        autocomplete="current-password"
                        aria-describedby="{{ $errors->any() ? 'login-errors' : 'login-password-hint' }}"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        required
                    >
                    <div id="login-password-hint" class="form-hint">أدخل كلمة المرور الحالية لهذا الحساب.</div>
                </div>
                <div class="login-form-options">
                    <label class="login-remember" for="login-remember">
                        <input id="login-remember" type="checkbox" name="remember"> تذكرني على هذا الجهاز
                    </label>
                </div>
                <button type="submit" class="login-submit-btn" style="@yield('btn-style', 'background:var(--pr);color:#fff')">
                    @yield('btn-text', 'دخول')
                </button>
            </form>

            <div class="back-link" style="@yield('link-color')">
                <a href="@yield('back-link-url', route('login'))">@yield('back-link-text', '← العودة لاختيار البوابة المناسبة لنوع الحساب')</a>
            </div>

            @if (app()->environment('local') && config('features.demo_data', false))
                @yield('demo-credentials')
            @endif
        </div>
    </div>
</div>

@yield('content')

<style>
        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .login-page {
            width: 100%;
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
            font-family: 'Tajawal', sans-serif;
            direction: rtl;
        }
        @media (max-width: 1024px) {
            .login-page { grid-template-columns: 1fr; }
            .login-brand { min-height: 260px; padding: 36px 24px !important; }
            .login-form-panel { padding: 28px 20px 36px; }
        }
        .login-brand {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: clamp(40px, 4vw, 72px) clamp(32px, 4vw, 64px);
            color: #fff;
            text-align: center;
        }
.login-brand .brand-logo {
    width: 72px; height: 72px;
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 24px;
    margin-bottom: 12px;
}
.login-brand .brand-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .5px;
    margin-bottom: 16px;
}
.login-brand .brand-title { font-size: 22px; font-weight: 800; margin: 0 0 8px; }
.login-brand .brand-desc { font-size: 14px; opacity: .9; line-height: 1.6; margin: 0 0 24px; max-width: 320px; }
.login-brand .brand-features { list-style: none; margin: 0; padding: 0; text-align: right; }
.login-brand .brand-features li { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 13px; opacity: .95; }
.login-brand .brand-features li span { font-size: 18px; }
        .login-form-panel {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(32px, 4vw, 72px);
            background: var(--bg, #F8FAFC);
        }
        .login-form-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
            border: 1px solid var(--bd, #E2E8F0);
}
.login-form-title { font-size: 22px; font-weight: 800; color: var(--tx,#1E293B); margin: 0 0 6px; }
.login-form-subtitle { font-size: 14px; color: var(--td,#64748B); margin: 0 0 24px; }
.login-errors {
    padding: 12px 14px;
    background: #FEE2E2;
    border: 1px solid #FECACA;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #991B1B;
}
.login-form .form-group { margin-bottom: 18px; }
.form-hint {
    margin-top: 6px;
    font-size: 12px;
    color: var(--tm, #94A3B8);
}
.login-form-options { margin-bottom: 20px; }
.login-remember { font-size: 13px; color: var(--td); cursor: pointer; display: flex; align-items: center; gap: 8px; }
.login-submit-btn {
    width: 100%;
    padding: 14px 20px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: transform .15s, box-shadow .15s;
}
.login-submit-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,.15); }
.back-link { margin-top: 20px; text-align: center; font-size: 13px; }
.back-link a { text-decoration: none; font-weight: 600; }
.back-link a:hover { text-decoration: underline; }
.demo-credentials {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--bd,#E2E8F0);
}
.demo-title { font-size: 12px; font-weight: 700; color: var(--td); margin-bottom: 10px; }
.demo-row { font-size: 13px; color: var(--tx); margin-bottom: 4px; }
.demo-row code { background: var(--sf,#F1F5F9); padding: 2px 8px; border-radius: 6px; font-size: 12px; }
</style>

<script>window.PWA={swUrl:'{{ asset("sw.js") }}',scope:'{{ rtrim(url("/"), "/") }}/'};</script>
<script src="{{ asset('js/pwa.js') }}" defer></script>
</body>
</html>
