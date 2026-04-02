@extends('layouts.app')
@section('title', 'Internal Compliance Queue')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <span>Compliance queue</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal compliance queue</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            Read-only operational visibility into dangerous-goods declarations, legal acknowledgement state, and recent compliance review activity. This queue stays sanitized: no raw waiver text, hashes, IP addresses, user agents, or raw audit payloads are exposed here.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.compliance.index') }}" class="btn btn-s">Refresh queue</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal workspace</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CMP" label="Total cases" :value="number_format($stats['total'])" />
    <x-stat-card icon="ATT" label="Needs attention" :value="number_format($stats['attention'])" />
    <x-stat-card icon="LGL" label="Waiver pending" :value="number_format($stats['waiver_pending'])" />
    <x-stat-card icon="DG" label="DG flagged" :value="number_format($stats['dg_flagged'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.compliance.index') }}" style="display:grid;grid-template-columns:2fr repeat(3,minmax(0,1fr)) auto;gap:12px;align-items:end">
        <div>
            <label for="compliance-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="compliance-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Shipment reference, account, owner, or organization">
        </div>

        <div>
            <label for="compliance-type" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Account type</label>
            <select id="compliance-type" name="type" class="input">
                <option value="">All</option>
                <option value="individual" @selected($filters['type'] === 'individual')>Individual</option>
                <option value="organization" @selected($filters['type'] === 'organization')>Organization</option>
            </select>
        </div>

        <div>
            <label for="compliance-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Compliance state</label>
            <select id="compliance-status" name="status" class="input">
                <option value="">All</option>
                @foreach($statusOptions as $statusKey => $statusLabel)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="compliance-review" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Review bucket</label>
            <select id="compliance-review" name="review" class="input">
                <option value="">All</option>
                @foreach($reviewOptions as $reviewKey => $reviewLabel)
                    <option value="{{ $reviewKey }}" @selected($filters['review'] === $reviewKey)>{{ $reviewLabel }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.compliance.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-compliance-table">
    <div class="card-title">Current compliance cases</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Shipment</th>
                <th>Account</th>
                <th>Current state</th>
                <th>Declaration summary</th>
                <th>Legal acknowledgement</th>
                <th>Recent review</th>
            </tr>
            </thead>
            <tbody>
            @forelse($cases as $row)
                <tr data-testid="internal-compliance-row">
                    <td>
                        <a href="{{ route('internal.compliance.show', $row['declaration']) }}" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['shipmentReference'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['shipmentStatus'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['accountLabel'] }}</div>
                        <div style="font-size:12px;color:var(--td)">
                            {{ $row['accountTypeLabel'] }}
                            @if($row['organizationSummary'])
                                • {{ $row['organizationSummary'] }}
                            @else
                                • {{ $row['ownerSummary'] }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['statusLabel'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['reviewLabel'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['declarationSummary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['legalSummary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['latestAuditSummary'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">No compliance cases match the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $cases->links() }}</div>
</div>
@endsection
