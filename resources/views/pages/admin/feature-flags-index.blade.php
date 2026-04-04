@extends('layouts.app')
@section('title', 'Internal feature flags')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>Feature flags</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal feature flags</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            Operational visibility for the DB-backed feature-flag catalog. This center shows feature-flag records and their audit history without acting as a hidden environment override console.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.feature-flags.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal home</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="FLG" label="Total flags" :value="number_format($stats['total'])" />
    <x-stat-card icon="ON" label="Enabled" :value="number_format($stats['enabled'])" />
    <x-stat-card icon="CFG" label="Config-backed" :value="number_format($stats['config_backed'])" />
    <x-stat-card icon="TGT" label="Targeted" :value="number_format($stats['targeted'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.feature-flags.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="feature-flag-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="feature-flag-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Name, key, description, or source">
        </div>
        <div>
            <label for="feature-flag-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">State</label>
            <select id="feature-flag-state" name="state" class="input">
                <option value="">All</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="feature-flag-source" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Runtime source</label>
            <select id="feature-flag-source" name="source" class="input">
                <option value="">All</option>
                @foreach($sourceOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.feature-flags.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<section class="card" data-testid="internal-feature-flags-note-card" style="margin-bottom:24px">
    <div class="card-title">Operational note</div>
    <p style="margin:0;color:var(--td);font-size:13px">
        Some platform services still read environment-backed defaults from <code>config/features.php</code>. This center is intentionally limited to the DB-backed feature-flag catalog so operators can inspect current rows safely and audit any explicit internal toggle.
    </p>
</section>

<div class="card" data-testid="internal-feature-flags-table">
    <div class="card-title">Visible feature flags</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Flag</th>
                <th>State</th>
                <th>Rollout</th>
                <th>Targeting</th>
                <th>Runtime source</th>
                <th>Latest audit</th>
            </tr>
            </thead>
            <tbody>
            @forelse($flags as $row)
                <tr data-testid="internal-feature-flags-row">
                    <td>
                        <a href="{{ route('internal.feature-flags.show', $row['route_key']) }}" data-testid="internal-feature-flag-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['key'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['description'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['state_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">Updated {{ $row['updated_at'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['rollout_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ number_format($row['rollout_percentage']) }}%</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['targeting_summary'] }}</div>
                        <div style="font-size:12px;color:var(--td)">
                            Accounts {{ number_format($row['target_account_count']) }} • Plans {{ number_format($row['target_plan_count']) }}
                        </div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['source_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['config_default_label'] }}</div>
                    </td>
                    <td>
                        @if($row['latest_audit'])
                            <div style="font-size:13px;color:var(--tx)">{{ $row['latest_audit']['headline'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['latest_audit']['created_at'] }}</div>
                        @else
                            <div class="empty-state" style="padding:0;border:none;background:none;color:var(--td)">No internal toggle audit yet.</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">No feature flags match the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $flags->links() }}</div>
</div>
@endsection
