{{-- ═══ PWA Meta Tags — Include in <head> ═══ --}}

{{-- Web App Manifest --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">

{{-- Theme Color --}}
<meta name="theme-color" content="#3B82F6">

{{-- iOS Support --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="CBEX">
<link rel="apple-touch-icon" href="{{ asset('icons/icon-152x152.png') }}">
<link rel="apple-touch-icon" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
<link rel="apple-touch-icon" sizes="512x512" href="{{ asset('icons/icon-512x512.png') }}">

{{-- Windows Tiles --}}
<meta name="msapplication-TileColor" content="#0B0F1A">
<meta name="msapplication-TileImage" content="{{ asset('icons/icon-144x144.png') }}">

{{-- Favicon --}}
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/icon-72x72.png') }}">

{{-- PWA Description --}}
<meta name="description" content="CBEX Group — بوابة إدارة الشحن واللوجستيات">
<meta name="mobile-web-app-capable" content="yes">
