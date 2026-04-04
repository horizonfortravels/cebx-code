@extends('layouts.app')
@section('title', $dashboard['title'])

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.reports.index') }}" style="color:inherit;text-decoration:none">Reports &amp; analytics</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $dashboard['title'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">{{ $dashboard['title'] }}</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            {{ $dashboard['description'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.reports.index') }}" class="btn btn-s">Back to reports hub</a>
        @if($canExport ?? false)
            <a href="{{ route('internal.reports.' . $dashboard['key'] . '.export', request()->query()) }}"
               class="btn btn-s"
               data-testid="internal-report-dashboard-export-link">
                Export CSV
            </a>
        @endif
        @if($drilldowns->isNotEmpty())
            <a href="{{ route($drilldowns->first()['route_name']) }}" class="btn btn-pr" data-testid="internal-report-dashboard-primary-link">Open linked center</a>
        @endif
    </div>
</div>

<div class="card" data-testid="internal-report-dashboard" style="margin-bottom:24px;background:#f8fafc">
    <div class="card-title">{{ $dashboard['eyebrow'] }}</div>
    <p style="margin:0;color:var(--td);line-height:1.8">
        These dashboard metrics are read-only operational summaries. No ticket, shipment, billing, compliance, or KYC mutations are exposed from this dashboard surface.
    </p>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($dashboard['metrics'] as $metric)
        <x-stat-card icon="RPT" :label="$metric['label']" :value="$metric['display'] ?? number_format($metric['value'])" />
    @endforeach
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:24px">
    @foreach($dashboard['breakdowns'] as $group)
        <div class="card" data-testid="internal-report-breakdown-card">
            <div class="card-title">{{ $group['title'] }}</div>
            <div style="display:grid;gap:10px">
                @foreach($group['items'] as $item)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border:1px solid var(--bd);border-radius:12px;background:#fff">
                        <div>
                            <div style="font-size:13px;color:var(--td)">{{ $item['label'] }}</div>
                            @if(!empty($item['detail']))
                                <div style="font-size:12px;color:var(--tm);margin-top:4px">{{ $item['detail'] }}</div>
                            @endif
                        </div>
                        <span style="font-size:20px;font-weight:800;color:var(--tx);text-align:right">{{ $item['display'] ?? number_format($item['value']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="card" data-testid="internal-report-trend-card">
        <div class="card-title">{{ $dashboard['trend']['title'] }}</div>
        <p style="margin:0 0 14px;color:var(--td);line-height:1.8">{{ $dashboard['trend']['summary'] }}</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(90px,1fr));gap:10px">
            @foreach($dashboard['trend']['points'] as $point)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:14px;background:#fff;text-align:center">
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">{{ $point['label'] }}</div>
                    <div style="font-size:20px;font-weight:800;color:var(--tx)">{{ number_format($point['value']) }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
    <div class="card" data-testid="internal-report-actions-card">
        <div class="card-title">Action-oriented summaries</div>
        <div style="display:grid;gap:12px">
            @foreach($dashboard['action_summaries'] as $summary)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:14px;background:#fff">
                    <div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:6px">{{ $summary['title'] }}</div>
                    <div style="font-size:13px;color:var(--td);line-height:1.8">{{ $summary['detail'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card" data-testid="internal-report-drilldown-card">
        <div class="card-title">Safe drilldowns</div>
        <p style="margin:0 0 14px;color:var(--td);line-height:1.8">
            Open the linked internal centers for deeper queue handling. These links still respect each target center’s own role gates and stay read-only here.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            @foreach($drilldowns as $index => $link)
                <a href="{{ route($link['route_name']) }}"
                   class="btn {{ $index === 0 ? 'btn-pr' : 'btn-s' }}"
                   data-testid="internal-report-drilldown-link-{{ $index }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
