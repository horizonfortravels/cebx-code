@extends('layouts.app')
@section('title', 'Create internal ticket')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal home</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.tickets.index') }}" style="color:inherit;text-decoration:none">Tickets</a>
            <span style="margin:0 6px">/</span>
            <span>Create ticket</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Create internal ticket</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            Create an internal helpdesk ticket with explicit account context and optional shipment linkage. Shipment-linked tickets are created from shipment detail so the account and shipment context stay aligned and auditable.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($selectedShipment)
            <a href="{{ route('internal.shipments.show', $selectedShipment['shipment']) }}" class="btn btn-s">Back to shipment</a>
        @elseif($selectedAccount)
            <a href="{{ route('internal.accounts.show', $selectedAccount['account']) }}" class="btn btn-s">Back to account</a>
        @else
            <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">Back to tickets</a>
        @endif
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

<form method="POST" action="{{ route('internal.tickets.store') }}" class="grid-2" data-testid="internal-ticket-create-form">
    @csrf

    <x-card title="Linked context">
        @if($selectedAccount)
            <input type="hidden" name="account_id" value="{{ $selectedAccount['id'] }}">
            <div data-testid="internal-ticket-linked-account-card" style="display:flex;flex-direction:column;gap:8px">
                <div style="font-weight:700;color:var(--tx)">{{ $selectedAccount['name'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $selectedAccount['type_label'] }} - {{ $selectedAccount['slug'] }}</div>
                @if($selectedAccount['organization_label'])
                    <div style="font-size:12px;color:var(--tm)">Organization: {{ $selectedAccount['organization_label'] }}</div>
                @endif
            </div>
        @else
            <div>
                <label for="ticket-account-id" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Linked account</label>
                <select id="ticket-account-id" name="account_id" class="input" data-testid="internal-ticket-account-select" required>
                    <option value="">Select an account</option>
                    @foreach($accountOptions as $option)
                        <option value="{{ $option['id'] }}" @selected(old('account_id') === $option['id'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <div style="font-size:12px;color:var(--td);margin-top:8px">
                    General tickets are always created with a linked account. Shipment linkage is added from shipment detail to preserve exact context.
                </div>
            </div>
        @endif

        @if($selectedShipment)
            <input type="hidden" name="shipment_id" value="{{ $selectedShipment['id'] }}">
            <div data-testid="internal-ticket-linked-shipment-card" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--bd);display:flex;flex-direction:column;gap:8px">
                <div style="font-weight:700;color:var(--tx)">{{ $selectedShipment['reference'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $selectedShipment['status_label'] }}</div>
                <div style="font-size:12px;color:var(--tm)">{{ $selectedShipment['tracking_summary'] }}</div>
            </div>
        @endif
    </x-card>

    <x-card title="Ticket summary">
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            <div style="grid-column:1 / -1">
                <label for="ticket-subject" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Subject</label>
                <input id="ticket-subject" name="subject" type="text" class="input" value="{{ old('subject') }}" maxlength="300" required>
            </div>
            <div>
                <label for="ticket-category" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Category</label>
                <select id="ticket-category" name="category" class="input" required>
                    @foreach(['shipping' => 'Shipping', 'billing' => 'Billing', 'technical' => 'Technical', 'account' => 'Account', 'carrier' => 'Carrier', 'general' => 'General'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $defaults['category']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ticket-priority" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Priority</label>
                <select id="ticket-priority" name="priority" class="input" required>
                    @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', $defaults['priority']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:1 / -1">
                <label for="ticket-description" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Request summary</label>
                <textarea id="ticket-description" name="description" class="input" rows="7" maxlength="5000" required>{{ old('description') }}</textarea>
                <div style="font-size:12px;color:var(--td);margin-top:8px">
                    This summary stays internal, auditable, and safe. Internal note workflows and ticket assignment changes are intentionally out of scope for this phase.
                </div>
            </div>
        </div>
    </x-card>

    <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap">
        @if($selectedShipment)
            <a href="{{ route('internal.shipments.show', $selectedShipment['shipment']) }}" class="btn btn-s">Cancel</a>
        @elseif($selectedAccount)
            <a href="{{ route('internal.accounts.show', $selectedAccount['account']) }}" class="btn btn-s">Cancel</a>
        @else
            <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">Cancel</a>
        @endif
        <button type="submit" class="btn btn-pr" data-testid="internal-ticket-create-submit">Create ticket</button>
    </div>
</form>
@endsection
