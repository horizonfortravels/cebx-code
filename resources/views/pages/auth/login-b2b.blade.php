@extends('layouts.auth')

@section('title', 'دخول الأعمال — حسابات المنظمات')

@section('portal-styles')
    .form-group input:focus { border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }
@endsection

@section('brand-bg', 'background: linear-gradient(160deg, #1E3A5F 0%, #1E40AF 40%, #3B82F6 100%)')

@section('brand-content')
    <div class="brand-logo"
        style="background:linear-gradient(135deg,#3B82F6,#1D4ED8);box-shadow:0 8px 32px rgba(59,130,246,0.4)">أعمال</div>
    <span class="brand-badge" style="background:rgba(255,255,255,0.15);color:#93C5FD">حسابات المنظمات</span>
    <h2 class="brand-title">بوابة الأعمال</h2>
    <p class="brand-desc">بوابة مخصصة لحسابات المنظمات الخارجية فقط، لإدارة شحنات المنظمة وفريقها عبر شبكة الناقلين التابعة
        للمنصة.</p>
    <ul class="brand-features">
        <li><span>📦</span> إدارة الشحنات والتتبع المباشر</li>
        <li><span>🏪</span> ربط المتاجر الإلكترونية (سلة، زد، شوبيفاي)</li>
        <li><span>👥</span> إدارة فريق العمل والأدوار</li>
        <li><span>📊</span> تقارير وتحليلات متقدمة</li>
        <li><span>💰</span> المحفظة الإلكترونية والفوترة</li>
    </ul>
@endsection

@section('form-title', 'دخول الأعمال')
@section('form-subtitle', 'سجّل دخولك بحساب المنظمة الخارجي للوصول إلى الشحنات والتقارير وأدوات تكامل المنصة')
@section('form-action', route('b2b.login.submit'))
@section('email-placeholder', 'مثال: اسم@الشركة.sa')
@section('input-focus-style', '')
@section('link-color', 'color:#3B82F6')
@section('btn-style', 'background:linear-gradient(135deg,#3B82F6,#1D4ED8);box-shadow:0 4px 16px rgba(59,130,246,0.4)')
@section('btn-text', '🏢 دخول حساب المنظمة')
