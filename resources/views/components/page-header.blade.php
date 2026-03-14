{{-- resources/views/components/page-header.blade.php --}}
@props(['title', 'subtitle' => null])
<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if($subtitle)<p class="subtitle">{{ $subtitle }}</p>@endif
    </div>
    @if($slot->isNotEmpty())
        <div class="actions">{{ $slot }}</div>
    @endif
</div>
