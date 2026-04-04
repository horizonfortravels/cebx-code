@extends('layouts.app')
@section('title', 'Shipment Detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.shipments.index') }}" style="color:inherit;text-decoration:none">Shipments</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $shipmentSummary['reference'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Shipment detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Read-only internal shipment visibility with normalized status, carrier artifacts, public tracking state, and linked KYC impact summaries. This page intentionally hides raw document storage paths, carrier payloads, private token values, and other unsafe metadata.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.shipments.index') }}" class="btn btn-s">Back to queue</a>
        <a href="{{ route('internal.shipments.show', $shipment) }}" class="btn btn-pr">Refresh detail</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="REF" label="Reference" :value="$shipmentSummary['reference']" />
    <x-stat-card icon="STS" label="Workflow" :value="$shipmentSummary['workflow_status_label']" />
    <x-stat-card icon="TRK" label="Normalized status" :value="$shipmentSummary['normalized_status_label']" />
    <x-stat-card icon="DOC" label="Documents" :value="number_format($documents->count())" />
</div>

<section class="card" data-testid="internal-shipment-actions-card" style="margin-bottom:24px">
    <div class="card-title">Operational actions</div>
    <p style="margin:0 0 12px;color:var(--td);font-size:13px">
        This internal panel exposes only safe operational links that already exist in the live product contract. Carrier retry, reissue, cancel, and manual status editing remain intentionally unavailable from this surface.
    </p>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.shipments.show', $shipment) }}"
           class="btn btn-pr"
           data-testid="internal-shipment-refresh-link">Refresh operational view</a>

        @if($canCreateTickets)
            <a href="{{ route('internal.shipments.tickets.create', $shipment) }}"
               class="btn btn-s"
               data-testid="internal-shipment-create-linked-ticket-link">Create linked ticket</a>
        @endif

        @if($canViewDocuments)
            <a href="{{ route('internal.shipments.documents.index', $shipment) }}"
               class="btn btn-s"
               data-testid="internal-shipment-documents-workspace-link">Open document workspace</a>
        @endif

        @if($canViewAccount && isset($accountSummary['account']))
            <a href="{{ route('internal.accounts.show', $accountSummary['account']) }}"
               class="btn btn-s"
               data-testid="internal-shipment-actions-account-link">Open linked account detail</a>
        @endif

        @if($canViewKyc && isset($accountSummary['account']))
            <a href="{{ route('internal.kyc.show', $accountSummary['account']) }}"
               class="btn btn-s"
               data-testid="internal-shipment-actions-kyc-link">Open KYC and restrictions</a>
        @endif

        @if(!empty($publicTracking['url']))
            <a href="{{ $publicTracking['url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="btn btn-s"
               data-testid="internal-shipment-actions-public-tracking-link">Open public tracking page</a>
            <button type="button"
                    class="btn btn-s"
                    data-testid="internal-shipment-copy-public-tracking-link"
                    data-copy-text="{{ $publicTracking['url'] }}"
                    data-copy-target="internal-shipment-copy-status">Copy public tracking link</button>
        @endif
    </div>

    <div id="internal-shipment-copy-status"
         data-testid="internal-shipment-copy-status"
         aria-live="polite"
         style="font-size:12px;color:var(--tm);margin-top:12px">
        Use these read-only shortcuts for operational follow-up without bypassing declaration, wallet, or compliance controls.
    </div>
</section>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-shipment-summary-card">
        <div class="card-title">Shipment summary</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Reference</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['reference'] }}</dd>

            <dt style="color:var(--tm)">Workflow status</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['workflow_status_label'] }}</dd>

            <dt style="color:var(--tm)">Normalized status</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['normalized_status_label'] }}</dd>

            <dt style="color:var(--tm)">Source</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['source_label'] }}</dd>

            <dt style="color:var(--tm)">Created</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['created_at'] ?? '—' }}</dd>

            <dt style="color:var(--tm)">Updated</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['updated_at'] ?? '—' }}</dd>

            <dt style="color:var(--tm)">Destination</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['recipient_city'] ?: '—' }} @if($shipmentSummary['recipient_country']) • {{ $shipmentSummary['recipient_country'] }} @endif</dd>

            <dt style="color:var(--tm)">Flags</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($shipmentSummary['flags'] !== [])
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        @foreach($shipmentSummary['flags'] as $flag)
                            <span class="badge">{{ $flag }}</span>
                        @endforeach
                    </div>
                @else
                    None
                @endif
            </dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-shipment-linked-account-card">
        <div class="card-title">Linked account</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div>
                <div style="font-weight:700;color:var(--tx)">{{ $accountSummary['name'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $accountSummary['type_label'] }} • {{ $accountSummary['slug'] }}</div>
            </div>
            <div style="font-size:13px;color:var(--tx)">{{ $accountSummary['owner_label'] }}</div>
            <div style="font-size:12px;color:var(--td)">{{ $accountSummary['owner_secondary'] }}</div>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                @if($canViewAccount && isset($accountSummary['account']))
                    <a href="{{ route('internal.accounts.show', $accountSummary['account']) }}" data-testid="internal-shipment-account-link" class="btn btn-s">Open account detail</a>
                @endif
                @if($canViewKyc && isset($accountSummary['account']))
                    <a href="{{ route('internal.kyc.show', $accountSummary['account']) }}" data-testid="internal-shipment-kyc-link" class="btn btn-s">Open KYC detail</a>
                @endif
            </div>
        </div>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-shipment-operational-state-card">
        <div class="card-title">Carrier and tracking</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Carrier</dt>
            <dd style="margin:0;color:var(--tx)">{{ $carrierSummary['carrier_label'] }}</dd>

            <dt style="color:var(--tm)">Service</dt>
            <dd style="margin:0;color:var(--tx)">{{ $carrierSummary['service_label'] }}</dd>

            <dt style="color:var(--tm)">Carrier shipment</dt>
            <dd style="margin:0;color:var(--tx)">{{ $carrierSummary['carrier_shipment_id'] }}</dd>

            <dt style="color:var(--tm)">Tracking</dt>
            <dd style="margin:0;color:var(--tx)">{{ $trackingSummary['tracking_number'] }}</dd>

            <dt style="color:var(--tm)">AWB</dt>
            <dd style="margin:0;color:var(--tx)">{{ $trackingSummary['awb_number'] }}</dd>

            <dt style="color:var(--tm)">Public tracking</dt>
            <dd style="margin:0;color:var(--tx)">
                <div>{{ $publicTracking['label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $publicTracking['detail'] }}</div>
                @if(!empty($publicTracking['url']))
                    <div style="margin-top:10px">
                        <a href="{{ $publicTracking['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn btn-s"
                           data-testid="internal-shipment-public-tracking-link">Open public tracking page</a>
                    </div>
                @endif
                <div style="font-size:12px;color:var(--tm);margin-top:6px">Enabled: {{ $publicTracking['enabled_at'] }} • Expires: {{ $publicTracking['expires_at'] }}</div>
            </dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-shipment-parcels-card">
        <div class="card-title">Parcel summary</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($shipment->parcels as $parcel)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">Parcel {{ $parcel->sequence }}</div>
                    <div style="font-size:13px;color:var(--td)">
                        Weight: {{ number_format((float) ($parcel->weight ?? 0), 2) }} kg
                        • Chargeable: {{ number_format((float) $parcel->chargeableWeight(), 2) }} kg
                    </div>
                    <div style="font-size:12px;color:var(--tm)">
                        {{ strtoupper((string) ($parcel->packaging_type ?? 'custom')) }}
                        @if($parcel->reference)
                            • Ref: {{ $parcel->reference }}
                        @endif
                        @if($parcel->description)
                            • {{ $parcel->description }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">No parcel records are visible on this shipment.</div>
            @endforelse
        </div>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-shipment-timeline-card">
        <div class="card-title">Timeline preview</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">
            Current state: <strong style="color:var(--tx)">{{ $timeline['current_status_label'] }}</strong>
            • Last update: {{ $timeline['last_updated'] ?? '—' }}
            • Events: {{ number_format($timeline['total_events']) }}
        </div>

        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($timeline['events'] as $event)
                <div data-testid="internal-shipment-event-item" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $event['event_type_label'] }}</div>
                    <div style="font-size:13px;color:var(--td)">{{ $event['description'] }}</div>
                    <div style="font-size:12px;color:var(--tm);margin-top:6px">
                        {{ $event['status_label'] }} • {{ $event['source_label'] }} • {{ $event['event_time_display'] ?? '—' }}
                        @if(!empty($event['location']))
                            • {{ $event['location'] }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">No timeline events are currently available.</div>
            @endforelse
        </div>
    </section>

    <section class="card" data-testid="internal-shipment-documents-card">
        <div class="card-title">Document summary</div>
        <p style="margin:0 0 12px;color:var(--td);font-size:13px">{{ $documentHeadline }}</p>
        @if($canViewDocuments)
            <div style="margin:0 0 12px">
                <a href="{{ route('internal.shipments.documents.index', $shipment) }}"
                   class="btn btn-s"
                   data-testid="internal-shipment-documents-link">Open document workspace</a>
            </div>
        @endif
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($documents as $document)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $document['document_type_label'] }}</div>
                    <div style="font-size:13px;color:var(--td)">{{ $document['filename'] }}</div>
                    <div style="font-size:12px;color:var(--tm)">
                        {{ $document['carrier_label'] }} • {{ $document['format_label'] }} • {{ $document['retrieval_mode_label'] }} • {{ $document['size_label'] }} • {{ $document['created_at_display'] ?? '—' }}
                    </div>
                    @if(!empty($document['tracking_number']))
                        <div style="font-size:12px;color:var(--td);margin-top:6px">Tracking: {{ $document['tracking_number'] }}</div>
                    @endif
                    @if(!empty($document['notes']))
                        <div style="font-size:12px;color:var(--td);margin-top:6px">{{ collect($document['notes'])->implode(' • ') }}</div>
                    @endif
                </div>
            @empty
                <div class="empty-state">No safe carrier document summaries are currently available.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="card" data-testid="internal-shipment-notifications-card" style="margin-bottom:24px">
    <div class="card-title">Notification activity</div>
    @if(!$notifications['visible'])
        <div class="empty-state">Notification visibility is not enabled for this role.</div>
    @else
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px">
            <div>
                <div style="font-size:12px;color:var(--tm)">Total projections</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($notifications['total_count']) }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Sent or delivered</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($notifications['delivered_count']) }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Needs review</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($notifications['issue_count']) }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Latest activity</div>
                <div style="font-weight:700;color:var(--tx)">{{ $notifications['latest_created_at'] }}</div>
            </div>
        </div>

        <div style="font-size:12px;color:var(--tm);margin-bottom:12px">
            Channels:
            @if($notifications['channels'] !== [])
                {{ collect($notifications['channels'])->implode(' / ') }}
            @else
                None recorded yet
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($notifications['items'] as $notification)
                <div data-testid="internal-shipment-notification-item" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $notification['subject'] }}</div>
                            <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $notification['event_type_label'] }} / {{ $notification['channel_label'] }}</div>
                        </div>
                        <span class="badge">{{ $notification['status_label'] }}</span>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">
                        Created: {{ $notification['created_at_display'] }}
                        @if($notification['sent_at_display'] !== '-')
                            | Sent: {{ $notification['sent_at_display'] }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">No shipment-linked notification activity is currently visible.</div>
            @endforelse
        </div>
    @endif
</section>

<section class="card" data-testid="internal-shipment-kyc-summary-card">
    <div class="card-title">KYC and restriction effect</div>
    @if($kycSummary)
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm)">KYC status</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['label'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Shipment effect</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['queue_summary'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Further action</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['action_label'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">Blocked shipments</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($kycSummary['blocked_shipments_count']) }}</div>
            </div>
        </div>

        @if($kycSummary['restriction_names'] !== [])
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                @foreach($kycSummary['restriction_names'] as $restrictionName)
                    <span class="badge">{{ $restrictionName }}</span>
                @endforeach
            </div>
        @endif
    @else
        <div class="empty-state">No linked account KYC summary is available for this shipment.</div>
    @endif
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('click', async function (event) {
    const trigger = event.target.closest('[data-copy-text]');

    if (!trigger) {
        return;
    }

    const text = trigger.getAttribute('data-copy-text') || '';
    const statusId = trigger.getAttribute('data-copy-target');
    const statusNode = statusId ? document.getElementById(statusId) : null;

    if (!text) {
        return;
    }

    const setStatus = function (message) {
        if (statusNode) {
            statusNode.textContent = message;
        }
    };

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const helper = document.createElement('textarea');
            helper.value = text;
            helper.setAttribute('readonly', 'readonly');
            helper.style.position = 'absolute';
            helper.style.left = '-9999px';
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            document.body.removeChild(helper);
        }

        setStatus('Public tracking link copied for internal follow-up.');
    } catch (error) {
        setStatus('Unable to copy the public tracking link automatically. You can still open it directly.');
    }
});
</script>
@endpush
