@extends('layouts.auth')

@section('title', 'تسجيل الدخول')

@section('brand-bg', 'background: linear-gradient(160deg, #1E3A5F 0%, #2D5A8E 100%)')

@section('brand-content')
    <div class="brand-logo" style="background:linear-gradient(135deg,#1E3A5F,#2D5A8E);box-shadow:0 8px 32px rgba(30,58,95,0.4)">SG</div>
    <span class="brand-badge" style="background:rgba(255,255,255,0.15);color:#BFDBFE">CBEX</span>
    <h2 class="brand-title">بوابة إدارة الشحن</h2>
    <p class="brand-desc">CBEX Group - Shipping Gateway Platform</p>
@endsection

@section('form-title', 'تسجيل الدخول')
@section('form-subtitle', 'أدخل بياناتك للمتابعة')
@section('form-action', route('login'))
@section('email-placeholder', 'you@company.sa')
@section('input-focus-style', '')
@section('btn-text', 'تسجيل الدخول')
@section('back-link-url', url('/'))
@section('back-link-text', 'العودة إلى صفحة اختيار البوابة')
