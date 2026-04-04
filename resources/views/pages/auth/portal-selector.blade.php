<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الشحن - اختر البوابة المناسبة لنوع الحساب</title>
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        :root {
            --page-max: 1600px;
            --page-gutter: clamp(24px, 2vw, 40px);
        }
        .portal-page {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, #0F172A 0%, #1E293B 50%, #0F172A 100%);
            padding: clamp(28px, 3vw, 56px) var(--page-gutter);
            position: relative;
            overflow: hidden;
        }
        .portal-page::before {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 70%);
            top: -200px; left: -100px;
            pointer-events: none;
        }
        .portal-page::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(124,58,237,0.06) 0%, transparent 70%);
            bottom: -150px; right: -100px;
            pointer-events: none;
        }
        .portal-shell {
            width: min(var(--page-max), calc(100% - (var(--page-gutter) * 2)));
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 48px;
            position: relative;
            z-index: 1;
        }
        .portal-header {
            text-align: center;
            width: 100%;
            max-width: var(--page-max);
            position: relative;
            z-index: 1;
        }
        .portal-header .logo {
            width: 88px; height: 88px;
            margin: 0 auto 20px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(59,130,246,0.3);
            background: linear-gradient(135deg, #3B82F6, #7C3AED);
        }
        .portal-header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
        }
        .portal-header .logo .logo-fallback {
            display: none;
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 28px;
            font-weight: 800;
        }
        .portal-header h1 {
            color: #F8FAFC;
            font-size: 30px;
            font-weight: 800;
            margin: 0 0 10px;
        }
        .portal-header p {
            color: #94A3B8;
            font-size: 16px;
            margin: 0;
        }
        .portals-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(280px, 1fr));
            gap: clamp(20px, 2vw, 28px);
            max-width: var(--page-max);
            width: 100%;
            position: relative;
            z-index: 1;
        }
        .portal-door {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 20px;
            padding: 36px 28px;
            text-align: center;
            text-decoration: none;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .portal-door::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 20px 20px 0 0;
            transition: height 0.3s ease;
        }
        .portal-door:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .portal-door:hover::before { height: 6px; }
        .portal-door.b2c::before { background: linear-gradient(90deg, #0D9488, #14B8A6); }
        .portal-door.b2c:hover { border-color: rgba(13,148,136,0.4); background: rgba(13,148,136,0.08); }
        .portal-door.b2c .door-icon { background: linear-gradient(135deg, #0D9488, #065F56); box-shadow: 0 6px 24px rgba(13,148,136,0.3); }
        .portal-door.b2b::before { background: linear-gradient(90deg, #3B82F6, #2563EB); }
        .portal-door.b2b:hover { border-color: rgba(59,130,246,0.4); background: rgba(59,130,246,0.08); }
        .portal-door.b2b .door-icon { background: linear-gradient(135deg, #3B82F6, #1D4ED8); box-shadow: 0 6px 24px rgba(59,130,246,0.3); }
        .portal-door.admin::before { background: linear-gradient(90deg, #7C3AED, #6D28D9); }
        .portal-door.admin:hover { border-color: rgba(124,58,237,0.4); background: rgba(124,58,237,0.08); }
        .portal-door.admin .door-icon { background: linear-gradient(135deg, #7C3AED, #4C1D95); box-shadow: 0 6px 24px rgba(124,58,237,0.3); }

        .door-icon {
            width: 64px; height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .portal-door:hover .door-icon { transform: scale(1.1); }
        .door-title {
            color: #F1F5F9;
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .door-subtitle {
            color: #64748B;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .door-desc {
            color: #94A3B8;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 24px;
        }
        .door-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.2s ease;
        }
        .b2c .door-cta { background: rgba(13,148,136,0.15); color: #14B8A6; }
        .b2c:hover .door-cta { background: #0D9488; color: #fff; }
        .b2b .door-cta { background: rgba(59,130,246,0.15); color: #60A5FA; }
        .b2b:hover .door-cta { background: #3B82F6; color: #fff; }
        .admin .door-cta { background: rgba(124,58,237,0.15); color: #A78BFA; }
        .admin:hover .door-cta { background: #7C3AED; color: #fff; }

        .door-badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .b2c .door-badge { background: rgba(13,148,136,0.15); color: #14B8A6; }
        .b2b .door-badge { background: rgba(59,130,246,0.15); color: #60A5FA; }
        .admin .door-badge { background: rgba(124,58,237,0.15); color: #A78BFA; }

        .portal-footer {
            width: 100%;
            max-width: var(--page-max);
            text-align: center;
            color: #475569;
            font-size: 13px;
            position: relative;
            z-index: 1;
        }
        .portal-footer a { color: #64748B; text-decoration: none; }

        @media (max-width: 1200px) {
            .portals-grid { grid-template-columns: repeat(2, minmax(280px, 1fr)); }
        }
        @media (max-width: 768px) {
            .portal-shell { gap: 32px; }
            .portals-grid { grid-template-columns: 1fr; max-width: 100%; }
            .portal-header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
<div class="portal-page">
    <div class="portal-shell">
        <div class="portal-header">
            <div class="logo">
                <img src="{{ asset('images/logo-auth.png') }}" alt="شعار بوابة الشحن" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <span class="logo-fallback" style="display:none;">ش</span>
            </div>
            <h1>بوابة الشحن</h1>
            <p>اختر البوابة المناسبة لنوع الحساب: بوابة الأفراد للحسابات الفردية، وبوابة الأعمال للحسابات المنظمة، والبوابة الداخلية لموظفي المنصة.</p>
        </div>

        <div class="portals-grid">
            <a href="{{ route('b2c.login') }}" class="portal-door b2c">
                <div class="door-icon">👤</div>
                <span class="door-badge">الأفراد</span>
                <div class="door-title">بوابة الأفراد</div>
                <div class="door-subtitle">الحسابات الفردية</div>
                <div class="door-desc">للحسابات الفردية الخارجية فقط: إدارة الشحنات الشخصية، تتبع الطلبات، وإدارة العناوين والمحفظة ضمن التجربة المخصصة للأفراد.</div>
                <div class="door-cta">دخول حساب الأفراد</div>
            </a>

            <a href="{{ route('b2b.login') }}" class="portal-door b2b">
                <div class="door-icon">🏢</div>
                <span class="door-badge">الأعمال</span>
                <div class="door-title">بوابة الأعمال</div>
                <div class="door-subtitle">حسابات المنظمات</div>
                <div class="door-desc">لحسابات المنظمات الخارجية فقط: إدارة شحنات المنظمة، فرق العمل، والتكاملات عبر شبكة الناقلين التابعة للمنصة.</div>
                <div class="door-cta">دخول حساب الأعمال</div>
            </a>

            <a href="{{ route('admin.login') }}" class="portal-door admin">
                <div class="door-icon">🛡️</div>
                <span class="door-badge">داخلي</span>
                <div class="door-title">البوابة الداخلية</div>
                <div class="door-subtitle">موظفو المنصة</div>
                <div class="door-desc">مساحة مخصصة لموظفي المنصة وإدارة العمليات الداخلية، مع رؤية أوسع للحسابات الخارجية ومتابعة سياقات التشغيل.</div>
                <div class="door-cta">دخول البوابة الداخلية</div>
            </a>
        </div>

        <div class="portal-footer">
            <a href="{{ url('/') }}">بوابة الشحن</a>
        </div>
    </div>
</div>
</body>
</html>
