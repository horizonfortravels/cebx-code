@props([
    'pageTitle' => 'إرشاد الوصول',
    'variant' => 'neutral',
    'icon' => 'alert',
    'eyebrow' => null,
    'heading' => null,
    'message' => null,
    'summary' => null,
    'statusCode' => null,
    'primaryActionUrl' => null,
    'primaryActionLabel' => null,
    'secondaryActionUrl' => null,
    'secondaryActionLabel' => null,
    'secondaryActionMethod' => 'get',
    'meta' => null,
])

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }} - بوابة الشحن CBEX</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
</head>
<body class="browser-safe-page browser-safe-page--{{ $variant }}">
    <main class="browser-safe-shell">
        <section class="browser-safe-card">
            <header class="browser-safe-hero">
                <div class="browser-safe-hero__meta">
                    @if($eyebrow)
                        <span class="browser-safe-eyebrow">{{ $eyebrow }}</span>
                    @endif

                    @if($statusCode)
                        <span class="browser-safe-status">HTTP {{ $statusCode }}</span>
                    @endif
                </div>

                <div class="browser-safe-hero__body">
                    <span class="browser-safe-icon" aria-hidden="true">
                        <x-portal-icon :name="$icon" />
                    </span>

                    <div class="browser-safe-hero__copy">
                        @if($heading)
                            <h1 class="browser-safe-title">{{ $heading }}</h1>
                        @endif

                        @if($message)
                            <p class="browser-safe-message">{{ $message }}</p>
                        @endif
                    </div>
                </div>
            </header>

            <div class="browser-safe-body">
                @if($summary)
                    <div class="browser-safe-summary">{{ $summary }}</div>
                @endif

                @if(trim((string) $slot) !== '')
                    <div class="browser-safe-content">
                        {{ $slot }}
                    </div>
                @endif

                @if($primaryActionUrl || $secondaryActionUrl)
                    <div class="browser-safe-actions">
                        @if($primaryActionUrl)
                            <a href="{{ $primaryActionUrl }}" class="browser-safe-button browser-safe-button--primary">{{ $primaryActionLabel }}</a>
                        @endif

                        @if(($secondaryActionMethod ?? 'get') === 'post' && $secondaryActionUrl)
                            <form action="{{ $secondaryActionUrl }}" method="POST" class="browser-safe-form">
                                @csrf
                                <button type="submit" class="browser-safe-button browser-safe-button--secondary">{{ $secondaryActionLabel }}</button>
                            </form>
                        @elseif($secondaryActionUrl)
                            <a href="{{ $secondaryActionUrl }}" class="browser-safe-button browser-safe-button--secondary">{{ $secondaryActionLabel }}</a>
                        @endif
                    </div>
                @endif

                @if($meta)
                    <div class="browser-safe-meta">{{ $meta }}</div>
                @endif
            </div>
        </section>
    </main>

    <script>window.PWA={swUrl:'{{ asset("sw.js") }}',scope:'{{ rtrim(url("/"), "/") }}/'};</script>
    <script src="{{ asset('js/pwa.js') }}" defer></script>
</body>
</html>
