@extends('layouts.app')
@section('title', 'Wallet Detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.billing.index') }}" style="color:inherit;text-decoration:none">Wallet &amp; billing</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $account->name }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Account wallet detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Read-only balance, ledger, preflight, and shipment-linked wallet visibility for operational staff. This surface intentionally excludes payment methods, checkout URLs, gateway payloads, raw metadata, and other unsafe billing internals.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.billing.index') }}" class="btn btn-s">Back to billing queue</a>
        @if($canViewAccount)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s" data-testid="internal-billing-account-link">Open linked account detail</a>
        @endif
        <a href="{{ route('internal.billing.show', $account) }}" class="btn btn-pr">Refresh detail</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="BAL" label="Current balance" :value="$walletSummary['current_balance']" />
    <x-stat-card icon="RSV" label="Reserved balance" :value="$walletSummary['reserved_balance']" />
    <x-stat-card icon="AVL" label="Available balance" :value="$walletSummary['available_balance']" />
    <x-stat-card icon="STS" label="Wallet status" :value="$walletSummary['status_label']" />
</div>

@if($walletBackfillOnly)
    <div class="card" style="margin-bottom:24px;border-color:#f59e0b">
        <div class="card-title">Legacy wallet fallback</div>
        <p style="margin:0;color:var(--td);font-size:13px">
            This account currently resolves through the legacy wallet fallback only. Read-only balance visibility remains available, but ledger, preflight, and funding panels stay intentionally empty until the billing wallet source of truth is present.
        </p>
    </div>
@endif

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-billing-summary-card">
        <div class="card-title">Wallet summary</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Account</dt>
            <dd style="margin:0;color:var(--tx)">{{ $account->name }}</dd>

            <dt style="color:var(--tm)">Account type</dt>
            <dd style="margin:0;color:var(--tx)">{{ $account->isOrganization() ? 'Organization' : 'Individual' }}</dd>

            <dt style="color:var(--tm)">Wallet source</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['source_label'] }}</dd>

            <dt style="color:var(--tm)">Currency</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['currency'] }}</dd>

            <dt style="color:var(--tm)">Current balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['current_balance'] }}</dd>

            <dt style="color:var(--tm)">Reserved balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['reserved_balance'] }}</dd>

            <dt style="color:var(--tm)">Available balance</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['available_balance'] }}</dd>

            <dt style="color:var(--tm)">Total credited</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['total_credited'] }}</dd>

            <dt style="color:var(--tm)">Total debited</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['total_debited'] }}</dd>

            <dt style="color:var(--tm)">Summary note</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['summary_note'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-billing-kyc-card">
        <div class="card-title">KYC and restriction context</div>
        @if($kycSummary)
            <div style="display:flex;flex-direction:column;gap:12px">
                <div>
                    <div style="font-size:12px;color:var(--tm)">Current KYC status</div>
                    <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['status_label'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">Operational effect</div>
                    <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['queue_summary'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">Next action</div>
                    <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['action_label'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">Restriction overlays</div>
                    @if($kycSummary['restriction_names'] !== [])
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
                            @foreach($kycSummary['restriction_names'] as $restrictionName)
                                <span class="badge">{{ $restrictionName }}</span>
                            @endforeach
                        </div>
                    @else
                        <div style="color:var(--tx)">No named restriction overlays are active.</div>
                    @endif
                </div>
            </div>
        @else
            <div class="empty-state">No linked KYC summary is currently available for this account.</div>
        @endif
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-billing-ledger-card">
        <div class="card-title">Recent ledger summary</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($ledgerEntries as $entry)
                <div data-testid="internal-billing-ledger-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
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
                        <a href="{{ route('internal.billing.ledger.show', ['account' => $account, 'entry' => $entry['id']]) }}" class="btn btn-s" data-testid="internal-billing-ledger-detail-link">View ledger detail</a>
                    </div>
                </div>
            @empty
                <div class="empty-state">No safe ledger summary entries are currently visible.</div>
            @endforelse
        </div>
    </section>

    <section class="card" data-testid="internal-billing-topups-card">
        <div class="card-title">Recent top-ups and adjustments</div>

        <div style="margin-bottom:12px">
            <div style="font-size:12px;color:var(--tm);margin-bottom:8px">Top-ups</div>
            <div style="display:flex;flex-direction:column;gap:10px">
                @forelse($topups as $topup)
                    <div data-testid="internal-billing-topup-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <div>
                                <div style="font-weight:700;color:var(--tx)">{{ $topup['amount'] }}</div>
                                <div style="font-size:12px;color:var(--td)">{{ $topup['gateway'] }}</div>
                            </div>
                            <span class="badge">{{ $topup['status'] }}</span>
                        </div>
                        <div style="font-size:12px;color:var(--tm);margin-top:8px">Created: {{ $topup['created_at'] }} | Confirmed: {{ $topup['confirmed_at'] }}</div>
                    </div>
                @empty
                    <div class="empty-state">No recent top-up summary is available.</div>
                @endforelse
            </div>
        </div>

        <div>
            <div style="font-size:12px;color:var(--tm);margin-bottom:8px">Adjustments</div>
            <div style="display:flex;flex-direction:column;gap:10px">
                @forelse($adjustments as $adjustment)
                    <div data-testid="internal-billing-adjustment-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <div style="font-weight:700;color:var(--tx)">{{ $adjustment['amount'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $adjustment['direction'] }}</div>
                        </div>
                        <div style="font-size:12px;color:var(--tm);margin-top:8px">{{ $adjustment['created_at'] }}</div>
                        <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $adjustment['note'] }}</div>
                    </div>
                @empty
                    <div class="empty-state">No recent adjustment summary is available.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>

<div class="grid-2">
    <section class="card" data-testid="internal-billing-holds-card">
        <div class="card-title">Recent preflight reservations</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($holds as $hold)
                <div data-testid="internal-billing-hold-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $hold['shipment_reference'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $hold['source'] }}</div>
                        </div>
                        <div style="text-align:left">
                            <div style="font-weight:700;color:var(--tx)">{{ $hold['amount'] }}</div>
                            <span class="badge">{{ $hold['status'] }}</span>
                        </div>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">Shipment status: {{ $hold['shipment_status'] }} | Shipment total: {{ $hold['shipment_total'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $hold['outcome'] }}</div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">Created: {{ $hold['created_at'] }} | Expires: {{ $hold['expires_at'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">Captured: {{ $hold['captured_at'] }} | Released: {{ $hold['released_at'] }}</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                        <a href="{{ route('internal.billing.preflights.show', ['account' => $account, 'hold' => $hold['id']]) }}" class="btn btn-s" data-testid="internal-billing-hold-detail-link">View preflight detail</a>
                        @if($canViewShipment && $hold['shipment_id'] !== '')
                            <a href="{{ route('internal.shipments.show', $hold['shipment_id']) }}" class="btn btn-s">Open linked shipment</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">No recent preflight reservation summary is available.</div>
            @endforelse
        </div>
    </section>

    <section class="card" data-testid="internal-billing-shipment-events-card">
        <div class="card-title">Shipment-linked wallet events</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($shipmentWalletEvents as $event)
                <div data-testid="internal-billing-shipment-event-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $event['label'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $event['shipment_reference'] }}</div>
                        </div>
                        <div style="font-weight:700;color:var(--tx)">{{ $event['amount'] }}</div>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">{{ $event['shipment_status'] }} | {{ $event['created_at'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $event['note'] }}</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                        <a href="{{ route('internal.billing.ledger.show', ['account' => $account, 'entry' => $event['ledger_id']]) }}" class="btn btn-s" data-testid="internal-billing-shipment-event-ledger-link">View ledger detail</a>
                        @if($canViewShipment && $event['shipment_id'] !== '')
                            <a href="{{ route('internal.shipments.show', $event['shipment_id']) }}" class="btn btn-s">Open linked shipment</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">No recent shipment-linked wallet events are visible yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
