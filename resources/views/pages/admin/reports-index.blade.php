@extends('layouts.app')
@section('title', 'Internal reports & analytics')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <span>Reports &amp; analytics</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal reporting hub</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Operational card summaries across shipments, KYC, wallet and billing, compliance, and helpdesk. This hub stays read-only and only shows safe headline metrics before you open the linked internal center for deeper casework.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.reports.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal workspace</a>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.reports.index') }}" data-testid="internal-reports-filter-form" style="display:grid;grid-template-columns:2fr repeat(2,minmax(0,1fr));gap:12px;align-items:end">
        <div>
            <label for="internal-reports-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="internal-reports-search" data-testid="internal-reports-search-input" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Domain, KPI, or operational summary">
        </div>
        <div>
            <label for="internal-reports-domain" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Domain</label>
            <select id="internal-reports-domain" data-testid="internal-reports-domain-filter" name="domain" class="input">
                <option value="">All domains</option>
                @foreach($domainOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['domain'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.reports.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom:24px;background:#f8fafc">
    <div class="card-title">Scope guardrail</div>
    <p style="margin:0;color:var(--td);line-height:1.8">
        Only high-level operational metrics are shown here. Secrets, raw webhook payloads, internal escalation content, private financial metadata, and hidden legal or compliance payloads stay inside their dedicated read centers and remain masked there as well.
    </p>
</div>

<div data-testid="internal-reports-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
    @forelse($cards as $card)
        <article class="card" data-testid="internal-report-card-{{ $card['key'] }}" style="display:grid;gap:16px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                <div>
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">{{ $card['eyebrow'] }}</div>
                    <h2 style="margin:0;font-size:22px;color:var(--tx)">{{ $card['title'] }}</h2>
                </div>
                <span style="display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700">
                    {{ number_format(count($card['metrics'])) }} KPIs
                </span>
            </div>

            <p style="margin:0;color:var(--td);line-height:1.8">{{ $card['description'] }}</p>

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                @foreach($card['metrics'] as $metric)
                    <div style="padding:14px;border:1px solid var(--bd);border-radius:16px;background:#fff">
                        <div style="font-size:12px;color:var(--tm);margin-bottom:6px">{{ $metric['label'] }}</div>
                        <div style="font-size:24px;font-weight:800;color:var(--tx)">{{ $metric['display'] ?? number_format($metric['value']) }}</div>
                    </div>
                @endforeach
            </div>

            <div style="font-size:13px;color:var(--td);line-height:1.8">{{ $card['summary'] }}</div>

            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    @if($card['can_open_dashboard'])
                        <a href="{{ route('internal.reports.' . $card['key']) }}" class="btn btn-pr" data-testid="internal-report-card-{{ $card['key'] }}-dashboard-link">Open dashboard</a>
                    @endif

                    @if($card['can_open'] && filled($card['route_name'] ?? null))
                        <a href="{{ route($card['route_name']) }}" class="btn btn-s" data-testid="internal-report-card-{{ $card['key'] }}-link">{{ $card['cta_label'] }}</a>
                    @elseif(filled($card['route_name'] ?? null))
                        <span class="btn btn-s" style="pointer-events:none;opacity:.8">Linked center is not available for this role</span>
                    @endif
                </div>
                <span style="font-size:12px;color:var(--tm)">Read-only summary</span>
            </div>
        </article>
    @empty
        <div class="card" data-testid="internal-reports-empty-state">
            <div class="card-title">No report cards matched the current filters</div>
            <p style="margin:0;color:var(--td);line-height:1.8">
                Adjust the search or domain filter to restore the available operational report cards.
            </p>
        </div>
    @endforelse
</div>
@endsection
