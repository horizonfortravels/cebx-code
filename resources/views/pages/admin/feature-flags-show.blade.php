@extends('layouts.app')
@section('title', 'Internal feature flag detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.feature-flags.index') }}" style="color:inherit;text-decoration:none">Feature flags</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal feature flag detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            {{ $detail['name'] }} • {{ $detail['key'] }} • {{ $detail['state_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.feature-flags.index') }}" class="btn btn-pr">Back to feature flags</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="STA" label="Current state" :value="$detail['state_label']" />
    <x-stat-card icon="RLO" label="Rollout" :value="$detail['rollout_label']" />
    <x-stat-card icon="ACC" label="Target accounts" :value="number_format($detail['target_account_count'])" />
    <x-stat-card icon="PLN" label="Target plans" :value="number_format($detail['target_plan_count'])" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-feature-flag-summary-card">
        <div class="card-title">Flag summary</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Name</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">Key</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['key'] }}</dd>
            <dt style="color:var(--tm)">Description</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['description'] }}</dd>
            <dt style="color:var(--tm)">State</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['state_label'] }}</dd>
            <dt style="color:var(--tm)">Rollout</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['rollout_label'] }}</dd>
            <dt style="color:var(--tm)">Created by</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['created_by'] }}</dd>
            <dt style="color:var(--tm)">Updated at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['updated_at'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-feature-flag-runtime-card">
        <div class="card-title">Runtime source and targeting</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Runtime source</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['source_label'] }}</dd>
            <dt style="color:var(--tm)">Config default</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['config_default_label'] }}</dd>
            <dt style="color:var(--tm)">Targeting</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['targeting_summary'] }}</dd>
            <dt style="color:var(--tm)">Accounts</dt>
            <dd style="margin:0;color:var(--tx)">{{ number_format($detail['target_account_count']) }} target account(s)</dd>
            <dt style="color:var(--tm)">Plans</dt>
            <dd style="margin:0;color:var(--tx)">{{ number_format($detail['target_plan_count']) }} target plan(s)</dd>
            <dt style="color:var(--tm)">Operational note</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['runtime_note'] }}</dd>
        </dl>
    </section>
</div>

<section class="card" data-testid="internal-feature-flag-audit-card" style="margin-bottom:24px">
    <div class="card-title">Internal audit trail</div>
    <div style="display:grid;gap:12px">
        @forelse($detail['audit_items'] as $item)
            <div style="padding:14px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.02)">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <strong style="color:var(--tx)">{{ $item['headline'] }}</strong>
                    <span style="font-size:12px;color:var(--td)">{{ $item['created_at'] }}</span>
                </div>
                <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $item['performed_by'] }}</div>
                <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $item['detail'] !== '' ? $item['detail'] : 'State change recorded.' }}</div>
            </div>
        @empty
            <div class="empty-state">No internal toggle audit is recorded for this feature flag yet.</div>
        @endforelse
    </div>
</section>

@if($canManageFlags)
    <section class="card" data-testid="internal-feature-flag-toggle-form">
        <div class="card-title">{{ $detail['state_key'] === 'enabled' ? 'Disable flag safely' : 'Enable flag safely' }}</div>
        <p style="color:var(--td);font-size:13px;margin-top:0">
            This action changes only the DB-backed feature-flag record and records an internal audit entry with the operator reason.
        </p>
        <form method="POST" action="{{ route('internal.feature-flags.toggle', $detail['id']) }}" style="display:flex;flex-direction:column;gap:10px">
            @csrf
            <input type="hidden" name="is_enabled" value="{{ $detail['state_key'] === 'enabled' ? 0 : 1 }}">
            <label for="feature-flag-toggle-reason" style="font-size:12px;color:var(--tm)">Internal reason</label>
            <textarea id="feature-flag-toggle-reason" name="reason" rows="3" class="input" maxlength="500" placeholder="Explain why this internal feature-flag state must change." required>{{ old('reason') }}</textarea>
            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn {{ $detail['state_key'] === 'enabled' ? 'btn-danger' : 'btn-pr' }}" data-testid="internal-feature-flag-toggle-button">
                    {{ $detail['state_key'] === 'enabled' ? 'Disable flag' : 'Enable flag' }}
                </button>
            </div>
        </form>
    </section>
@endif
@endsection
