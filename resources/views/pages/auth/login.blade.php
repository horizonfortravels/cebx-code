@extends('layouts.auth')

@section('title', 'طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„')

@section('brand-bg', 'background: linear-gradient(160deg, #1E3A5F 0%, #2D5A8E 100%)')

@section('brand-content')
    <div class="brand-logo" style="background:linear-gradient(135deg,#1E3A5F,#2D5A8E);box-shadow:0 8px 32px rgba(30,58,95,0.4)">SG</div>
    <span class="brand-badge" style="background:rgba(255,255,255,0.15);color:#BFDBFE">CBEX</span>
    <h2 class="brand-title">ط¨ظˆط§ط¨ط© ط¥ط¯ط§ط±ط© ط§ظ„ط´ط­ظ†</h2>
    <p class="brand-desc">CBEX Group â€” Shipping Gateway Platform</p>
@endsection

@section('form-title', 'طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„')
@section('form-subtitle', 'ط£ط¯ط®ظ„ ط¨ظٹط§ظ†ط§طھظƒ ظ„ظ„ط¯ط®ظˆظ„')
@section('form-action', route('login'))
@section('email-placeholder', 'you@company.sa')
@section('input-focus-style', '')
@section('btn-text', 'طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„')
@section('back-link-url', url('/'))
@section('back-link-text', '← العودة إلى صفحة اختيار البوابة')
