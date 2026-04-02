@extends('layouts.app')
@section('title', 'Shipment Documents')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal workspace</a>
            <span style="margin:0 6px">/</span>
            <span>Shipment documents</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Shipment documents</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            Internal read-only document and label surface for carrier artifacts. This page keeps raw content storage coordinates, opaque payloads, and other unsafe metadata hidden while reusing the live shipment document contract.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($canViewShipmentDetail)
            <a href="{{ route('internal.shipments.show', $shipment) }}" class="btn btn-s">Back to shipment detail</a>
        @endif
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Internal home</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="REF" label="Reference" :value="$shipmentSummary['reference']" />
    <x-stat-card icon="CAR" label="Carrier" :value="$shipmentSummary['carrier_label']" />
    <x-stat-card icon="TRK" label="Tracking" :value="$shipmentSummary['tracking_number']" />
    <x-stat-card icon="DOC" label="Artifacts" :value="number_format($documents->count())" />
</div>

<section class="card" data-testid="internal-shipment-documents-workspace" style="margin-bottom:24px">
    <div class="card-title">Document workspace</div>
    <p style="margin:0 0 12px;color:var(--td);font-size:13px">{{ $documentHeadline }}</p>
    <div style="font-size:12px;color:var(--tm);margin-bottom:14px">AWB: {{ $shipmentSummary['awb_number'] }}</div>

    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse($documents as $document)
            <div data-testid="internal-shipment-document-row" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding:16px;border:1px solid var(--bd);border-radius:18px;background:white">
                <div style="display:flex;flex-direction:column;gap:8px;min-width:260px;flex:1">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <span data-testid="internal-shipment-document-type" style="font-size:18px;font-weight:800;color:var(--tx)">{{ $document['document_type_label'] }}</span>
                        <span class="td-mono" data-testid="internal-shipment-document-format" style="font-size:12px;color:var(--tm)">{{ $document['format_label'] }}</span>
                        <span class="td-mono" data-testid="internal-shipment-document-carrier" style="font-size:12px;color:var(--tm)">{{ $document['carrier_label'] }}</span>
                    </div>
                    <div data-testid="internal-shipment-document-filename" style="font-size:14px;color:var(--td)">{{ $document['filename'] }}</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
                        <div>
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">Availability</div>
                            <div data-testid="internal-shipment-document-availability" style="font-weight:700;color:var(--tx)">{{ $document['availability_label'] }}</div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">Retrieval mode</div>
                            <div style="font-weight:700;color:var(--tx)">{{ $document['retrieval_mode_label'] }}</div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">Size</div>
                            <div style="font-weight:700;color:var(--tx)">{{ $document['size_label'] }}</div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">Created</div>
                            <div style="font-weight:700;color:var(--tx)">{{ $document['created_at_display'] }}</div>
                        </div>
                    </div>
                    @if(!empty($document['tracking_number']))
                        <div data-testid="internal-shipment-document-tracking" style="font-size:12px;color:var(--td)">Tracking: {{ $document['tracking_number'] }}</div>
                    @endif
                    @if(!empty($document['notes']))
                        <div data-testid="internal-shipment-document-notes" style="font-size:12px;color:var(--td)">{{ collect($document['notes'])->implode(' | ') }}</div>
                    @endif
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;min-width:220px">
                    @if(!empty($document['previewable']) && !empty($document['preview_route']))
                        <a href="{{ $document['preview_route'] }}"
                           data-testid="internal-shipment-document-preview-link"
                           class="btn btn-s"
                           target="_blank"
                           rel="noopener noreferrer">Preview PDF</a>
                    @endif
                    <a href="{{ $document['download_route'] }}"
                       data-testid="internal-shipment-document-download-link"
                       class="btn btn-pr"
                       download="{{ $document['filename'] }}">Download document</a>
                </div>
            </div>
        @empty
            <div data-testid="internal-shipment-documents-empty-state" class="empty-state">
                No carrier documents are currently available for this shipment.
            </div>
        @endforelse
    </div>
</section>
@endsection
