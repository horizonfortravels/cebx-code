<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>لوحة التحكم — بوابة الأعمال (B2B)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8e 100%);
            min-height: 100vh;
            color: #333;
        }
        .header {
            background: rgba(255,255,255,0.95);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .badge-b2b {
            display: inline-block;
            background: #1e3a5f;
            color: #fff;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .header h1 { font-size: 18px; color: #1e3a5f; }
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
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 24px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .card h2 {
            font-size: 16px;
            color: #1e3a5f;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .nav-link {
            display: block;
            padding: 14px 16px;
            background: #eff6ff;
            color: #1e3a5f;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            transition: background 0.2s;
        }
        .nav-link:hover {
            background: #dbeafe;
        }
        .welcome {
            font-size: 15px;
            color: #555;
            margin-bottom: 8px;
        }
        .welcome strong { color: #1e3a5f; }
        .switch-portal {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #888;
        }
        .switch-portal a {
            color: #1e3a5f;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <span class="badge-b2b">B2B — بوابة الأعمال</span>
            <h1>لوحة التحكم</h1>
        </div>
        <div class="header-actions">
            <form method="POST" action="{{ route('b2b.logout') }}" class="inline">
                @csrf
                <button type="submit" class="btn-logout">تسجيل الخروج</button>
            </form>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <p class="welcome">مرحباً، <strong>{{ Auth::user()->name ?? Auth::user()->email }}</strong></p>
            <p class="welcome">من هنا يمكنك إدارة الشحنات والطلبات والمتاجر والمستخدمين والمحفظة والتقارير.</p>
        </div>

        <div class="card">
            <h2>القائمة الرئيسية</h2>
            <div class="nav-grid">
                <a href="{{ route('b2b.shipments.index') }}" class="nav-link">📦 الشحنات</a>
                <a href="{{ route('b2b.orders.index') }}" class="nav-link">🛒 الطلبات</a>
                <a href="{{ route('b2b.stores.index') }}" class="nav-link">🏪 المتاجر</a>
                <a href="{{ route('b2b.users.index') }}" class="nav-link">👥 المستخدمين</a>
                <a href="{{ route('b2b.roles.index') }}" class="nav-link">🛡 الأدوار</a>
                <a href="{{ route('b2b.invitations.index') }}" class="nav-link">📧 الدعوات</a>
                <a href="{{ route('b2b.wallet.index') }}" class="nav-link">💰 المحفظة</a>
                <a href="{{ route('b2b.reports.index') }}" class="nav-link">📈 التقارير</a>
                <a href="{{ route('b2b.settings.index') }}" class="nav-link">⚙ الإعدادات</a>
            </div>
            <div class="switch-portal">
                حساب فردي؟ <a href="{{ route('b2c.login') }}">انتقل إلى بوابة B2C</a>
            </div>
        </div>
    </main>
</body>
</html>
