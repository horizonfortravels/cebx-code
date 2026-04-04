<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>إعادة تعيين كلمة المرور - بوابة الشحن</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
    <style>
        :root {
            --page-max: 1600px;
            --page-gutter: clamp(24px, 2vw, 40px);
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg,#eff6ff 0%,#f8fafc 48%,#e0f2fe 100%);
            font-family: 'Tajawal', sans-serif;
            color: #0f172a;
        }
        .reset-page {
            width: 100%;
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.12fr) minmax(0, 0.88fr);
            align-items: stretch;
        }
        .reset-hero {
            background: #0f3a5f;
            color: #fff;
            padding: clamp(44px, 4.2vw, 88px) clamp(32px, 4vw, 68px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 18px;
            min-height: 100vh;
        }
        .reset-main {
            padding: clamp(40px, 4.2vw, 88px) clamp(24px, 3vw, 56px);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .reset-card {
            width: 100%;
            max-width: clamp(500px, 34vw, 620px);
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 20px 45px rgba(15,23,42,.08);
            padding: clamp(30px, 2.4vw, 38px);
        }
        .reset-card h2 {
            margin: 0 0 8px;
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
        }
        .reset-card p {
            margin: 0 0 24px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.8;
        }
        .reset-alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.7;
        }
        .reset-alert.success {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }
        .reset-alert.error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }
        .reset-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .reset-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }
        .reset-input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 14px 16px;
            font: inherit;
            color: #0f172a;
            background: #fff;
        }
        .reset-hint {
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
            line-height: 1.7;
        }
        .reset-submit {
            border: none;
            border-radius: 16px;
            padding: 14px 18px;
            background: #0f3a5f;
            color: #fff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        .reset-link {
            margin-top: 18px;
            text-align: center;
            font-size: 13px;
        }
        .reset-link a {
            color: #0f3a5f;
            text-decoration: none;
            font-weight: 700;
        }
        @media (max-width: 1024px) {
            .reset-page {
                grid-template-columns: 1fr;
            }
            .reset-hero,
            .reset-main {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
<div class="reset-page">
    <section class="reset-hero">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:20px;background:rgba(255,255,255,.14);font-weight:800;font-size:24px">ش</div>
        <div style="display:inline-block;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.12);font-size:12px;font-weight:700;width:max-content">استعادة الوصول</div>
        <h1 style="margin:0;font-size:30px;font-weight:800;line-height:1.4">أعد تعيين كلمة المرور بأمان</h1>
        <p style="margin:0;font-size:15px;line-height:1.9;opacity:.92;max-width:460px">
            استخدم الرابط الذي وصلك عبر البريد لإنشاء كلمة مرور جديدة. لن نعرض أي رمز أو سر داخل هذه الصفحة، وسيتم إبطال الجلسات السابقة بعد اكتمال العملية.
        </p>
        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;font-size:14px;opacity:.95">
            <li>رابط واحد مخصص لكل طلب إعادة تعيين</li>
            <li>تأكيد كلمة المرور قبل الحفظ</li>
            <li>العودة إلى بوابة الدخول المناسبة بعد النجاح</li>
        </ul>
    </section>

    <main class="reset-main">
        <div class="reset-card">
            <h2>إنشاء كلمة مرور جديدة</h2>
            <p>
                أدخل البريد المرتبط بالحساب ثم اختر كلمة مرور جديدة قوية. بعد الحفظ يمكنك تسجيل الدخول من البوابة المناسبة لنوع الحساب.
            </p>

            @if (session('success'))
                <div class="reset-alert success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="reset-alert error" role="alert" aria-live="polite">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="reset-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="reset-email" class="reset-label">البريد الإلكتروني</label>
                    <input id="reset-email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" class="reset-input">
                </div>

                <div>
                    <label for="reset-password" class="reset-label">كلمة المرور الجديدة</label>
                    <input id="reset-password" type="password" name="password" required autocomplete="new-password" class="reset-input">
                    <div class="reset-hint">استخدم ثمانية أحرف على الأقل مع أحرف كبيرة وصغيرة وأرقام ورمز خاص.</div>
                </div>

                <div>
                    <label for="reset-password-confirmation" class="reset-label">أكد كلمة المرور</label>
                    <input id="reset-password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="reset-input">
                </div>

                <button type="submit" class="reset-submit">
                    حفظ كلمة المرور الجديدة
                </button>
            </form>

            <div class="reset-link">
                <a href="{{ route('login') }}">العودة لاختيار البوابة المناسبة</a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
