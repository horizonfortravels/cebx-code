<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ط¥ط¹ط§ط¯ط© طھط¹ظٹظٹظ† ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± â€” Shipping Gateway</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
    <style>
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
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
            align-items: stretch;
        }
        .reset-hero {
            background: #0f3a5f;
            color: #fff;
            padding: clamp(40px, 4vw, 72px) clamp(32px, 4vw, 56px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 18px;
            min-height: 100vh;
        }
        .reset-main {
            padding: clamp(32px, 4vw, 72px) clamp(24px, 3vw, 48px);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .reset-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 20px 45px rgba(15,23,42,.08);
            padding: 32px;
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
        <div style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:20px;background:rgba(255,255,255,.14);font-weight:800;font-size:24px">SG</div>
        <div style="display:inline-block;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.12);font-size:12px;font-weight:700;width:max-content">ط§ط³طھط¹ط§ط¯ط© ط§ظ„ظˆطµظˆظ„</div>
        <h1 style="margin:0;font-size:30px;font-weight:800;line-height:1.4">ط£ط¹ط¯ طھط¹ظٹظٹظ† ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط¨ط£ظ…ط§ظ†</h1>
        <p style="margin:0;font-size:15px;line-height:1.9;opacity:.92;max-width:420px">
            ط§ط³طھط®ط¯ظ… ط§ظ„ط±ط§ط¨ط· ط§ظ„ط°ظٹ ظˆطµظ„ظƒ ط¹ط¨ط± ط§ظ„ط¨ط±ظٹط¯ ظ„ط¥ظ†ط´ط§ط، ظƒظ„ظ…ط© ظ…ط±ظˆط± ط¬ط¯ظٹط¯ط©. ظ„ظ† ظٹطھظ… ط¹ط±ط¶ ط£ظٹ ط±ظ…ط² ط£ظˆ ط³ط± ط¯ط§ط®ظ„ ظ‡ط°ظ‡ ط§ظ„طµظپط­ط©طŒ ظˆط³ظٹطھظ… ط¥ط¨ط·ط§ظ„ ط§ظ„ط¬ظ„ط³ط§طھ ط§ظ„ط³ط§ط¨ظ‚ط© ط¨ط¹ط¯ ط§ظ„ط¥طھظ…ط§ظ….
        </p>
        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;font-size:14px;opacity:.95">
            <li>ط±ط§ط¨ط· ظˆط§ط­ط¯ ظ…ظˆظ‚ظ‘طھ ظ„ظƒظ„ ط·ظ„ط¨ ط¥ط¹ط§ط¯ط© طھط¹ظٹظٹظ†</li>
            <li>طھط£ظƒظٹط¯ ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ظ‚ط¨ظ„ ط§ظ„ط­ظپظ„ظ…</li>
            <li>ط§ظ„ط¹ظˆط¯ط© ط¥ظ„ظ‰ ط¨ظˆط§ط¨ط© ط§ظ„ط¯ط®ظˆظ„ ط§ظ„ظ…ظ†ط§ط³ط¨ط© ط¨ط¹ط¯ ط§ظ„ظ†ط¬ط§ط­</li>
        </ul>
    </section>

    <main class="reset-main">
        <div class="reset-card">
            <h2>ط¥ظ†ط´ط§ط، ظƒظ„ظ…ط© ظ…ط±ظˆط± ط¬ط¯ظٹط¯ط©</h2>
            <p>
                ط£ط¯ط®ظ„ ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ظ…ط±طھط¨ط· ط¨ط§ظ„ط­ط³ط§ط¨ ط«ظ… ط§ط®طھط± ظƒظ„ظ…ط© ظ…ط±ظˆط± ط¬ط¯ظٹط¯ط© ظ‚ظˆظٹط©. ط¨ط¹ط¯ ط§ظ„ط­ظپظ„ظ… ظٹظ…ظƒظ†ظƒ طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„ ظ…ظ† ط§ظ„ط¨ظˆط§ط¨ط© ط§ظ„ظ…ظ†ط§ط³ط¨ط© ظ„ظ†ظˆط¹ ط§ظ„ط­ط³ط§ط¨.
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
                    <label for="reset-email" class="reset-label">ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ</label>
                    <input id="reset-email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" class="reset-input">
                </div>

                <div>
                    <label for="reset-password" class="reset-label">ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط§ظ„ط¬ط¯ظٹط¯ط©</label>
                    <input id="reset-password" type="password" name="password" required autocomplete="new-password" class="reset-input">
                    <div class="reset-hint">ط§ط³طھط®ط¯ظ… ط«ظ…ط§ظ†ظٹط© ط£ط­ط±ظپ ط¹ظ„ظ‰ ط§ظ„ط£ظ‚ظ„ ظ…ط¹ ط£ط­ط±ظپ ظƒط¨ظٹط±ط© ظˆطµط؛ظٹط±ط© ظˆط£ط±ظ‚ط§ظ… ظˆط±ظ…ط² ط®ط§طµ.</div>
                </div>

                <div>
                    <label for="reset-password-confirmation" class="reset-label">طھط£ظƒظٹط¯ ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط±</label>
                    <input id="reset-password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="reset-input">
                </div>

                <button type="submit" class="reset-submit">
                    ط­ظپط¸ ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط§ظ„ط¬ط¯ظٹط¯ط©
                </button>
            </form>

            <div class="reset-link">
                <a href="{{ route('login') }}">ط§ظ„ط¹ظˆط¯ط© ظ„ط§ط®طھظٹط§ط± ط§ظ„ط¨ظˆط§ط¨ط© ط§ظ„ظ…ظ†ط§ط³ط¨ط©</a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
