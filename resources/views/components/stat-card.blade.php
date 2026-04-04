@props(['icon' => '', 'iconSvg' => null, 'label' => '', 'value' => '0', 'trend' => null, 'up' => true, 'meta' => null])
<div {{ $attributes->class('stat-card') }}>
    <div class="stat-card-head">
        @if($iconSvg)
            <span class="stat-icon stat-icon-svg">{!! $iconSvg !!}</span>
        @else
            <span class="stat-icon">{{ $icon }}</span>
        @endif
        @if($trend)
            <span class="stat-trend {{ $up ? 'up' : 'down' }}">{{ $trend }}</span>
        @endif
    </div>
    <div class="stat-value">{{ $value }}</div>
    <div class="stat-label">{{ $label }}</div>
    @if($meta)
        <div class="stat-meta">{{ $meta }}</div>
    @endif
</div>
