@extends('layouts.app')
@section('title', 'Integration detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.integrations.index') }}" style="color:inherit;text-decoration:none">Integrations</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Integration detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            {{ $detail['name'] }} • {{ $detail['type_label'] }} • {{ $detail['provider_name'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($detail['account_summary'] && $canViewAccount)
            <a href="{{ route('internal.accounts.show', $detail['account_summary']['account']) }}" class="btn btn-s" data-testid="internal-integration-account-link">Open linked account</a>
        @endif
        <a href="{{ route('internal.integrations.index') }}" class="btn btn-pr">Back to integrations</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="INT" label="Provider" :value="$detail['provider_name']" />
    <x-stat-card icon="TYP" label="Type" :value="$detail['type_label']" />
    <x-stat-card icon="ON" label="State" :value="$detail['enabled_label']" />
    <x-stat-card icon="HLT" label="Health" :value="$detail['health_label']" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-integration-summary-card">
        <div class="card-title">Integration summary</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Display name</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">Provider key</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['provider_key'] }}</dd>
            <dt style="color:var(--tm)">Integration type</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['type_label'] }}</dd>
            <dt style="color:var(--tm)">Enabled state</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['enabled_label'] }}</dd>
            <dt style="color:var(--tm)">Configuration</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['configuration_label'] }}</dd>
            <dt style="color:var(--tm)">Operational state</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['state_badge'] }}</dd>
            @if($detail['account_summary'])
                <dt style="color:var(--tm)">Linked account</dt>
                <dd style="margin:0;color:var(--tx)">{{ $detail['account_summary']['name'] }} • {{ $detail['account_summary']['type_label'] }}</dd>
            @endif
            @foreach($detail['metadata'] as $label => $value)
                @continue($value === null || $value === '')
                <dt style="color:var(--tm)">{{ \Illuminate\Support\Str::headline($label) }}</dt>
                <dd style="margin:0;color:var(--tx)">{{ $value }}</dd>
            @endforeach
        </dl>
    </section>

    <section class="card" data-testid="internal-integration-health-card">
        <div class="card-title">Health and service status</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="font-weight:700;color:var(--tx)">{{ $detail['health_label'] }}</div>
            <div style="font-size:13px;color:var(--td)">{{ $detail['health_summary']['checked_at'] }}</div>
            <div class="grid-3">
                <div>
                    <div style="font-size:12px;color:var(--tm)">Last check</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['checked_at'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">Response time</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['response_time'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">Requests</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['request_summary'] }}</div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-integration-activity-card">
        <div class="card-title">Recent activity</div>
        <div style="font-weight:700;color:var(--tx);margin-bottom:8px">{{ $detail['activity_summary']['headline'] }}</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:14px">{{ $detail['activity_summary']['detail'] }}</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($detail['activity_summary']['items'] as $item)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-size:12px;color:var(--tm)">{{ $item['label'] }}</div>
                    <div style="color:var(--tx)">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card" data-testid="internal-integration-feature-flags-card">
        <div class="card-title">Feature flags</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($detail['feature_flags'] as $item)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $item['label'] }}</div>
                    <div style="font-size:12px;color:var(--td)">{{ $item['key'] }}</div>
                    <div style="font-size:13px;color:var(--tx);margin-top:6px">{{ $item['state_label'] }}</div>
                </div>
            @empty
                <div class="empty-state">No linked feature flags are currently defined for this integration.</div>
            @endforelse
        </div>
    </section>
</div>

@if($canViewCredentials)
    <section class="card" data-testid="internal-integration-credentials-card">
        <div class="card-title">Masked credential summary</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:14px">{{ $detail['masked_api_summary'] }}</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            @forelse($detail['credentials']['items'] as $item)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-size:12px;color:var(--tm)">{{ $item['label'] }}</div>
                    <div style="color:var(--tx)">{{ $item['value'] }}</div>
                </div>
            @empty
                <div class="empty-state">No configured credential fields are visible for this integration.</div>
            @endforelse
        </div>
    </section>
@endif
@endsection
