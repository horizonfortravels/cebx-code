@extends('layouts.auth')

@section('title', 'دخول الأفراد — B2C')

@section('portal-styles')
    .form-group input:focus { border-color: #0D9488; box-shadow: 0 0 0 4px rgba(13,148,136,0.1); }
@endsection

@section('brand-bg', 'background: linear-gradient(160deg, #134E4A 0%, #0F766E 40%, #0D9488 100%)')

@section('brand-content')
    <div class="brand-logo" style="background:linear-gradient(135deg,#0D9488,#065F56);box-shadow:0 8px 32px rgba(13,148,136,0.4)">B2C</div>
    <span class="brand-badge" style="background:rgba(255,255,255,0.15);color:#5EEAD4">PERSONAL SHIPPING</span>
    <h2 class="brand-title">بوابة الأفراد</h2>
    <p class="brand-desc">أرسل واستلم شحناتك الشخصية بكل سهولة — تتبع مباشر، دفتر عناوين، ومحفظة إلكترونية.</p>
    <ul class="brand-features">
        <li><span>📦</span> إنشاء شحنات وتتبعها بسهولة</li>
        <li><span>🔍</span> تتبع لحظي بالوقت الفعلي</li>
        <li><span>📒</span> دفتر عناوين محفوظ</li>
        <li><span>💳</span> محفظة إلكترونية سريعة</li>
        <li><span>🎧</span> دعم فني على مدار الساعة</li>
    </ul>
@endsection

@section('form-title', 'دخول الأفراد')
@section('form-subtitle', 'سجّل دخولك بحسابك الشخصي في بوابة الأفراد')
@section('form-action', route('b2c.login.submit'))
@section('email-placeholder', 'you@example.sa')
@section('input-focus-style', '')
@section('link-color', 'color:#0D9488')
@section('btn-style', 'background:linear-gradient(135deg,#0D9488,#065F56);box-shadow:0 4px 16px rgba(13,148,136,0.4)')
@section('btn-text', '👤 دخول بوابة الأفراد')

@section('demo-credentials')
<div class="demo-credentials">
    <div class="demo-title">🔑 بيانات تجريبية</div>
    <div class="demo-row"><span>البريد:</span> <code>mohammed@example.sa</code></div>
    <div class="demo-row"><span>كلمة المرور:</span> <code>password</code></div>
</div>
@endsection
