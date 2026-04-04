@extends('layouts.app')
@section('title', 'Internal tickets')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal home</a>
            <span style="margin:0 6px">/</span>
            <span>Tickets</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Internal tickets</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            Operational visibility into customer helpdesk tickets, linked accounts, linked shipments, assignees, and safe customer conversation history.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">Refresh</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">Back to internal home</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="TKT" label="Total tickets" :value="number_format($stats['total'])" />
    <x-stat-card icon="OPN" label="Open queue" :value="number_format($stats['open'])" />
    <x-stat-card icon="URG" label="Urgent" :value="number_format($stats['urgent'])" />
    <x-stat-card icon="SHP" label="Linked shipments" :value="number_format($stats['linked_shipments'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Search and filters</div>
    <form method="GET" action="{{ route('internal.tickets.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="ticket-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Search</label>
            <input id="ticket-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Ticket number, subject, requester, account, shipment, or assignee">
        </div>
        <div>
            <label for="ticket-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Status</label>
            <select id="ticket-status" name="status" class="input" data-testid="internal-ticket-filter-status">
                <option value="">All</option>
                @foreach($statusOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-priority" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Priority</label>
            <select id="ticket-priority" name="priority" class="input" data-testid="internal-ticket-filter-priority">
                <option value="">All</option>
                @foreach($priorityOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['priority'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-category" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Category</label>
            <select id="ticket-category" name="category" class="input">
                <option value="">All</option>
                @foreach($categoryOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-account-filter" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Linked account</label>
            <select id="ticket-account-filter" name="account_id" class="input" data-testid="internal-ticket-filter-account">
                <option value="">All accounts</option>
                @foreach($accountFilterOptions as $account)
                    <option value="{{ $account['id'] }}" @selected($filters['account_id'] === $account['id'])>{{ $account['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-shipment-scope" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Linked shipment</label>
            <select id="ticket-shipment-scope" name="shipment_scope" class="input" data-testid="internal-ticket-filter-shipment">
                <option value="">All tickets</option>
                @foreach($shipmentScopeOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['shipment_scope'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-assignee-filter" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Assignee</label>
            <select id="ticket-assignee-filter" name="assignee_id" class="input" data-testid="internal-ticket-filter-assignee">
                <option value="">All assignees</option>
                <option value="{{ $assigneeFilterUnassigned }}" @selected($filters['assignee_id'] === $assigneeFilterUnassigned)>Unassigned</option>
                @foreach($assigneeFilterOptions as $assignee)
                    <option value="{{ $assignee['id'] }}" @selected($filters['assignee_id'] === $assignee['id'])>{{ $assignee['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr">Apply</button>
            <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">Reset</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-tickets-table">
    <div class="card-title">Visible tickets</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Ticket</th>
                <th>Requester and account</th>
                <th>Status and priority</th>
                <th>Linked shipment</th>
                <th>Assignee</th>
                <th>Recent activity</th>
            </tr>
            </thead>
            <tbody>
            @forelse($tickets as $row)
                <tr data-testid="internal-tickets-row">
                    <td>
                        <a href="{{ route('internal.tickets.show', $row['route_key']) }}" data-testid="internal-tickets-open-link" style="font-weight:700;color:var(--tx);text-decoration:none">
                            {{ $row['ticket_number'] }}
                        </a>
                        <div style="font-size:13px;color:var(--tx);margin-top:4px">{{ $row['subject'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['category_label'] }}</div>
                    </td>
                    <td>
                        @if($row['requester'])
                            <div style="font-weight:700;color:var(--tx)">{{ $row['requester']['name'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['requester']['email'] }}</div>
                        @endif
                        @if($row['account_summary'])
                            <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $row['account_summary']['name'] }} - {{ $row['account_summary']['type_label'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--tx)">{{ $row['status_label'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['priority_label'] }}</div>
                    </td>
                    <td>
                        @if($row['shipment_summary'])
                            <div style="font-weight:700;color:var(--tx)">{{ $row['shipment_summary']['reference'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['shipment_summary']['status_label'] }}</div>
                        @else
                            <div style="font-size:12px;color:var(--td)">No linked shipment</div>
                        @endif
                    </td>
                    <td>
                        @if($row['assignee'])
                            <div style="font-weight:700;color:var(--tx)">{{ $row['assignee']['name'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $row['assigned_team'] }}</div>
                        @else
                            <div style="font-size:12px;color:var(--td)">{{ $row['assigned_team'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-size:13px;color:var(--tx)">{{ $row['recent_activity_summary'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $row['recent_activity_at'] }} - {{ number_format($row['replies_count']) }} replies</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">No tickets match the current filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $tickets->links() }}</div>
</div>
@endsection
