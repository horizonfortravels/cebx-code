@extends('layouts.app')
@section('title', 'Internal Shipments')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <span>Shipments</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal shipment read center</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:900px">
            Queue-oriented shipment visibility for internal platform staff. This surface is read-only in I5A and keeps tracking, document, account, and KYC impact summaries together without exposing raw carrier payloads, document storage paths, or private tracking tokens.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.shipments.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal workspace</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="ALL" label="Total shipments" :value="number_format($stats['total'])" />
    <x-stat-card icon="RUN" label="In flight" :value="number_format($stats['in_flight'])" />
    <x-stat-card icon="ACT" label="Needs attention" :value="number_format($stats['requires_attention'])" />
    <x-stat-card icon="KYC" label="KYC blocked" :value="number_format($stats['kyc_blocked'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.shipments.index') }}" data-testid="internal-shipments-filter-form" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="internal-shipments-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="internal-shipments-search" data-testid="internal-shipments-search-input" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Reference, tracking, account, recipient">
        </div>

        <div>
            <label for="internal-shipments-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Workflow status</label>
            <select id="internal-shipments-status" data-testid="internal-shipments-status-filter" name="status" class="input">
                <option value="">All</option>
                @foreach($statusOptions as $statusKey => $statusLabel)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="internal-shipments-carrier" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Carrier</label>
            <select id="internal-shipments-carrier" data-testid="internal-shipments-carrier-filter" name="carrier" class="input">
                <option value="">All</option>
                @foreach($carrierOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($filters['carrier'] === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="internal-shipments-source" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Source</label>
            <select id="internal-shipments-source" data-testid="internal-shipments-source-filter" name="source" class="input">
                <option value="">All</option>
                @foreach($sourceOptions as $sourceKey => $sourceLabel)
                    <option value="{{ $sourceKey }}" @selected($filters['source'] === $sourceKey)>{{ $sourceLabel }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="internal-shipments-international" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">International</label>
            <select id="internal-shipments-international" data-testid="internal-shipments-international-filter" name="international" class="input">
                <option value="">All</option>
                <option value="yes" @selected($filters['international'] === 'yes')>Yes</option>
                <option value="no" @selected($filters['international'] === 'no')>No</option>
            </select>
        </div>

        <div>
            <label for="internal-shipments-cod" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">COD</label>
            <select id="internal-shipments-cod" data-testid="internal-shipments-cod-filter" name="cod" class="input">
                <option value="">All</option>
                <option value="yes" @selected($filters['cod'] === 'yes')>Yes</option>
                <option value="no" @selected($filters['cod'] === 'no')>No</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.shipments.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-shipments-table">
    <div class="card-title">Shipment queue</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Reference</th>
                <th>Account</th>
                <th>Status</th>
                <th>Carrier</th>
                <th>Tracking</th>
                <th>Timeline</th>
                <th>Documents / public tracking</th>
                <th>KYC / restrictions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($shipments as $row)
                <tr data-testid="internal-shipment-row">
                    <td>
                        <a href="{{ route('internal.shipments.show', $row['shipment']) }}" data-testid="internal-shipment-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['shipmentSummary']['reference'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['shipmentSummary']['source_label'] }} • {{ $row['shipmentSummary']['created_at'] ?? '—' }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['accountSummary']['name'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['accountSummary']['type_label'] }} • {{ $row['accountSummary']['slug'] }}</div>
                        <div style="font-size:12px;color:var(--tm)">{{ $row['accountSummary']['owner_label'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['shipmentSummary']['workflow_status_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['shipmentSummary']['normalized_status_label'] }}</div>
                        @if($row['shipmentSummary']['flags'] !== [])
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                                @foreach($row['shipmentSummary']['flags'] as $flag)
                                    <span class="badge">{{ $flag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['carrierSummary']['pair_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">Carrier shipment: {{ $row['carrierSummary']['carrier_shipment_id'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['trackingSummary']['tracking_reference'] }}</div>
                        <div style="font-size:12px;color:var(--td)">AWB: {{ $row['trackingSummary']['awb_number'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['timelinePreview']['label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['timelinePreview']['detail'] }}</div>
                        <div style="font-size:12px;color:var(--tm)">Last update: {{ $row['timelinePreview']['last_updated'] }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['documentsSummary']['label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['publicTracking']['label'] }}</div>
                    </td>
                    <td>
                        @if($row['kycSummary'])
                            <div style="font-weight:700;color:var(--tx)">{{ $row['kycSummary']['label'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['kycSummary']['queue_summary'] }}</div>
                        @else
                            <div style="font-size:12px;color:var(--td)">No linked KYC summary</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty-state">No shipments matched the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $shipments->links() }}</div>
</div>
@endsection
