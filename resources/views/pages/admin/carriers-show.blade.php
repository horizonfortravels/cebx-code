@extends('layouts.app')
@section('title', 'Carrier integration detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.carriers.index') }}" style="color:inherit;text-decoration:none">Carrier integrations</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Carrier integration detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            {{ $detail['name'] }} • {{ $detail['provider_key'] }} • {{ $detail['enabled_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.integrations.show', 'carrier~' . $detail['provider_key']) }}" class="btn btn-s">Open broader integrations detail</a>
        <a href="{{ route('internal.carriers.index') }}" class="btn btn-pr">Back to carriers</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CAR" label="Carrier" :value="$detail['provider_name']" />
    <x-stat-card icon="ON" label="State" :value="$detail['enabled_label']" />
    <x-stat-card icon="MD" label="Connection mode" :value="$detail['mode_label']" />
    <x-stat-card icon="HLT" label="Health" :value="$detail['health_label']" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-carrier-summary-card">
        <div class="card-title">Carrier summary</div>
        <dl style="display:grid;grid-template-columns:minmax(150px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Carrier name</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">Provider key</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['provider_key'] }}</dd>
            <dt style="color:var(--tm)">Enabled state</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['enabled_label'] }}</dd>
            <dt style="color:var(--tm)">Configuration</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['configuration_label'] }}</dd>
            <dt style="color:var(--tm)">Connection mode</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['mode_summary'] }}</dd>
            <dt style="color:var(--tm)">Shipper account</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['shipper_account_summary']['summary'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-carrier-health-card">
        <div class="card-title">Connection and test status</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="font-weight:700;color:var(--tx)">{{ $detail['connection_test_summary']['headline'] }}</div>
            <div style="font-size:13px;color:var(--td)">{{ $detail['connection_test_summary']['detail'] }}</div>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                <div>
                    <div style="font-size:12px;color:var(--tm)">Last check</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['checked_at'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">Response time</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['response_time'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">Requests</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['request_summary'] }}</div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    @if($canManageCarriers)
        <section class="card" data-testid="internal-carrier-actions-card">
            <div class="card-title">Operational actions</div>
            <div style="display:flex;flex-direction:column;gap:16px">
                <form method="post" action="{{ route('internal.carriers.toggle', $detail['provider_key']) }}" data-testid="internal-carrier-toggle-form">
                    @csrf
                    <input type="hidden" name="is_enabled" value="{{ $detail['is_enabled'] ? 0 : 1 }}">
                    <label style="display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:12px;color:var(--tm)">Operator reason</span>
                        <textarea name="reason" rows="3" style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)" placeholder="Explain why this carrier is being {{ $detail['is_enabled'] ? 'disabled' : 'enabled' }} from the internal portal." required>{{ old('reason') }}</textarea>
                    </label>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px">
                        <div style="font-size:13px;color:var(--td)">
                            {{ $detail['is_enabled'] ? 'Disable this carrier gate for internal runtime usage.' : 'Enable this carrier gate for internal runtime usage.' }}
                        </div>
                        <button type="submit" class="btn btn-pr" data-testid="internal-carrier-toggle-button">
                            {{ $detail['is_enabled'] ? 'Disable carrier' : 'Enable carrier' }}
                        </button>
                    </div>
                </form>

                <form method="post" action="{{ route('internal.carriers.test', $detail['provider_key']) }}" data-testid="internal-carrier-test-form">
                    @csrf
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">Run safe connection test</div>
                            <div style="font-size:13px;color:var(--td)">This validates the carrier configuration contract and records a new health-check result without touching shipment data.</div>
                        </div>
                        <button type="submit" class="btn btn-s" data-testid="internal-carrier-test-button">Run connection test</button>
                    </div>
                </form>

                <form method="post" action="{{ route('internal.carriers.credentials.update', $detail['provider_key']) }}" data-testid="internal-carrier-credentials-update-form">
                    @csrf
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">Update stored carrier credentials</div>
                            <div style="font-size:13px;color:var(--td)">Leave any field blank to keep the current stored value. Saved credentials remain encrypted at rest and masked in the portal.</div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                            @foreach($credentialFields as $field)
                                <label style="display:flex;flex-direction:column;gap:8px">
                                    <span style="font-size:12px;color:var(--tm)">{{ $field['label'] }}</span>
                                    <input
                                        type="{{ $field['input_type'] }}"
                                        name="{{ $field['name'] }}"
                                        value=""
                                        autocomplete="off"
                                        style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)"
                                    >
                                    <span style="font-size:12px;color:var(--td)">Current: {{ $field['current_value'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <label style="display:flex;flex-direction:column;gap:8px">
                            <span style="font-size:12px;color:var(--tm)">Operator reason</span>
                            <textarea name="reason" rows="3" style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)" placeholder="Explain why these stored carrier credentials are being updated from the internal portal." required></textarea>
                        </label>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                            <div style="font-size:13px;color:var(--td)">A fresh safe connection test will run immediately after saving.</div>
                            <button type="submit" class="btn btn-pr" data-testid="internal-carrier-credentials-save-button">Save credentials</button>
                        </div>
                    </div>
                </form>

                @if($supportsRotation)
                    <form method="post" action="{{ route('internal.carriers.credentials.rotate', $detail['provider_key']) }}" data-testid="internal-carrier-credentials-rotate-form">
                        @csrf
                        <div style="display:flex;flex-direction:column;gap:12px">
                            <div>
                                <div style="font-weight:700;color:var(--tx)">Rotate active API credentials</div>
                                <div style="font-size:13px;color:var(--td)">This replaces the currently stored API credential pair for this carrier and immediately re-tests the connection. It does not expose or export the previous secret.</div>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                                @foreach($rotationFields as $field)
                                    <label style="display:flex;flex-direction:column;gap:8px">
                                        <span style="font-size:12px;color:var(--tm)">{{ $field['label'] }}</span>
                                        <input
                                            type="{{ $field['input_type'] }}"
                                            name="{{ $field['name'] }}"
                                            value=""
                                            autocomplete="off"
                                            style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)"
                                        >
                                        <span style="font-size:12px;color:var(--td)">Current: {{ $field['current_value'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <label style="display:flex;flex-direction:column;gap:8px">
                                <span style="font-size:12px;color:var(--tm)">Operator reason</span>
                                <textarea name="reason" rows="3" style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)" placeholder="Explain why the active API key or secret set is being rotated from the internal portal." required></textarea>
                            </label>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                                <div style="font-size:13px;color:var(--td)">Only the newly provided API credential fields are replaced during rotation.</div>
                                <button type="submit" class="btn btn-s" data-testid="internal-carrier-credentials-rotate-button">Rotate credentials</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <section class="card" data-testid="internal-carrier-activity-card">
        <div class="card-title">Recent carrier activity</div>
        <div style="font-weight:700;color:var(--tx);margin-bottom:8px">{{ $detail['activity_summary']['headline'] }}</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:14px">{{ $detail['activity_summary']['detail'] }}</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($detail['activity_summary']['items'] as $item)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-size:12px;color:var(--tm)">{{ $item['label'] }}</div>
                    <div style="color:var(--tx)">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card" data-testid="internal-carrier-error-card">
        <div class="card-title">Last error summary</div>
        <div style="font-weight:700;color:var(--tx);margin-bottom:8px">{{ $detail['last_error_summary']['headline'] }}</div>
        <div style="font-size:13px;color:var(--td)">{{ $detail['last_error_summary']['detail'] }}</div>
    </section>
</div>

@if($canViewCredentials)
    <section class="card" data-testid="internal-carrier-credentials-card">
        <div class="card-title">Masked credential summary</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:14px">{{ $detail['masked_api_summary'] }} Plaintext secrets are never rendered back after save.</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            @forelse($credentialFields as $field)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-size:12px;color:var(--tm)">{{ $field['label'] }}</div>
                    <div style="color:var(--tx)">{{ $field['current_value'] }}</div>
                </div>
            @empty
                <div class="empty-state">No configured credential fields are visible for this carrier.</div>
            @endforelse
        </div>
    </section>
@endif
@endsection
