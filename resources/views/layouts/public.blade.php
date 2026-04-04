<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('public_tracking.common.page_title')) - بوابة الشحن CBEX</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-soft: rgba(255, 255, 255, 0.82);
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(148, 163, 184, 0.22);
            --primary: #0f766e;
            --primary-strong: #115e59;
            --accent: #0f172a;
            --warning: #b45309;
            --page-max: 1520px;
            --page-gutter: 24px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Tajawal", "IBM Plex Sans Arabic", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top right, rgba(15, 118, 110, .18), transparent 28%),
                radial-gradient(circle at bottom left, rgba(15, 23, 42, .10), transparent 30%),
                linear-gradient(180deg, #f8fafc 0%, #eef6f6 100%);
        }

        .shell {
            width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
            padding: 0;
        }

        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, rgba(15, 23, 42, .96) 0%, rgba(15, 118, 110, .92) 100%);
            color: #fff;
        }

        .content-section {
            padding: 24px 0 40px;
        }

        .hero-inner {
            width: min(var(--page-max), calc(100% - (var(--page-gutter) * 2)));
            margin: 0 auto;
            padding: 24px 28px;
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: 0 26px 70px rgba(15, 23, 42, .14);
            background: linear-gradient(135deg, rgba(15, 23, 42, .96) 0%, rgba(15, 118, 110, .92) 100%);
        }

        .content-inner {
            width: min(var(--page-max), calc(100% - (var(--page-gutter) * 2)));
            margin: 0 auto;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            font-size: 13px;
            font-weight: 700;
        }

        .hero h1 {
            margin: 18px 0 10px;
            font-size: clamp(30px, 4vw, 46px);
            line-height: 1.1;
        }

        .hero p {
            max-width: 760px;
            margin: 0;
            font-size: 16px;
            line-height: 1.85;
            color: rgba(255,255,255,.9);
        }

        .content {
            display: grid;
            gap: 20px;
        }

        .panel {
            border: 1px solid var(--border);
            border-radius: 24px;
            background: var(--surface-soft);
            backdrop-filter: blur(12px);
            box-shadow: 0 18px 48px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .panel-body {
            padding: 24px 26px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .metric {
            padding: 16px;
            border-radius: 18px;
            background: rgba(255,255,255,.82);
            border: 1px solid rgba(148, 163, 184, .18);
        }

        .metric-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 6px;
        }

        .metric-value {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(15, 118, 110, .10);
            color: var(--primary-strong);
            font-weight: 800;
            font-size: 14px;
        }

        .timeline {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .timeline-item {
            display: grid;
            grid-template-columns: 14px 1fr;
            gap: 14px;
            padding: 18px;
            border-radius: 20px;
            background: rgba(255,255,255,.92);
            border: 1px solid rgba(148, 163, 184, .16);
        }

        .timeline-dot {
            width: 14px;
            height: 14px;
            border-radius: 999px;
            margin-top: 6px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-strong) 100%);
            box-shadow: 0 0 0 6px rgba(15, 118, 110, .10);
        }

        .timeline-title {
            font-size: 18px;
            font-weight: 800;
            margin: 0;
        }

        .timeline-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 10px;
            color: var(--muted);
            font-size: 14px;
        }

        .empty {
            padding: 22px;
            border-radius: 18px;
            border: 1px dashed rgba(148, 163, 184, .4);
            color: var(--muted);
            background: rgba(255,255,255,.72);
            text-align: center;
        }

        .footer-note {
            color: var(--warning);
            font-size: 14px;
            line-height: 1.8;
        }

        @media (max-width: 720px) {
            .shell {
                padding-inline: 0;
            }

            .hero-inner,
            .content-inner {
                width: min(100%, calc(100% - 20px));
            }

            .hero,
            .panel-body {
                padding: 20px;
            }

            .hero {
                min-height: auto;
            }

            .content-section {
                padding: 18px 0 24px;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        @yield('content')
    </div>
</body>
</html>
