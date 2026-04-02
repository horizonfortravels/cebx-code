@extends('layouts.app')
@section('title', 'Compliance Case')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.compliance.index') }}" style="color:inherit;text-decoration:none">Compliance queue</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $shipmentSummary['reference'] ?? ('Case ' . $declaration->id) }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Compliance case detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Read-only operational visibility into declaration state, legal acknowledgement, dangerous-goods metadata, and recent compliance audit activity. This page intentionally hides raw waiver text, hashes, IP addresses, user agents, and raw audit payloads.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($canViewShipment && $shipment)
            <a href="{{ route('internal.shipments.show', $shipment) }}" class="btn btn-s" data-testid="internal-compliance-shipment-link">Open linked shipment</a>
        @endif
        @if($canViewAccount && $account)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s" data-testid="internal-compliance-account-link">Open linked account</a>
        @endif
        @if($canViewKyc && $account)
            <a href="{{ route('internal.kyc.show', $account) }}" class="btn btn-s" data-testid="internal-compliance-kyc-link">Open linked KYC</a>
        @endif
        @if($canViewBilling && $account && $hasBillingContext)
            <a href="{{ route('internal.billing.show', $account) }}" class="btn btn-s" data-testid="internal-compliance-billing-link">Open linked billing</a>
            @if($linkedPreflightHold)
                <a href="{{ route('internal.billing.preflights.show', ['account' => $account, 'hold' => $linkedPreflightHold]) }}" class="btn btn-s" data-testid="internal-compliance-preflight-link">Open linked preflight</a>
            @endif
        @endif
        <a href="{{ route('internal.compliance.index') }}" class="btn btn-s">Back to queue</a>
        <a href="{{ route('internal.compliance.show', $declaration) }}" class="btn btn-pr">Refresh detail</a>
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CMP" label="Compliance state" :value="$declarationSummary['status']" />
    <x-stat-card icon="REV" label="Review state" :value="$reviewSummary['label']" />
    <x-stat-card icon="DG" label="Contains DG" :value="$declarationSummary['contains_dg']" />
    <x-stat-card icon="LGL" label="Legal acknowledgement" :value="$legalSummary['state_label']" />
</div>

@if($canManageComplianceActions)
    <section class="card" data-testid="internal-compliance-actions-card" style="margin-bottom:24px">
        <div class="card-title">Internal compliance actions</div>
        @if($requestCorrectionAction['is_available'])
            <p style="margin:0 0 14px;color:var(--td);font-size:14px">
                {{ $requestCorrectionAction['detail'] }}
            </p>
            <form method="POST"
                  action="{{ route('internal.compliance.requires-action', $declaration) }}"
                  data-testid="internal-compliance-requires-action-form"
                  style="display:flex;flex-direction:column;gap:12px">
                @csrf
                <label style="display:flex;flex-direction:column;gap:6px">
                    <span style="font-size:12px;color:var(--tm)">Internal review reason</span>
                    <textarea name="reason" rows="3" class="input" style="min-height:92px" required>{{ old('reason') }}</textarea>
                </label>
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                    <div style="font-size:12px;color:var(--tm)">{{ $requestCorrectionAction['headline'] }}</div>
                    <button type="submit" class="btn btn-pr" data-testid="internal-compliance-requires-action-button">Request correction</button>
                </div>
            </form>
        @else
            <p style="margin:0;color:var(--td)" data-testid="internal-compliance-action-state-note">
                {{ $requestCorrectionAction['headline'] }}. {{ $requestCorrectionAction['detail'] }}
            </p>
        @endif
    </section>
@endif

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-case-summary-card">
        <div class="card-title">Case summary</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Status</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['status'] }}</dd>

            <dt style="color:var(--tm)">Review state</dt>
            <dd style="margin:0;color:var(--tx)">{{ $reviewSummary['label'] }}</dd>

            <dt style="color:var(--tm)">Review detail</dt>
            <dd style="margin:0;color:var(--tx)">{{ $reviewSummary['detail'] }}</dd>

            <dt style="color:var(--tm)">DG declared</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['dg_answered'] }}</dd>

            <dt style="color:var(--tm)">Contains DG</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['contains_dg'] }}</dd>

            <dt style="color:var(--tm)">Declared at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['declared_at'] }}</dd>

            <dt style="color:var(--tm)">Updated at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['updated_at'] }}</dd>

            <dt style="color:var(--tm)">Hold reason</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['hold_reason'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-compliance-shipment-card">
        <div class="card-title">Shipment context</div>
        @if($shipmentSummary)
            <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">Reference</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['reference'] }}</dd>

                <dt style="color:var(--tm)">Workflow status</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['workflow_status'] }}</dd>

                <dt style="color:var(--tm)">Source</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['source'] }}</dd>

                <dt style="color:var(--tm)">Dangerous goods</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['dangerous_goods'] }}</dd>

                <dt style="color:var(--tm)">Status reason</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['status_reason'] }}</dd>

                <dt style="color:var(--tm)">Created</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['created_at'] }}</dd>

                <dt style="color:var(--tm)">Updated</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['updated_at'] }}</dd>
            </dl>
        @else
            <div class="empty-state">Shipment context is not available for this compliance case.</div>
        @endif
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-account-card">
        <div class="card-title">Account and organization context</div>
        @if($accountSummary)
            <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">Account</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['name'] }}</dd>

                <dt style="color:var(--tm)">Slug</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['slug'] }}</dd>

                <dt style="color:var(--tm)">Type</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['type'] }}</dd>

                <dt style="color:var(--tm)">Lifecycle</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['status'] }}</dd>

                <dt style="color:var(--tm)">Organization</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['organization'] }}</dd>

                <dt style="color:var(--tm)">Owner</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['owner'] }}</dd>

                <dt style="color:var(--tm)">Owner email</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['owner_email'] }}</dd>
            </dl>
        @else
            <div class="empty-state">Account context is not available for this compliance case.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-compliance-legal-card">
        <div class="card-title">Legal acknowledgement summary</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">State</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['state_label'] }}</dd>

            <dt style="color:var(--tm)">Detail</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['detail'] }}</dd>

            <dt style="color:var(--tm)">Version</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['version'] }}</dd>

            <dt style="color:var(--tm)">Accepted at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['accepted_at'] }}</dd>

            <dt style="color:var(--tm)">Locale</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['locale'] }}</dd>
        </dl>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-workflow-card">
        <div class="card-title">Declaration workflow effect</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Shipment workflow</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['shipment_workflow_state'] }}</dd>

            <dt style="color:var(--tm)">Blocked</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['is_blocked'] ? 'Yes' : 'No' }}</dd>

            <dt style="color:var(--tm)">Declaration complete</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['declaration_complete'] ? 'Yes' : 'No' }}</dd>

            <dt style="color:var(--tm)">Requires disclaimer</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['requires_disclaimer'] ? 'Yes' : 'No' }}</dd>

            <dt style="color:var(--tm)">Next action</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['next_action'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-compliance-notes-card">
        <div class="card-title">Compliance notes</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($notesSummary as $note)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $note['source'] }}</div>
                    <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $note['detail'] }}</div>
                </div>
            @empty
                <div class="empty-state">No safe compliance notes are visible for this case.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="card" data-testid="internal-compliance-effects-card" style="margin-bottom:24px">
    <div class="card-title">Current restrictions and operational effect</div>
    @if($restrictionSummary)
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm)">KYC status</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['status_label'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Queue summary</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['queue_summary'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Shipment effect</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['shipping_label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $restrictionSummary['shipping_detail'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">International shipping</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['international_label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $restrictionSummary['international_detail'] }}</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
            <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03)">
                <div style="font-size:12px;color:var(--tm)">Further action</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['action_label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $restrictionSummary['action_detail'] }}</div>
            </div>
            <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03)">
                <div style="font-size:12px;color:var(--tm)">Blocked shipments</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($restrictionSummary['blocked_shipments_count']) }}</div>
            </div>
        </div>

        @if($restrictionSummary['restriction_names'] !== [])
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                @foreach($restrictionSummary['restriction_names'] as $restrictionName)
                    <span class="badge">{{ $restrictionName }}</span>
                @endforeach
            </div>
        @endif
    @else
        <div class="empty-state">No linked restriction summary is available for this compliance case.</div>
    @endif
</section>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-dg-card">
        <div class="card-title">Dangerous-goods metadata</div>
        @if($dgMetadataSummary)
            <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">UN number</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['un_number'] }}</dd>

                <dt style="color:var(--tm)">DG class</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['dg_class'] }}</dd>

                <dt style="color:var(--tm)">Packing group</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['packing_group'] }}</dd>

                <dt style="color:var(--tm)">Proper shipping name</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['proper_shipping_name'] }}</dd>

                <dt style="color:var(--tm)">Quantity</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['quantity'] }}</dd>
            </dl>
        @else
            <div class="empty-state">No safe dangerous-goods metadata is visible for this case.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-compliance-audit-card">
        <div class="card-title">Recent compliance audit summary</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($auditEntries as $entry)
                <div data-testid="internal-compliance-audit-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $entry['action'] }}</div>
                    <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $entry['actor_role'] }} | {{ $entry['created_at'] }}</div>
                    @if(!empty($entry['change_summary']))
                        <div data-testid="internal-compliance-audit-change-summary" style="font-size:12px;color:var(--td);margin-top:8px">
                            Change summary: {{ $entry['change_summary'] }}
                        </div>
                    @endif
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">{{ $entry['note'] }}</div>
                </div>
            @empty
                <div class="empty-state">No compliance audit entries are visible for this case yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
