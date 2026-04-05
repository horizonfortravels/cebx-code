<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'تسجيل الدخول') - بوابة الشحن</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
    @php
        $authPortal = trim($__env->yieldContent('auth-portal', 'shared'));
        $brandMark = trim($__env->yieldContent('brand-mark'));
        $brandBadge = trim($__env->yieldContent('brand-badge'));
        $brandTitle = trim($__env->yieldContent('brand-title'));
        $brandDescription = trim($__env->yieldContent('brand-description'));
        $brandFeatures = trim($__env->yieldContent('brand-features'));
        $formBadge = trim($__env->yieldContent('form-badge'));
        $formNote = trim($__env->yieldContent('form-note'));
        $formSupport = trim($__env->yieldContent('form-support'));
        $legacyBrandBackground = trim($__env->yieldContent('brand-bg'));
        $legacyInputFocusStyle = trim($__env->yieldContent('input-focus-style'));
        $legacyButtonStyle = trim($__env->yieldContent('btn-style'));
        $legacyLinkStyle = trim($__env->yieldContent('link-color'));
        $hasStructuredBrand = $brandMark !== ''
            || $brandBadge !== ''
            || $brandTitle !== ''
            || $brandDescription !== ''
            || $brandFeatures !== '';
    @endphp
    @hasSection('portal-styles')
        <style>@yield('portal-styles')</style>
    @endif
</head>
<body class="auth-layout auth-layout--{{ $authPortal }}">
<div class="login-page">
    <div class="login-brand" @if($legacyBrandBackground !== '') style="{{ $legacyBrandBackground }}" @endif>
        @if($hasStructuredBrand)
            <div class="login-brand-shell">
                @if($brandMark !== '')
                    <div class="brand-logo">{!! $brandMark !!}</div>
                @endif
                @if($brandBadge !== '')
                    <span class="brand-badge">{{ $brandBadge }}</span>
                @endif
                @if($brandTitle !== '')
                    <h2 class="brand-title">{{ $brandTitle }}</h2>
                @endif
                @if($brandDescription !== '')
                    <p class="brand-desc">{{ $brandDescription }}</p>
                @endif
                @if($brandFeatures !== '')
                    <ul class="brand-features">{!! $brandFeatures !!}</ul>
                @endif
            </div>
        @else
            @yield('brand-content')
        @endif
    </div>

    <div class="login-form-panel">
        <div class="login-form-card">
            <div class="login-form-head">
                <h1 class="login-form-title">@yield('form-title', 'تسجيل الدخول')</h1>
                <p class="login-form-subtitle">@yield('form-subtitle', 'أدخل بياناتك للمتابعة')</p>
            </div>

            @if (session('success'))
                <div class="login-success" role="status" aria-live="polite">
                    {{ session('success') }}
                </div>
            @endif

            @if($formBadge !== '')
                <div class="login-form-badge">{!! $formBadge !!}</div>
            @endif

            @if($formNote !== '')
                <div class="login-form-note">{!! $formNote !!}</div>
            @endif

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
                        placeholder="@yield('email-placeholder', 'name@company.sa')"
                        class="form-input"
                        inputmode="email"
                        autocomplete="username"
                        aria-describedby="{{ $errors->any() ? 'login-errors' : 'login-email-hint' }}"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        required
                        autofocus
                        @if($legacyInputFocusStyle !== '') style="{{ $legacyInputFocusStyle }}" @endif
                    >
                    <div id="login-email-hint" class="form-hint">استخدم البريد المرتبط بالحساب الذي تريد تسجيل الدخول إليه.</div>
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
                <button type="submit" class="login-submit-btn" @if($legacyButtonStyle !== '') style="{{ $legacyButtonStyle }}" @endif>
                    @yield('btn-text', 'دخول')
                </button>
            </form>

            <div class="back-link" @if($legacyLinkStyle !== '') style="{{ $legacyLinkStyle }}" @endif>
                <a href="@yield('back-link-url', route('login'))">@yield('back-link-text', 'العودة إلى اختيار البوابة المناسبة لنوع الحساب')</a>
            </div>

            @if($formSupport !== '')
                <div class="login-support">{!! $formSupport !!}</div>
            @endif

            @if (app()->environment('local') && config('features.demo_data', false))
                @yield('demo-credentials')
            @endif
        </div>
    </div>
</div>

@yield('content')

<script>window.PWA={swUrl:'{{ asset("sw.js") }}',scope:'{{ rtrim(url("/"), "/") }}/'};</script>
<script src="{{ asset('js/pwa.js') }}" defer></script>
</body>
</html>
