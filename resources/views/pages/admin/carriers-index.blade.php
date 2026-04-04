@extends('layouts.app')
@section('title', 'Carrier integrations')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>Carrier integrations</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Carrier integrations</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            Read-only operational visibility for connected carrier APIs, with safe state, mode, health, shipper-account summaries, and masked credential indicators.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.carriers.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.integrations.index') }}" class="btn btn-s">Open full integrations center</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal home</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CAR" label="Connected carriers" :value="number_format($stats['total'])" />
    <x-stat-card icon="ON" label="Enabled" :value="number_format($stats['enabled'])" />
    <x-stat-card icon="CFG" label="Configured" :value="number_format($stats['configured'])" />
    <x-stat-card icon="ALT" label="Needs attention" :value="number_format($stats['attention'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.carriers.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="carrier-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="carrier-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Carrier, provider key, or safe error summary">
        </div>
        <div>
            <label for="carrier-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">State</label>
            <select id="carrier-state" name="state" class="input">
                <option value="">All</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="carrier-health" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Health</label>
            <select id="carrier-health" name="health" class="input">
                <option value="">All</option>
                @foreach($healthOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['health'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.carriers.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-carriers-table">
    <div class="card-title">Visible carrier integrations</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Carrier</th>
                <th>Status</th>
                <th>Connection and test status</th>
                <th>Shipper account</th>
                <th>Masked credentials</th>
                <th>Last error summary</th>
            </tr>
            </thead>
            <tbody>
            @forelse($carriers as $row)
                <tr data-testid="internal-carriers-row">
                    <td>
                        <a href="{{ route('internal.carriers.show', $row['provider_key']) }}" data-testid="internal-carriers-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['provider_name'] }} • {{ $row['provider_key'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['enabled_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['configuration_label'] }} • {{ $row['mode_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['state_badge'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['connection_test_summary']['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['connection_test_summary']['detail'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['shipper_account_summary']['summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['masked_api_summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['last_error_summary']['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['last_error_summary']['detail'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">No carrier integrations match the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $carriers->links() }}</div>
</div>
@endsection
