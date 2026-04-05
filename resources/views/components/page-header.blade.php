{{-- resources/views/components/page-header.blade.php --}}
@props(['title', 'subtitle' => null, 'eyebrow' => null, 'meta' => null])
<div {{ $attributes->class('page-header') }}>
    <div class="page-header-copy">
        @if($eyebrow)
            <div class="page-header-eyebrow">{{ $eyebrow }}</div>
        @endif
        <h1 class="page-header-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-header-subtitle">{{ $subtitle }}</p>
        @endif
        @if($meta)
            <div class="page-header-meta">{{ $meta }}</div>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="page-header-actions">{{ $slot }}</div>
    @endif
</div>
