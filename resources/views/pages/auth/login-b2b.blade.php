@extends('layouts.auth')

@section('title', 'دخول الأعمال — B2B')

@section('portal-styles')
    .form-group input:focus { border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }
@endsection

@section('brand-bg', 'background: linear-gradient(160deg, #1E3A5F 0%, #1E40AF 40%, #3B82F6 100%)')

@section('brand-content')
    <div class="brand-logo" style="background:linear-gradient(135deg,#3B82F6,#1D4ED8);box-shadow:0 8px 32px rgba(59,130,246,0.4)">B2B</div>
    <span class="brand-badge" style="background:rgba(255,255,255,0.15);color:#93C5FD">BUSINESS PORTAL</span>
    <h2 class="brand-title">بوابة الأعمال</h2>
    <p class="brand-desc">منصة متكاملة لإدارة شحنات شركتك — ربط المتاجر، إدارة الفريق، والتقارير التحليلية في مكان واحد.</p>
    <ul class="brand-features">
        <li><span>📦</span> إدارة الشحنات والتتبع المباشر</li>
        <li><span>🏪</span> ربط المتاجر الإلكترونية (سلة، زد، Shopify)</li>
        <li><span>👥</span> إدارة فريق العمل والأدوار</li>
        <li><span>📊</span> تقارير وتحليلات متقدمة</li>
        <li><span>💰</span> المحفظة الإلكترونية والفوترة</li>
    </ul>
@endsection

@section('form-title', 'دخول الأعمال')
@section('form-subtitle', 'سجّل دخولك بحساب شركتك في بوابة B2B')
@section('form-action', route('b2b.login.submit'))
@section('email-placeholder', 'you@company.sa')
@section('input-focus-style', '')
@section('link-color', 'color:#3B82F6')
@section('btn-style', 'background:linear-gradient(135deg,#3B82F6,#1D4ED8);box-shadow:0 4px 16px rgba(59,130,246,0.4)')
@section('btn-text', '🏢 دخول بوابة الأعمال')

@section('demo-credentials')
<div class="demo-credentials">
    <div class="demo-title">🔑 بيانات تجريبية</div>
    <div class="demo-row"><span>البريد:</span> <code>sultan@techco.sa</code></div>
    <div class="demo-row"><span>كلمة المرور:</span> <code>password</code></div>
</div>
@endsection
