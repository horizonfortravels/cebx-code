{{-- resources/views/components/timeline.blade.php --}}
@props(['items' => [], 'teal' => false])
<div {{ $attributes->class(['timeline', $teal ? 'timeline--teal' : 'timeline--primary']) }}>
    <div class="timeline-track" aria-hidden="true"></div>
    @foreach($items as $item)
        <article class="timeline-item {{ $loop->first ? 'is-current' : '' }}">
            <div class="timeline-dot" aria-hidden="true"></div>
            <div class="timeline-content">
                <div class="timeline-title">{{ $item['title'] }}</div>
                <div class="timeline-date">{{ $item['date'] }}</div>
                @if(isset($item['location']))
                    <div class="timeline-meta">
                        <span class="timeline-meta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20.25s6-5.52 6-10a6 6 0 1 0-12 0c0 4.48 6 10 6 10Z"></path>
                                <circle cx="12" cy="10.25" r="2.25"></circle>
                            </svg>
                        </span>
                        <span>{{ $item['location'] }}</span>
                    </div>
                @endif
                @if(isset($item['desc']))
                    <div class="timeline-desc">{{ $item['desc'] }}</div>
                @endif
                @if(!empty($item['details']) && is_array($item['details']))
                    <div class="timeline-details">
                        @foreach($item['details'] as $detail)
                            @if(filled($detail['value'] ?? null))
                                <div class="timeline-detail">
                                    <div class="timeline-detail__label">{{ $detail['label'] }}</div>
                                    <div class="timeline-detail__value">{{ $detail['value'] }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </article>
    @endforeach
</div>
