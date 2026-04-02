@extends('layouts.app')
@section('title', 'Wallet Ledger Detail')

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
            <span>Ledger detail</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Ledger entry detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Read-only visibility into the ledger event, its business reference, and any linked shipment or reservation context.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.billing.show', $account) }}" class="btn btn-s">Back to wallet detail</a>
        @if($canViewAccount)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s">Open linked account detail</a>
        @endif
        @if($canViewShipment && $linkedShipment)
            <a href="{{ route('internal.shipments.show', $linkedShipment['id']) }}" class="btn btn-pr">Open linked shipment</a>
        @endif
    </div>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-billing-ledger-detail-card">
        <div class="card-title">Ledger summary</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Type</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['type'] }}</dd>

            <dt style="color:var(--tm)">Direction</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['direction'] }}</dd>

            <dt style="color:var(--tm)">Amount</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['amount'] }}</dd>

            <dt style="color:var(--tm)">Running balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['running_balance'] }}</dd>

            <dt style="color:var(--tm)">Reference</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['reference'] }}</dd>

            <dt style="color:var(--tm)">Created</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['created_at'] }}</dd>

            <dt style="color:var(--tm)">Note</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['note'] }}</dd>
        </dl>
    </section>

    <section class="card">
        <div class="card-title">Wallet context</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Wallet source</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['source_label'] }}</dd>

            <dt style="color:var(--tm)">Current balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['current_balance'] }}</dd>

            <dt style="color:var(--tm)">Reserved balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['reserved_balance'] }}</dd>

            <dt style="color:var(--tm)">Available balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['available_balance'] }}</dd>
        </dl>
    </section>
</div>

<div class="grid-2">
    <section class="card" data-testid="internal-billing-ledger-linked-shipment-card">
        <div class="card-title">Linked shipment context</div>
        @if($linkedShipment)
            <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">Shipment reference</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['reference'] }}</dd>

                <dt style="color:var(--tm)">Workflow status</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['status'] }}</dd>

                <dt style="color:var(--tm)">Shipment total</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['total_charge'] }}</dd>

                <dt style="color:var(--tm)">Reserved on shipment</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['reserved_amount'] }}</dd>
            </dl>
        @else
            <div class="empty-state">This ledger entry is not currently linked to a shipment.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-billing-ledger-linked-preflight-card">
        <div class="card-title">Linked preflight context</div>
        @if($linkedPreflight)
            <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">Reservation status</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['status'] }}</dd>

                <dt style="color:var(--tm)">Reserved amount</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['amount'] }}</dd>

                <dt style="color:var(--tm)">Source</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['source'] }}</dd>

                <dt style="color:var(--tm)">Outcome</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['outcome'] }}</dd>
            </dl>
            <div style="margin-top:10px">
                <a href="{{ route('internal.billing.preflights.show', ['account' => $account, 'hold' => $linkedPreflight['id']]) }}" class="btn btn-s">Open preflight detail</a>
            </div>
        @else
            <div class="empty-state">This ledger entry is not currently linked to a reservation.</div>
        @endif
    </section>
</div>

@if($linkedTopup)
    <section class="card" data-testid="internal-billing-ledger-linked-topup-card" style="margin-top:24px">
        <div class="card-title">Linked top-up context</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Amount</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['amount'] }}</dd>

            <dt style="color:var(--tm)">Status</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['status'] }}</dd>

            <dt style="color:var(--tm)">Gateway</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['gateway'] }}</dd>

            <dt style="color:var(--tm)">Created</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['created_at'] }}</dd>

            <dt style="color:var(--tm)">Confirmed</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['confirmed_at'] }}</dd>
        </dl>
    </section>
@endif
@endsection
