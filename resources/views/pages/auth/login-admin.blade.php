@extends('layouts.auth')

@section('title', 'دخول الإدارة — Admin')

@section('portal-styles')
    .form-group input:focus { border-color: #7C3AED; box-shadow: 0 0 0 4px rgba(124,58,237,0.1); }
@endsection

@section('brand-bg', 'background: linear-gradient(160deg, #2E1065 0%, #4C1D95 40%, #7C3AED 100%)')

@section('brand-content')
    <div class="brand-logo" style="background:linear-gradient(135deg,#7C3AED,#4C1D95);box-shadow:0 8px 32px rgba(124,58,237,0.4)">SYS</div>
    <span class="brand-badge" style="background:rgba(255,255,255,0.15);color:#C4B5FD">SYSTEM ADMIN</span>
    <h2 class="brand-title">لوحة الإدارة</h2>
    <p class="brand-desc">التحكم الكامل بالنظام — إدارة المنظمات، اللوجستيات، الامتثال، التسعير، والتدقيق.</p>
    <ul class="brand-features">
        <li><span>🏢</span> إدارة المنظمات والحسابات</li>
        <li><span>🚢</span> اللوجستيات: سفن، حاويات، جمارك</li>
        <li><span>🪪</span> الامتثال: KYC، بضائع خطرة، مخاطر</li>
        <li><span>🏷️</span> التسعير وقواعد الشحن</li>
        <li><span>📜</span> سجل التدقيق والمراجعة</li>
    </ul>
@endsection

@section('form-title', 'دخول الإدارة')
@section('form-subtitle', 'سجّل دخولك بحساب المسؤول لإدارة النظام')
@section('form-action', route('admin.login.submit'))
@section('email-placeholder', 'admin@system.sa')
@section('input-focus-style', '')
@section('link-color', 'color:#7C3AED')
@section('btn-style', 'background:linear-gradient(135deg,#7C3AED,#4C1D95);box-shadow:0 4px 16px rgba(124,58,237,0.4)')
@section('btn-text', '🛡️ دخول لوحة الإدارة')

@section('demo-credentials')
<div class="demo-credentials">
    <div class="demo-title">🔑 بيانات تجريبية</div>
    <div class="demo-row"><span>البريد:</span> <code>admin@system.sa</code></div>
    <div class="demo-row"><span>كلمة المرور:</span> <code>admin</code></div>
</div>
@endsection
