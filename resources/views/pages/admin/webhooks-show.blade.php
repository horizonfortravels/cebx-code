@extends('layouts.app')
@section('title', 'Webhook endpoint detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.webhooks.index') }}" style="color:inherit;text-decoration:none">Webhooks</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Webhook endpoint detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            {{ $detail['name'] }} • {{ $detail['endpoint_label'] }} • {{ $detail['provider_name'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.webhooks.index') }}" class="btn btn-pr">Back to webhooks</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="WH" label="Endpoint" :value="$detail['endpoint_label']" />
    <x-stat-card icon="STA" label="State" :value="$detail['state_label']" />
    <x-stat-card icon="TRY" label="Attempts" :value="number_format($detail['attempts_count'])" />
    <x-stat-card icon="ERR" label="Recent failures" :value="number_format($detail['failures_count'])" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-webhook-summary-card">
        <div class="card-title">Endpoint summary</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Endpoint</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">Provider</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['provider_name'] }}</dd>
            <dt style="color:var(--tm)">Type</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['endpoint_label'] }}</dd>
            <dt style="color:var(--tm)">State</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['state_label'] }} • {{ $detail['enabled_label'] }}</dd>
            <dt style="color:var(--tm)">Last attempt</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['last_attempt_at'] }}</dd>
            <dt style="color:var(--tm)">Last success</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['last_success_at'] }}</dd>
            <dt style="color:var(--tm)">Last failure</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['last_failure_at'] }}</dd>
            <dt style="color:var(--tm)">Security</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['security_summary'] }}</dd>
            @if($detail['account_summary'])
                <dt style="color:var(--tm)">Linked account</dt>
                <dd style="margin:0;color:var(--tx)">{{ $detail['account_summary']['name'] }} • {{ $detail['account_summary']['type_label'] }}</dd>
            @endif
        </dl>
    </section>

    <section class="card" data-testid="internal-webhook-failures-card">
        <div class="card-title">Recent failures</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($detail['recent_failures'] as $attempt)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px" data-testid="internal-webhook-failure-entry">
                    <div style="font-weight:700;color:var(--tx)">{{ $attempt['headline'] }}</div>
                    <div style="font-size:12px;color:var(--td)">{{ $attempt['status_label'] }} • {{ $attempt['received_at'] }}</div>
                    <div style="font-size:13px;color:var(--tx);margin-top:6px">{{ $attempt['failure_summary'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $attempt['resource_summary'] }} • {{ $attempt['attempt_summary'] }}</div>
                </div>
            @empty
                <div class="empty-state">No recent webhook failures are recorded for this endpoint.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="card" data-testid="internal-webhook-attempts-card" style="margin-bottom:24px">
    <div class="card-title">Recent deliveries</div>
    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse($detail['recent_attempts'] as $attempt)
            <article style="padding:14px;border:1px solid var(--bd);border-radius:12px" data-testid="internal-webhook-attempt-entry">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div>
                        <div style="font-weight:700;color:var(--tx)">{{ $attempt['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $attempt['status_label'] }} • {{ $attempt['resource_summary'] }}</div>
                    </div>
                    <div style="font-size:12px;color:var(--td);text-align:left">
                        <div>Received {{ $attempt['received_at'] }}</div>
                        <div>Updated {{ $attempt['processed_at'] }}</div>
                    </div>
                </div>
                <div style="font-size:13px;color:var(--td);margin-top:10px">{{ $attempt['attempt_summary'] }}</div>

                @if($attempt['is_failure'])
                    <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $attempt['failure_summary'] }}</div>
                @endif

                @if($canRetryEvents && $attempt['is_retryable'])
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--bd)">
                        <form method="POST" action="{{ route('internal.webhooks.events.retry', ['endpoint' => $detail['route_key'], 'event' => $attempt['event_id']]) }}" data-testid="internal-webhook-retry-form" style="display:flex;flex-direction:column;gap:10px">
                            @csrf
                            <label style="font-size:12px;color:var(--tm)" for="retry-reason-{{ $attempt['event_id'] }}">Internal retry reason</label>
                            <textarea id="retry-reason-{{ $attempt['event_id'] }}" name="reason" rows="2" class="input" placeholder="Explain why this failed delivery is safe to retry." required>{{ old('reason') }}</textarea>
                            <div style="display:flex;justify-content:flex-end">
                                <button type="submit" class="btn btn-pr" data-testid="internal-webhook-retry-button">Retry safely</button>
                            </div>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="empty-state">No webhook deliveries are recorded for this endpoint yet.</div>
        @endforelse
    </div>
</section>
@endsection
