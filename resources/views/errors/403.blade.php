<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>غير مسموح بالوصول - CBEX Shipping Gateway</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --danger: #dc2626;
            --border: #e2e8f0;
            --primary: #2563eb;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: radial-gradient(circle at top, #eff6ff 0%, var(--bg) 55%);
            color: var(--text);
            font-family: "Tajawal", "IBM Plex Sans Arabic", sans-serif;
        }

        .card {
            width: min(100%, 680px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 32px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(220, 38, 38, 0.08);
            color: var(--danger);
            font-weight: 700;
            font-size: 13px;
        }

        h1 {
            margin: 18px 0 12px;
            font-size: 34px;
            line-height: 1.2;
        }

        p {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.8;
        }

        .actions {
            margin-top: 28px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            padding: 12px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
        }

        .button-primary {
            background: var(--primary);
            color: #fff;
        }

        .button-secondary {
            background: #eef2ff;
            color: #1e293b;
        }

        .code {
            margin-top: 18px;
            font-size: 13px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    @php
        $message = trim((string) ($exception->getMessage() ?? ''));
        $message = $message !== '' ? $message : 'لا يمكنك الوصول إلى هذه الصفحة باستخدام الصلاحيات الحالية.';
    @endphp

    <div class="card">
        <div class="eyebrow">403 <span>وصول مرفوض</span></div>
        <h1>غير مسموح بالوصول</h1>
        <p>{{ $message }}</p>

        <div class="actions">
            <a href="{{ url('/') }}" class="button button-primary">العودة إلى البوابة الرئيسية</a>
            <a href="{{ url()->previous() }}" class="button button-secondary">الرجوع للصفحة السابقة</a>
        </div>

        <div class="code">إذا كنت تعتقد أن هذا المنع غير صحيح، تواصل مع فريق الدعم أو استخدم البوابة المناسبة لحسابك.</div>
    </div>
</body>
</html>
