@extends('layouts.app')
@section('title', 'Internal API keys')

@php
    $selectedScopes = collect(old('scopes', []))->map(fn ($scope) => strtolower(trim((string) $scope)))->all();
@endphp

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>API keys</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal API keys</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            Operational visibility for internal platform and integration API keys, with masked prefixes, scope summaries, account linkage, and one-time secret display only during safe create or rotate actions.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.api-keys.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal home</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="KEY" label="Total keys" :value="number_format($stats['total'])" />
    <x-stat-card icon="ON" label="Active" :value="number_format($stats['active'])" />
    <x-stat-card icon="REV" label="Revoked" :value="number_format($stats['revoked'])" />
    <x-stat-card icon="EXP" label="Expiring soon" :value="number_format($stats['expiring'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.api-keys.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="api-key-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="api-key-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Key name, prefix, account, or scope">
        </div>
        <div>
            <label for="api-key-state" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">State</label>
            <select id="api-key-state" name="state" class="input">
                <option value="">All</option>
                @foreach($stateOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="api-key-scope" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Scope</label>
            <select id="api-key-scope" name="scope" class="input">
                <option value="">All</option>
                @foreach($scopeOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['scope'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="api-key-account" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Account</label>
            <select id="api-key-account" name="account" class="input">
                <option value="">All</option>
                @foreach($accountOptions as $account)
                    <option value="{{ $account->id }}" @selected($filters['account'] === (string) $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.api-keys.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

@if($canManageKeys)
    <section class="card" data-testid="internal-api-key-create-form" style="margin-bottom:24px">
        <div class="card-title">Create internal API key</div>
        <form method="POST" action="{{ route('internal.api-keys.store') }}" class="form-grid-2">
            @csrf
            <div>
                <label for="api-key-account-id" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Account</label>
                <select id="api-key-account-id" name="account_id" class="input" required>
                    <option value="">Select account</option>
                    @foreach($accountOptions as $account)
                        <option value="{{ $account->id }}" @selected(old('account_id') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="api-key-name" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Key name</label>
                <input id="api-key-name" type="text" name="name" value="{{ old('name') }}" class="input" maxlength="200" placeholder="Support automation key" required>
            </div>
            <div style="grid-column:1 / -1">
                <div style="font-size:12px;color:var(--tm);margin-bottom:8px">Safe scopes</div>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    @foreach($scopeOptions as $key => $label)
                        <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--bd);border-radius:12px">
                            <input type="checkbox" name="scopes[]" value="{{ $key }}" @checked(in_array($key, $selectedScopes, true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div style="grid-column:1 / -1">
                <label for="api-key-create-reason" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Internal reason</label>
                <textarea id="api-key-create-reason" name="reason" rows="3" class="input" maxlength="500" placeholder="Explain why this internal key is needed." required>{{ old('reason') }}</textarea>
            </div>
            <div style="grid-column:1 / -1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-pr" data-testid="internal-api-key-create-button">Create masked key</button>
            </div>
        </form>
    </section>
@endif

<div class="card" data-testid="internal-api-keys-table">
    <div class="card-title">Visible API keys</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Key</th>
                <th>Account</th>
                <th>State</th>
                <th>Scopes</th>
                <th>Security summary</th>
                <th>Last use</th>
            </tr>
            </thead>
            <tbody>
            @forelse($keys as $row)
                <tr data-testid="internal-api-keys-row">
                    <td>
                        <a href="{{ route('internal.api-keys.show', $row['route_key']) }}" data-testid="internal-api-key-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['name'] }}
                        </a>
                        <div style="font-size:12px;color:var(--td)">{{ $row['masked_prefix'] }}</div>
                    </td>
                    <td>
                        @if($row['account_summary'])
                            <div style="font-weight:700;color:var(--tx)">{{ $row['account_summary']['name'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['account_summary']['type_label'] }} • {{ $row['account_summary']['slug'] }}</div>
                        @else
                            <div style="font-size:12px;color:var(--td)">No linked account</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['state_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['status_detail'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['scope_summary'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['allowed_ip_summary'] }}</div>
                        <div style="font-size:12px;color:var(--td)">Created {{ $row['created_at'] }}</div>
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['last_used_at'] }}</div>
                        <div style="font-size:12px;color:var(--td)">Expires {{ $row['expires_at'] }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">No API keys match the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $keys->links() }}</div>
</div>
@endsection
