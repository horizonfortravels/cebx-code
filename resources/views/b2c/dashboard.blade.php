<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>لوحة الحساب — بوابة الأفراد للحسابات الفردية</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: #333;
        }
        .header {
            background: rgba(255,255,255,0.95);
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .header-inner {
            width: min(1600px, calc(100% - 40px));
            min-height: 64px;
            margin: 0 auto;
            padding: 14px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .badge-b2c {
            display: inline-block;
            background: #0d9488;
            color: #fff;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .header h1 { font-size: 18px; color: #0d9488; }
        .header-actions form { display: inline; }
        .btn-logout {
            padding: 8px 16px;
            background: #e5e7eb;
            color: #374151;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-logout:hover { background: #d1d5db; }
        .container {
            width: min(1600px, calc(100% - 40px));
            margin: 0 auto;
            padding: clamp(28px, 3vw, 40px) 0 40px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: clamp(24px, 2vw, 30px);
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .card h2 {
            font-size: 16px;
            color: #0d9488;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }
        .nav-link {
            display: block;
            padding: 14px 16px;
            background: #f0fdfa;
            color: #0d9488;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            transition: background 0.2s;
        }
        .nav-link:hover {
            background: #ccfbf1;
        }
        .welcome {
            font-size: 15px;
            color: #555;
            margin-bottom: 8px;
        }
        .welcome strong { color: #0d9488; }
        @media (max-width: 768px) {
            .header-inner,
            .container {
                width: min(100%, calc(100% - 24px));
            }
            .header-inner {
                flex-wrap: wrap;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
        <div class="header-left">
        <span class="badge-b2c">بوابة الأفراد</span>
            <h1>لوحة الحساب الفردي</h1>
        </div>
        <div class="header-actions">
            <form method="POST" action="{{ route('b2c.logout') }}" class="inline">
                @csrf
                <button type="submit" class="btn-logout">تسجيل الخروج</button>
            </form>
        </div>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <p class="welcome">مرحبًا، <strong>{{ Auth::user()->name ?? Auth::user()->email }}</strong></p>
            <p class="welcome">هذه الصفحة مخصصة للحسابات الفردية الخارجية فقط. من هنا يمكنك إدارة شحناتك الشخصية وتتبعها والوصول إلى المحفظة والعناوين والدعم عبر شبكة الناقلين التابعة للمنصة.</p>
        </div>

        <div class="card">
            <h2>الأقسام المتاحة للحساب الفردي</h2>
            <div class="nav-grid">
                <a href="{{ route('b2c.shipments.index') }}" class="nav-link">📦 الشحنات</a>
                <a href="{{ route('b2c.tracking.index') }}" class="nav-link">🔎 التتبع</a>
                <a href="{{ route('b2c.wallet.index') }}" class="nav-link">💰 المحفظة</a>
                <a href="{{ route('b2c.addresses.index') }}" class="nav-link">📍 العناوين</a>
                <a href="{{ route('b2c.support.index') }}" class="nav-link">🧾 الدعم</a>
                <a href="{{ route('b2c.settings.index') }}" class="nav-link">⚙ الإعدادات</a>
            </div>
        </div>
    </main>
</body>
</html>
