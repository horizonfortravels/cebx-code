<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — بوابة الأفراد (B2C)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .login-card h1 {
            font-size: 22px;
            margin-bottom: 8px;
            color: #0d9488;
        }
        .login-card .subtitle {
            font-size: 13px;
            color: #777;
            margin-bottom: 28px;
        }
        .badge-b2c {
            display: inline-block;
            background: #0d9488;
            color: #fff;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #444;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 18px;
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #0d9488;
        }
        .error-msg {
            background: #fff3f3;
            border: 1px solid #fcc;
            color: #c00;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: #0d9488;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button[type="submit"]:hover {
            background: #14b8a6;
        }
        .switch-portal {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #888;
        }
        .switch-portal a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <span class="badge-b2c">B2C — بوابة الأفراد</span>
        <h1>تسجيل الدخول</h1>
        <p class="subtitle">أدخل بريدك الإلكتروني وكلمة المرور</p>

        @if($errors->any())
            <div class="error-msg">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('b2c.login.submit') }}">
            @csrf

            <label for="email">البريد الإلكتروني</label>
            <input type="email" id="email" name="email"
                   value="{{ old('email') }}"
                   placeholder="you@example.com"
                   required autofocus>

            <label for="password">كلمة المرور</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••"
                   required>

            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember" style="margin: 0; font-weight: normal;">تذكرني</label>
            </div>

            <button type="submit">تسجيل الدخول</button>
        </form>

        <div class="switch-portal">
            حساب منظمة؟
            <a href="{{ route('b2b.login') }}">سجّل دخول من بوابة B2B</a>
        </div>
    </div>
</body>
</html>
