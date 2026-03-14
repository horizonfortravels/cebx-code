@props(['icon' => '', 'label' => '', 'value' => '0', 'trend' => null, 'up' => true])
<div class="stat-card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="stat-icon">{{ $icon }}</span>
        @if($trend)
            <span class="stat-trend {{ $up ? 'up' : 'down' }}">{{ $trend }}</span>
        @endif
    </div>
    <div class="stat-value">{{ $value }}</div>
    <div class="stat-label">{{ $label }}</div>
</div>
