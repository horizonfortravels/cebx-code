@props(['icon' => '', 'iconSvg' => null, 'iconName' => null, 'label' => '', 'value' => '0', 'trend' => null, 'up' => true, 'meta' => null, 'eyebrow' => null])
<div {{ $attributes->class('stat-card') }}>
    <div class="stat-card-head">
        @if($iconSvg)
            <span class="stat-icon stat-icon-svg">{!! $iconSvg !!}</span>
        @elseif($iconName)
            <span class="stat-icon stat-icon-svg"><x-portal-icon :name="$iconName" /></span>
        @else
            <span class="stat-icon">{{ $icon }}</span>
        @endif
        @if($trend)
            <span class="stat-trend {{ $up ? 'up' : 'down' }}">{{ $trend }}</span>
        @endif
    </div>
    <div class="stat-card-copy">
        @if($eyebrow)
            <div class="stat-eyebrow">{{ $eyebrow }}</div>
        @endif
        <div class="stat-value">{{ $value }}</div>
        <div class="stat-label">{{ $label }}</div>
    </div>
    @if($meta)
        <div class="stat-meta">{{ $meta }}</div>
    @endif
</div>
