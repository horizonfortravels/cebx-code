<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'إرشاد الوصول' }} - CBEX</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #1d4ed8;
            --secondary: #e2e8f0;
            --border: #e2e8f0;
            --accent: #dc2626;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 38%),
                radial-gradient(circle at bottom left, rgba(15, 118, 110, 0.10), transparent 34%),
                var(--bg);
            color: var(--text);
            font-family: 'Tajawal', sans-serif;
        }

        .panel {
            width: min(100%, 1120px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero {
            padding: 28px 28px 20px;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: #fff;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 13px;
            font-weight: 700;
        }

        h1 {
            margin: 16px 0 10px;
            font-size: 32px;
            line-height: 1.25;
        }

        .hero p {
            margin: 0;
            font-size: 16px;
            line-height: 1.9;
            color: rgba(255, 255, 255, 0.88);
        }

        .body {
            padding: 28px;
        }

        .next-steps {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 12px;
        }

        .next-steps li {
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #f8fafc;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .button,
        .button-secondary,
        .button-form button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
            padding: 12px 18px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-family: inherit;
            font-size: 15px;
            border: none;
            cursor: pointer;
        }

        .button {
            background: var(--primary);
            color: #fff;
        }

        .button-secondary,
        .button-form button {
            background: var(--secondary);
            color: #0f172a;
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

            .panel {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="hero">
            <div class="eyebrow">{{ $eyebrow ?? 'إرشاد' }}</div>
            <h1>{{ $heading ?? 'تحتاج إلى خطوة مختلفة للمتابعة' }}</h1>
            <p>{{ $message ?? 'راجع البوابة الحالية أو ارجع إلى الصفحة المناسبة لحسابك.' }}</p>
        </div>

        <div class="body">
            <ul class="next-steps">
                <li>إذا كنت تتوقع صفحة مختلفة، ارجع إلى البوابة المخصصة لنوع حسابك أولًا: بوابة الأفراد للحسابات الفردية أو بوابة الأعمال للحسابات المنظمة.</li>
                <li>إذا كان دورك الحالي أقل من المطلوب، اطلب الصلاحية المناسبة بدل المحاولة عبر مسار غير مخصص لك.</li>
                <li>في حال كنت مستخدمًا داخليًا، اختر سياق الحساب فقط عند الحاجة إلى تصفح بيانات عميل محدد.</li>
            </ul>

            <div class="actions">
                @if (! empty($primaryActionUrl))
                    <a href="{{ $primaryActionUrl }}" class="button">{{ $primaryActionLabel ?? 'الانتقال إلى الخطوة التالية' }}</a>
                @endif

                @if (($secondaryActionMethod ?? 'get') === 'post')
                    <form action="{{ $secondaryActionUrl }}" method="POST" class="button-form">
                        @csrf
                        <button type="submit">{{ $secondaryActionLabel ?? 'تنفيذ الإجراء' }}</button>
                    </form>
                @elseif (! empty($secondaryActionUrl))
                    <a href="{{ $secondaryActionUrl }}" class="button-secondary">{{ $secondaryActionLabel ?? 'العودة' }}</a>
                @endif
            </div>

            <div class="meta">الحالة الحالية: {{ $statusCode ?? 403 }}</div>
        </div>
    </div>
</body>
</html>
