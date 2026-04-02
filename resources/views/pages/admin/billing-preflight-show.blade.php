@extends('layouts.app')
@section('title', 'Wallet Preflight Detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.billing.index') }}" style="color:inherit;text-decoration:none">Wallet &amp; billing</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.billing.show', $account) }}" style="color:inherit;text-decoration:none">{{ $account->name }}</a>
            <span style="margin:0 6px">/</span>
            <span>Preflight detail</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Preflight reservation detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Read-only visibility into the shipment-linked wallet reservation, current wallet snapshot, and the ledger events tied to this preflight outcome.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.billing.show', $account) }}" class="btn btn-s">Back to wallet detail</a>
        @if($canViewAccount)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s">Open linked account detail</a>
        @endif
        @if($canViewShipment && $shipmentSummary)
            <a href="{{ route('internal.shipments.show', $shipmentSummary['id']) }}" class="btn btn-pr">Open linked shipment</a>
        @endif
    </div>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-billing-preflight-summary-card">
        <div class="card-title">Reservation summary</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Status</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['status'] }}</dd>

            <dt style="color:var(--tm)">Reserved amount</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['amount'] }}</dd>

            <dt style="color:var(--tm)">Source</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['source'] }}</dd>

            <dt style="color:var(--tm)">Created</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['created_at'] }}</dd>

            <dt style="color:var(--tm)">Expires</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['expires_at'] }}</dd>

            <dt style="color:var(--tm)">Captured</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['captured_at'] }}</dd>

            <dt style="color:var(--tm)">Released</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['released_at'] }}</dd>

            <dt style="color:var(--tm)">Outcome</dt>
            <dd style="margin:0;color:var(--tx)">{{ $preflightSummary['outcome'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-billing-preflight-balance-card">
        <div class="card-title">Wallet snapshot</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Current balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $balanceSnapshot['current_balance'] }}</dd>

            <dt style="color:var(--tm)">Reserved balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $balanceSnapshot['reserved_balance'] }}</dd>

            <dt style="color:var(--tm)">Available balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $balanceSnapshot['available_balance'] }}</dd>

            <dt style="color:var(--tm)">Wallet source</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['source_label'] }}</dd>
        </dl>
    </section>
</div>

@if($canManageBillingActions && $staleReleaseAction['is_releasable'])
    <section class="card" data-testid="internal-billing-hold-actions-card" style="margin-bottom:24px">
        <div class="card-title">Stale reservation recovery</div>
        <p style="margin:0 0 14px;color:var(--td);font-size:14px">
            {{ $staleReleaseAction['detail'] }} This action is internal-only, requires a human reason, clears the linked shipment reservation reference when present, and records a billing audit entry.
        </p>
        <form method="POST"
              action="{{ route('internal.billing.preflights.release', ['account' => $account, 'hold' => $preflightSummary['id']]) }}"
              data-testid="internal-billing-release-hold-form"
              style="display:flex;flex-direction:column;gap:12px">
            @csrf
            <label style="display:flex;flex-direction:column;gap:6px">
                <span style="font-size:12px;color:var(--tm)">Operator reason</span>
                <textarea name="reason" rows="3" class="input" style="min-height:88px" required>{{ old('reason') }}</textarea>
            </label>
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                <div style="font-size:12px;color:var(--tm)">{{ $staleReleaseAction['headline'] }}</div>
                <button type="submit" class="btn btn-pr" data-testid="internal-billing-release-hold-button">Release stale reservation</button>
            </div>
        </form>
    </section>
@endif

<div class="grid-2">
    <section class="card" data-testid="internal-billing-preflight-shipment-card">
        <div class="card-title">Linked shipment context</div>
        @if($shipmentSummary)
            <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">Shipment reference</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['reference'] }}</dd>

                <dt style="color:var(--tm)">Workflow status</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['status'] }}</dd>

                <dt style="color:var(--tm)">Shipment total</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['total_charge'] }}</dd>

                <dt style="color:var(--tm)">Reserved on shipment</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['reserved_amount'] }}</dd>
            </dl>
        @else
            <div class="empty-state">No linked shipment context is available for this reservation.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-billing-preflight-ledger-card">
        <div class="card-title">Related ledger events</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($relatedLedgerEntries as $entry)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $entry['type'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $entry['reference'] }}</div>
                        </div>
                        <div style="text-align:left">
                            <div style="font-weight:700;color:var(--tx)">{{ $entry['amount'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $entry['direction'] }}</div>
                        </div>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">Running balance: {{ $entry['running_balance'] }} | {{ $entry['created_at'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $entry['note'] }}</div>
                    <div style="margin-top:10px">
                        <a href="{{ route('internal.billing.ledger.show', ['account' => $account, 'entry' => $entry['id']]) }}" class="btn btn-s">View ledger detail</a>
                    </div>
                </div>
            @empty
                <div class="empty-state">No ledger events are currently linked to this reservation.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
