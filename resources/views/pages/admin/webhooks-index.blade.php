@extends('layouts.app')
@section('title', 'Internal webhooks')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>Webhooks</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal webhook operations</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            Read-only delivery visibility for inbound store and tracking webhook endpoints, including recent failures and the narrow safe retry path for failed store deliveries.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.webhooks.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal home</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="WH" label="Total endpoints" :value="number_format($stats['total'])" />
    <x-stat-card icon="ALT" label="Needs attention" :value="number_format($stats['attention'])" />
    <x-stat-card icon="RET" label="Retryable failures" :value="number_format($stats['retryable'])" />
    <x-stat-card icon="TRK" label="Tracking endpoints" :value="number_format($stats['tracking'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.webhooks.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="webhook-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="webhook-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Provider, endpoint, account, or store">
        </div>
        <div>
            <label for="webhook-type" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Endpoint type</label>
            <select id="webhook-type" name="type" class="input">
                <option value="">All</option>
                @foreach($typeOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="webhook-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">State</label>
            <select id="webhook-state" name="state" class="input">
                <option value="">All</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.webhooks.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-webhooks-table">
    <div class="card-title">Visible webhook endpoints</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Endpoint</th>
                <th>Type and context</th>
                <th>State</th>
                <th>Recent delivery</th>
                <th>Failures</th>
                <th>Security summary</th>
            </tr>
            </thead>
            <tbody>
            @forelse($endpoints as $row)
                <tr data-testid="internal-webhooks-row">
                    <td>
                        <a href="{{ route('internal.webhooks.show', $row['route_key']) }}" data-testid="internal-webhooks-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['provider_name'] }} • {{ $row['provider_key'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['endpoint_label'] }}</div>
                        @if($row['account_summary'])
                            <div style="font-size:12px;color:var(--td)">{{ $row['account_summary']['name'] }} • {{ $row['account_summary']['type_label'] }}</div>
                        @else
                            <div style="font-size:12px;color:var(--td)">Platform-scoped inbound endpoint</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['state_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['enabled_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">Attempts {{ number_format($row['attempts_count']) }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['recent_summary'] }}</div>
                        <div style="font-size:12px;color:var(--td)">Last attempt {{ $row['last_attempt_at'] }}</div>
                        <div style="font-size:12px;color:var(--td)">Last success {{ $row['last_success_at'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ number_format($row['failures_count']) }} recent failure(s)</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['failure_summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['security_summary'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">No webhook endpoints match the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $endpoints->links() }}</div>
</div>
@endsection
