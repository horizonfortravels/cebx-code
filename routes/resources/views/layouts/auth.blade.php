<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'تسجيل الدخول') — CBEX Shipping Gateway</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    {{-- ═══ PWA Meta Tags ═══ --}}
    @include('components.pwa-meta')
</head>
<body>
    @yield('content')

    {{-- ═══ PWA Registration ═══ --}}
    <script src="{{ asset('js/pwa.js') }}"></script>
</body>
</html>
