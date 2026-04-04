<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ request()->is('b2c/*') || request()->is('b2b/*') || request()->is('notifications') ? __('portal_shipments.errors.external.403.heading') : 'وصول مقيّد' }} - بوابة الشحن CBEX</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
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
                radial-gradient(circle at top right, rgba(37, 99, 235, .10), transparent 34%),
                radial-gradient(circle at bottom left, rgba(15, 118, 110, .08), transparent 30%),
                var(--bg);
            color: var(--text);
            font-family: "Tajawal", "IBM Plex Sans Arabic", sans-serif;
        }
        .panel {
            width: min(100%, 1120px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .08);
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
            background: rgba(255,255,255,.14);
            font-size: 13px;
            font-weight: 700;
        }
        h1 { margin: 16px 0 10px; font-size: 32px; line-height: 1.25; }
        .hero p { margin: 0; font-size: 16px; line-height: 1.9; color: rgba(255,255,255,.9); }
        .body { padding: 28px; }
        .summary {
            margin: 0;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #f8fafc;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.9;
        }
        .actions { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
            padding: 12px 18px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
        }
        .button-primary { background: var(--primary); color: #fff; }
        .button-secondary { background: #e2e8f0; color: #0f172a; }
        .meta { margin-top: 18px; font-size: 13px; color: var(--muted); }

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
@php
    $accountType = (string) optional(auth()->user()?->account)->type;
    $portal = request()->is('b2c/*') || $accountType === 'individual'
        ? 'b2c'
        : ((request()->is('b2b/*') || request()->is('notifications') || $accountType === 'organization') ? 'b2b' : null);
    $isExternalPortal = $portal !== null;
    $primaryRoute = $portal === 'b2c' ? 'b2c.dashboard' : ($portal === 'b2b' ? 'b2b.dashboard' : null);
    $secondaryRoute = $portal === 'b2c' ? 'b2c.shipments.index' : ($portal === 'b2b' ? 'b2b.shipments.index' : null);
    $fallbackMessage = $isExternalPortal
        ? __('portal_shipments.errors.external.403.message')
        : 'هذه الصفحة غير متاحة بصلاحيات الحساب الحالية.';
    $message = trim((string) ($exception->getMessage() ?? ''));
    if ($message === '' || $message === 'This action is unauthorized.') {
        $message = $fallbackMessage;
    }
@endphp
    <div class="panel">
        <div class="hero">
            <div class="eyebrow">{{ $isExternalPortal ? __('portal_shipments.errors.external.403.eyebrow') : '403 وصول مقيّد' }}</div>
            <h1>{{ $isExternalPortal ? __('portal_shipments.errors.external.403.heading') : 'لا يمكنك فتح هذه الصفحة.' }}</h1>
            <p>{{ $message }}</p>
        </div>
        <div class="body">
            <div class="summary">
                {{ $isExternalPortal
                    ? __('portal_shipments.errors.external.403.message')
                    : 'استخدم البوابة الصحيحة أو اطلب الصلاحية المطلوبة قبل المحاولة مرة أخرى.' }}
            </div>
            <div class="actions">
                <a href="{{ $primaryRoute && \Illuminate\Support\Facades\Route::has($primaryRoute) ? route($primaryRoute) : url('/') }}" class="button button-primary">{{ $isExternalPortal ? __('portal_shipments.errors.external.primary_action') : 'العودة إلى الرئيسية' }}</a>
                <a href="{{ $secondaryRoute && \Illuminate\Support\Facades\Route::has($secondaryRoute) ? route($secondaryRoute) : url()->previous() }}" class="button button-secondary">{{ $isExternalPortal ? __('portal_shipments.errors.external.secondary_action') : 'عودة' }}</a>
            </div>
            <div class="meta">HTTP 403</div>
        </div>
    </div>
</body>
</html>
