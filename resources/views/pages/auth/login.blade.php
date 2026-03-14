@extends('layouts.auth')
@section('title', 'تسجيل الدخول')
@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="{{ asset('images/logo-auth.png') }}" alt="CBEX Group" class="auth-logo-img">
        </div>
        <h1 style="color:var(--tx);font-size:20px;font-weight:700;margin:0 0 4px">بوابة إدارة الشحن</h1>
        <p style="color:var(--td);font-size:11px;margin-bottom:24px">CBEX Group — Shipping Gateway Platform</p>
        <form method="POST" action="{{ route('login') }}" style="text-align:right">
            @csrf
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                @error('email') <span style="color:var(--dg);font-size:10px">{{ $message }}</span> @enderror
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور</label>
                <input type="password" name="password" class="form-control" required>
                @error('password') <span style="color:var(--dg);font-size:10px">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="btn btn-pr btn-block btn-lg" style="margin-top:10px">تسجيل الدخول</button>
            <p class="form-hint" style="margin-top:14px;color:var(--td);font-size:11px;text-align:center">
                بعد إصلاح كلمة المرور والبريد الإلكتروني يمكنك تسجيل الدخول.
            </p>
        </form>
    </div>
</div>
@endsection
