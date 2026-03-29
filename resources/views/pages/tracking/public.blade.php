@extends('layouts.public')

@section('title', __('public_tracking.common.page_title'))

@php
    $tracking = $tracking ?? [];
    $events = $tracking['events'] ?? [];
@endphp

@section('content')
    <section class="hero">
        <div class="eyebrow">{{ __('public_tracking.common.portal_eyebrow') }}</div>
        <h1>{{ __('public_tracking.common.headline') }}</h1>
        <p>{{ __('public_tracking.common.description') }}</p>
    </section>

    <section class="content">
        <div class="panel">
            <div class="panel-body" style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
                <div>
                    <div class="metric-label">{{ __('public_tracking.summary.current_status') }}</div>
                    <div class="status-pill">{{ $tracking['current_status_label'] ?? __('public_tracking.common.not_available') }}</div>
                </div>
                <div class="footer-note">{{ __('public_tracking.common.notice') }}</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="grid">
                    <div class="metric">
                        <div class="metric-label">{{ __('public_tracking.summary.tracking_number') }}</div>
                        <div class="metric-value">{{ $tracking['tracking_number_masked'] ?? __('public_tracking.common.not_available') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-label">{{ __('public_tracking.summary.carrier') }}</div>
                        <div class="metric-value">{{ $tracking['carrier_name'] ?? __('public_tracking.common.not_available') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-label">{{ __('public_tracking.summary.last_updated') }}</div>
                        <div class="metric-value">
                            {{ !empty($tracking['last_updated']) ? \Illuminate\Support\Carbon::parse($tracking['last_updated'])->format('Y-m-d H:i') : __('public_tracking.common.not_available') }}
                        </div>
                    </div>
                    <div class="metric">
                        <div class="metric-label">{{ __('public_tracking.summary.route') }}</div>
                        <div class="metric-value">
                            {{ ($tracking['origin_summary'] ?? __('public_tracking.common.not_available')) . ' -> ' . ($tracking['destination_summary'] ?? __('public_tracking.common.not_available')) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px">
                    <div>
                        <div class="metric-label">{{ __('public_tracking.timeline.title') }}</div>
                        <div class="metric-value">{{ number_format((int) ($tracking['events_count'] ?? 0)) }}</div>
                    </div>
                    <div class="metric-label">{{ __('public_tracking.timeline.subtitle') }}</div>
                </div>

                @if($events === [])
                    <div class="empty">{{ __('public_tracking.timeline.empty') }}</div>
                @else
                    <div class="timeline">
                        @foreach($events as $event)
                            <article class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div>
                                    <h2 class="timeline-title">{{ $event['title'] ?? __('public_tracking.common.not_available') }}</h2>
                                    <div class="timeline-meta">
                                        <span>{{ $event['status_label'] ?? __('public_tracking.common.not_available') }}</span>
                                        <span>{{ !empty($event['event_time']) ? \Illuminate\Support\Carbon::parse($event['event_time'])->format('Y-m-d H:i') : __('public_tracking.common.not_available') }}</span>
                                        @if(!empty($event['location']))
                                            <span>{{ $event['location'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
