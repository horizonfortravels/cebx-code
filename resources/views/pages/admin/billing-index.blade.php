@extends('layouts.app')
@section('title', 'Internal Billing')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <span>Wallet &amp; billing</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal wallet and billing read center</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Read-only operational visibility for account wallets, recent ledger activity, preflight reservations, and safe funding summaries. This surface hides payment methods, checkout URLs, gateway metadata, private identifiers, and other unsafe financial internals.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.billing.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal workspace</a>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.billing.index') }}" data-testid="internal-billing-filter-form" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="internal-billing-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="internal-billing-search" data-testid="internal-billing-search-input" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Account, slug, organization, or email">
        </div>

        <div>
            <label for="internal-billing-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Wallet status</label>
            <select id="internal-billing-status" data-testid="internal-billing-status-filter" name="status" class="input">
                <option value="">All</option>
                @foreach($statusOptions as $statusKey => $statusLabel)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="internal-billing-currency" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Currency</label>
            <select id="internal-billing-currency" data-testid="internal-billing-currency-filter" name="currency" class="input">
                <option value="">All</option>
                @foreach($currencyOptions as $currency)
                    <option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="internal-billing-low-balance" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Low balance</label>
            <select id="internal-billing-low-balance" data-testid="internal-billing-low-balance-filter" name="low_balance" class="input">
                <option value="">All</option>
                <option value="yes" @selected($filters['low_balance'] === 'yes')>Yes</option>
                <option value="no" @selected($filters['low_balance'] === 'no')>No</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.billing.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-billing-table">
    <div class="card-title">Account wallets</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Account</th>
                <th>Wallet</th>
                <th>Current balance</th>
                <th>Reserved</th>
                <th>Available</th>
                <th>KYC / restrictions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($accounts as $row)
                <tr data-testid="internal-billing-row">
                    <td>
                        <a href="{{ route('internal.billing.show', $row['account']) }}" data-testid="internal-billing-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['accountLabel'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['accountTypeLabel'] }} @if($row['account']->slug) • {{ $row['account']->slug }} @endif</div>
                        @if($row['organizationSummary'] !== '')
                            <div style="font-size:12px;color:var(--tm)">{{ $row['organizationSummary'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['wallet']['source_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['wallet']['currency'] }} • {{ $row['wallet']['status_label'] }}</div>
                        <div style="font-size:12px;color:var(--tm)">{{ $row['wallet']['summary_note'] }}</div>
                    </td>
                    <td style="font-weight:700;color:var(--tx)">{{ $row['wallet']['current_balance'] }}</td>
                    <td>{{ $row['wallet']['reserved_balance'] }}</td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['wallet']['available_balance'] }}</div>
                        @if($row['wallet']['low_balance'])
                            <div style="font-size:12px;color:#b45309">Low balance threshold reached</div>
                        @endif
                    </td>
                    <td>
                        @if($row['kycSummary'])
                            <div style="font-weight:700;color:var(--tx)">{{ $row['kycSummary']['status_label'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['kycSummary']['queue_summary'] }}</div>
                        @else
                            <div style="font-size:12px;color:var(--td)">No linked KYC summary</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">No account wallets matched the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $accounts->links() }}</div>
</div>
@endsection
