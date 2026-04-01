<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>إعادة تعيين كلمة المرور — Shipping Gateway</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
</head>
<body style="margin:0;background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 48%,#e0f2fe 100%);font-family:'Tajawal',sans-serif;color:#0f172a">
<div style="min-height:100vh;display:grid;grid-template-columns:minmax(320px,460px) minmax(320px,520px);align-items:stretch">
    <section style="background:#0f3a5f;color:#fff;padding:56px 40px;display:flex;flex-direction:column;justify-content:center;gap:18px">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:20px;background:rgba(255,255,255,.14);font-weight:800;font-size:24px">SG</div>
        <div style="display:inline-block;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.12);font-size:12px;font-weight:700;width:max-content">استعادة الوصول</div>
        <h1 style="margin:0;font-size:30px;font-weight:800;line-height:1.4">أعد تعيين كلمة المرور بأمان</h1>
        <p style="margin:0;font-size:15px;line-height:1.9;opacity:.92;max-width:420px">
            استخدم الرابط الذي وصلك عبر البريد لإنشاء كلمة مرور جديدة. لن يتم عرض أي رمز أو سر داخل هذه الصفحة، وسيتم إبطال الجلسات السابقة بعد الإتمام.
        </p>
        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;font-size:14px;opacity:.95">
            <li>رابط واحد موقّت لكل طلب إعادة تعيين</li>
            <li>تأكيد كلمة المرور قبل الحفظ</li>
            <li>العودة إلى بوابة الدخول المناسبة بعد النجاح</li>
        </ul>
    </section>

    <main style="padding:40px 28px;display:flex;align-items:center;justify-content:center">
        <div style="width:100%;max-width:460px;background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 20px 45px rgba(15,23,42,.08);padding:32px">
            <h2 style="margin:0 0 8px;font-size:26px;font-weight:800;color:#0f172a">إنشاء كلمة مرور جديدة</h2>
            <p style="margin:0 0 24px;color:#64748b;font-size:14px;line-height:1.8">
                أدخل البريد المرتبط بالحساب ثم اختر كلمة مرور جديدة قوية. بعد الحفظ يمكنك تسجيل الدخول من البوابة المناسبة لنوع الحساب.
            </p>

            @if (session('success'))
                <div style="margin-bottom:18px;padding:12px 14px;border-radius:14px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div style="margin-bottom:18px;padding:12px 14px;border-radius:14px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b" role="alert" aria-live="polite">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" style="display:flex;flex-direction:column;gap:18px">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="reset-email" style="display:block;margin-bottom:8px;font-size:13px;font-weight:700;color:#334155">البريد الإلكتروني</label>
                    <input id="reset-email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"
                           style="width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:14px 16px;font:inherit;color:#0f172a;background:#fff">
                </div>

                <div>
                    <label for="reset-password" style="display:block;margin-bottom:8px;font-size:13px;font-weight:700;color:#334155">كلمة المرور الجديدة</label>
                    <input id="reset-password" type="password" name="password" required autocomplete="new-password"
                           style="width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:14px 16px;font:inherit;color:#0f172a;background:#fff">
                    <div style="margin-top:6px;font-size:12px;color:#64748b">استخدم ثمانية أحرف على الأقل مع أحرف كبيرة وصغيرة وأرقام ورمز خاص.</div>
                </div>

                <div>
                    <label for="reset-password-confirmation" style="display:block;margin-bottom:8px;font-size:13px;font-weight:700;color:#334155">تأكيد كلمة المرور</label>
                    <input id="reset-password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           style="width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:14px 16px;font:inherit;color:#0f172a;background:#fff">
                </div>

                <button type="submit" style="border:none;border-radius:16px;padding:14px 18px;background:#0f3a5f;color:#fff;font:inherit;font-weight:800;cursor:pointer">
                    حفظ كلمة المرور الجديدة
                </button>
            </form>

            <div style="margin-top:18px;text-align:center;font-size:13px">
                <a href="{{ route('login') }}" style="color:#0f3a5f;text-decoration:none;font-weight:700">العودة لاختيار البوابة المناسبة</a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
