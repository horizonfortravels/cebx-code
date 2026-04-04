@extends('layouts.app')
@section('title', 'Internal API key detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.api-keys.index') }}" style="color:inherit;text-decoration:none">API keys</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal API key detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            {{ $detail['name'] }} • {{ $detail['masked_prefix'] }} • {{ $detail['state_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.api-keys.index') }}" class="btn btn-pr">Back to API keys</a>
    </div>
</div>

@if($plaintextKey)
    <section class="card" data-testid="internal-api-key-plaintext-card" style="margin-bottom:24px;border-color:rgba(15,118,110,.25);background:rgba(15,118,110,.05)">
        <div class="card-title">One-time plaintext secret</div>
        <p style="color:var(--td);font-size:13px;margin-top:0">
            Store this key securely now. The internal portal will not show it again after this request completes.
        </p>
        <code data-testid="internal-api-key-plaintext-value" style="display:block;padding:14px;border:1px dashed var(--bd);border-radius:12px;background:#fff;color:var(--tx);font-size:14px;direction:ltr;text-align:left">{{ $plaintextKey }}</code>
    </section>
@endif

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="KEY" label="Masked prefix" :value="$detail['masked_prefix']" />
    <x-stat-card icon="STA" label="State" :value="$detail['state_label']" />
    <x-stat-card icon="SCP" label="Scope count" :value="number_format(count($detail['scope_keys']))" />
    <x-stat-card icon="IP" label="Allowlisted IPs" :value="number_format($detail['allowed_ip_count'])" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-api-key-summary-card">
        <div class="card-title">Key summary</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Name</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">Prefix</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['masked_prefix'] }}</dd>
            <dt style="color:var(--tm)">State</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['state_label'] }} • {{ $detail['status_detail'] }}</dd>
            <dt style="color:var(--tm)">Created</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['created_at'] }}</dd>
            <dt style="color:var(--tm)">Created by</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['creator_summary']['name'] }} • {{ $detail['creator_summary']['email'] }}</dd>
            <dt style="color:var(--tm)">Last used</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['last_used_at'] }}</dd>
            <dt style="color:var(--tm)">Expires</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['expires_at'] }}</dd>
            <dt style="color:var(--tm)">Revoked at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['revoked_at'] }}</dd>
            @if($detail['account_summary'])
                <dt style="color:var(--tm)">Linked account</dt>
                <dd style="margin:0;color:var(--tx)">
                    {{ $detail['account_summary']['name'] }} • {{ $detail['account_summary']['type_label'] }}
                    @if($canViewAccount)
                        <div style="margin-top:8px">
                            <a href="{{ route('internal.accounts.show', $detail['account_summary']['account']) }}" class="btn btn-s" data-testid="internal-api-key-account-link">Open account detail</a>
                        </div>
                    @endif
                </dd>
            @endif
        </dl>
    </section>

    <section class="card" data-testid="internal-api-key-security-card">
        <div class="card-title">Security summary</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Allowlist</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['allowed_ip_summary'] }}</dd>
            <dt style="color:var(--tm)">Secret model</dt>
            <dd style="margin:0;color:var(--tx)">Plaintext is shown once on create or rotate, then masked permanently.</dd>
            <dt style="color:var(--tm)">Stored secret</dt>
            <dd style="margin:0;color:var(--tx)">Only a hashed key record is stored. Plaintext and key hash are never rendered here.</dd>
        </dl>
    </section>
</div>

<section class="card" data-testid="internal-api-key-scopes-card" style="margin-bottom:24px">
    <div class="card-title">Scopes</div>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
        @forelse($detail['scope_items'] as $scope)
            <span style="padding:10px 12px;border:1px solid var(--bd);border-radius:999px;background:rgba(15,23,42,.03);color:var(--tx)">
                {{ $scope['label'] }}
            </span>
        @empty
            <div class="empty-state">This key has no scoped allowlist recorded, so it behaves like a legacy unrestricted key.</div>
        @endforelse
    </div>
</section>

@if($canManageKeys && $detail['is_rotatable'])
    <div class="grid-2">
        <section class="card" data-testid="internal-api-key-rotate-form">
            <div class="card-title">Rotate key safely</div>
            <form method="POST" action="{{ route('internal.api-keys.rotate', $detail['id']) }}" style="display:flex;flex-direction:column;gap:10px">
                @csrf
                <label for="api-key-rotate-reason" style="font-size:12px;color:var(--tm)">Internal rotation reason</label>
                <textarea id="api-key-rotate-reason" name="reason" rows="3" class="input" maxlength="500" placeholder="Explain why a new secret is required." required>{{ old('reason') }}</textarea>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-pr" data-testid="internal-api-key-rotate-button">Rotate key</button>
                </div>
            </form>
        </section>

        <section class="card" data-testid="internal-api-key-revoke-form">
            <div class="card-title">Revoke key safely</div>
            <form method="POST" action="{{ route('internal.api-keys.revoke', $detail['id']) }}" style="display:flex;flex-direction:column;gap:10px">
                @csrf
                <label for="api-key-revoke-reason" style="font-size:12px;color:var(--tm)">Internal revocation reason</label>
                <textarea id="api-key-revoke-reason" name="reason" rows="3" class="input" maxlength="500" placeholder="Explain why this key must be disabled." required>{{ old('reason') }}</textarea>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-danger" data-testid="internal-api-key-revoke-button">Revoke key</button>
                </div>
            </form>
        </section>
    </div>
@endif
@endsection
