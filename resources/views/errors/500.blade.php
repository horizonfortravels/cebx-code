<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حدث خطأ غير متوقع - بوابة الشحن CBEX</title>
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
            background:
                radial-gradient(circle at top, rgba(37, 99, 235, 0.10), transparent 42%),
                radial-gradient(circle at bottom left, rgba(220, 38, 38, 0.08), transparent 30%),
                var(--bg);
            color: var(--text);
            font-family: "Tajawal", sans-serif;
        }

        .card {
            width: min(100%, 1120px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
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
            line-height: 1.9;
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
            min-width: 160px;
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

        .meta {
            margin-top: 18px;
            font-size: 13px;
            color: var(--muted);
        }

        @media (max-width: 720px) {
            body {
                padding: 16px;
            }

            .card {
                width: 100%;
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="eyebrow">500 <span>خطأ غير متوقع</span></div>
        <h1>تعذر إكمال هذه الصفحة الآن</h1>
        <p>
            واجهت المنصة مشكلة غير متوقعة أثناء تحميل هذه الصفحة. لم يتم عرض تفاصيل تقنية للمستخدم حفاظًا على الوضوح والأمان.
            يمكنك العودة إلى الصفحة السابقة أو الرجوع إلى البوابة الرئيسية ثم المحاولة مرة أخرى.
        </p>

        <div class="actions">
            <a href="{{ url('/') }}" class="button button-primary">العودة إلى البوابة الرئيسية</a>
            <a href="{{ url()->previous() }}" class="button button-secondary">الرجوع للصفحة السابقة</a>
        </div>

        <div class="meta">إذا استمر الخطأ، شارك فريق التشغيل باسم الصفحة التي كنت تحاول فتحها فقط دون الحاجة لنسخ أي تفاصيل تقنية.</div>
    </div>
</body>
</html>
