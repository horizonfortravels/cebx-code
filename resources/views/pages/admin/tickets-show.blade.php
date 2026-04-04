@extends('layouts.app')
@section('title', 'Ticket detail')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">Internal home</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.tickets.index') }}" style="color:inherit;text-decoration:none">Tickets</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['ticket_number'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">Ticket detail</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            {{ $detail['ticket_number'] }} - {{ $detail['subject'] }} - {{ $detail['category_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($detail['shipment_summary'] && $canViewShipment)
            <a href="{{ route('internal.shipments.show', $detail['shipment_summary']['shipment']) }}" class="btn btn-s" data-testid="internal-ticket-shipment-link">Open linked shipment</a>
        @endif
        @if($detail['account_summary'] && $canViewAccount)
            <a href="{{ route('internal.accounts.show', $detail['account_summary']['account']) }}" class="btn btn-s" data-testid="internal-ticket-account-link">Open linked account</a>
        @endif
        <a href="{{ route('internal.tickets.index') }}" class="btn btn-pr">Back to tickets</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="TKT" label="Ticket" :value="$detail['ticket_number']" />
    <x-stat-card icon="STA" label="Status" :value="$detail['status_label']" />
    <x-stat-card icon="PRI" label="Priority" :value="$detail['priority_label']" />
    <x-stat-card icon="ACT" label="Recent activity" :value="$detail['recent_activity_at']" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-ticket-summary-card">
        <div class="card-title">Ticket summary</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Ticket number</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['ticket_number'] }}</dd>
            <dt style="color:var(--tm)">Subject</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['subject'] }}</dd>
            <dt style="color:var(--tm)">Category</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['category_label'] }}</dd>
            <dt style="color:var(--tm)">Status</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['status_label'] }}</dd>
            <dt style="color:var(--tm)">Priority</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['priority_label'] }}</dd>
            <dt style="color:var(--tm)">Created at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['created_at_label'] }}</dd>
            <dt style="color:var(--tm)">Updated at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['updated_at_label'] }}</dd>
            <dt style="color:var(--tm)">Resolved at</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['resolved_at_label'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-ticket-context-card">
        <div class="card-title">Requester and linked context</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">Requester</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['requester'])
                    {{ $detail['requester']['name'] }} - {{ $detail['requester']['email'] }}
                @else
                    Unknown requester
                @endif
            </dd>
            <dt style="color:var(--tm)">Assignee</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['assignee'])
                    {{ $detail['assignee']['name'] }} - {{ $detail['assigned_team'] }}
                @else
                    {{ $detail['assigned_team'] }}
                @endif
            </dd>
            <dt style="color:var(--tm)">Account</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['account_summary'])
                    {{ $detail['account_summary']['name'] }} - {{ $detail['account_summary']['type_label'] }} - {{ $detail['account_summary']['slug'] }}
                @else
                    No linked account summary
                @endif
            </dd>
            @if($detail['account_summary'] && !empty($detail['account_summary']['organization_label']))
                <dt style="color:var(--tm)">Organization</dt>
                <dd style="margin:0;color:var(--tx)">{{ $detail['account_summary']['organization_label'] }}</dd>
            @endif
            <dt style="color:var(--tm)">Linked shipment</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['shipment_summary'])
                    {{ $detail['shipment_summary']['reference'] }} - {{ $detail['shipment_summary']['status_label'] }} - {{ $detail['shipment_summary']['tracking_summary'] }}
                @else
                    No linked shipment
                @endif
            </dd>
        </dl>
    </section>
</div>

<div class="grid-2">
    <section class="card" data-testid="internal-ticket-request-card">
        <div class="card-title">Request summary</div>
        <div style="font-size:14px;line-height:1.8;color:var(--tx)">{{ $detail['description'] }}</div>
    </section>

    <section class="card" data-testid="internal-ticket-activity-card">
        <div class="card-title">Recent activity</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            @foreach($detail['recent_activity'] as $activity)
                <article style="padding:12px;border:1px solid var(--bd);border-radius:12px" data-testid="internal-ticket-activity-entry">
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $activity['actor_label'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $activity['actor_name'] }}</div>
                        </div>
                        <div style="font-size:12px;color:var(--td)">{{ $activity['created_at_label'] }}</div>
                    </div>
                    <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $activity['excerpt'] }}</div>
                </article>
            @endforeach
        </div>
    </section>
</div>

<div class="grid-2" style="margin-top:24px">
    <section class="card" data-testid="internal-ticket-workflow-card">
        <div class="card-title">Workflow, triage, and assignment</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0 0 16px">
            <dt style="color:var(--tm)">Current status</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['status_label'] }}</dd>
            <dt style="color:var(--tm)">Current priority</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['priority_label'] }}</dd>
            <dt style="color:var(--tm)">Current category</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['category_label'] }}</dd>
            <dt style="color:var(--tm)">Current assignee</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['assignee'])
                    {{ $detail['assignee']['name'] }} - {{ $detail['assigned_team'] }}
                @else
                    {{ $detail['assigned_team'] }}
                @endif
            </dd>
        </dl>

        @if($canManageTickets)
            <div style="display:grid;gap:16px">
                <form method="POST" action="{{ route('internal.tickets.triage', $detail['route_key']) }}" data-testid="internal-ticket-triage-form">
                    @csrf
                    <div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:10px">Update ticket triage</div>
                    <div style="display:grid;gap:10px">
                        <div>
                            <label for="ticket-priority-select" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Priority</label>
                            <select id="ticket-priority-select" name="priority" class="input" data-testid="internal-ticket-priority-select">
                                @foreach($triagePriorityOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($detail['priority_key'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ticket-category-select" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Category</label>
                            <select id="ticket-category-select" name="category" class="input" data-testid="internal-ticket-category-select">
                                @foreach($triageCategoryOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($detail['category_key'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ticket-triage-note" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Triage note</label>
                            <textarea id="ticket-triage-note" name="note" class="input" rows="3" data-testid="internal-ticket-triage-note" placeholder="Optional internal context for the triage change."></textarea>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-pr" data-testid="internal-ticket-triage-submit">Update triage</button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="{{ route('internal.tickets.status', $detail['route_key']) }}" data-testid="internal-ticket-status-form">
                    @csrf
                    <div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:10px">Update workflow status</div>
                    <div style="display:grid;gap:10px">
                        <div>
                            <label for="ticket-status-select" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Status</label>
                            <select id="ticket-status-select" name="status" class="input" data-testid="internal-ticket-status-select">
                                @foreach($workflowStatusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($detail['status_key'] === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ticket-status-note" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Workflow note</label>
                            <textarea id="ticket-status-note" name="note" class="input" rows="3" data-testid="internal-ticket-status-note" placeholder="Optional internal context for the status change."></textarea>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-pr" data-testid="internal-ticket-status-submit">Update status</button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="{{ route('internal.tickets.assignment', $detail['route_key']) }}" data-testid="internal-ticket-assignment-form">
                    @csrf
                    <div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:10px">Update assignee</div>
                    <div style="display:grid;gap:10px">
                        <div>
                            <label for="ticket-assignment-select" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Assignee</label>
                            <select id="ticket-assignment-select" name="assigned_to" class="input" data-testid="internal-ticket-assignment-select">
                                <option value="">Unassigned</option>
                                @foreach($assignableUsers as $assignee)
                                    <option value="{{ $assignee['id'] }}" @selected(($detail['assignee']['id'] ?? null) === $assignee['id'])>{{ $assignee['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ticket-assignment-note" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Assignment note</label>
                            <textarea id="ticket-assignment-note" name="note" class="input" rows="3" data-testid="internal-ticket-assignment-note" placeholder="Optional internal context for the assignment change."></textarea>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-pr" data-testid="internal-ticket-assignment-submit">Update assignee</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </section>

    <section class="card" data-testid="internal-ticket-notes-card">
        <div class="card-title">Internal notes</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">{{ $detail['internal_notes_summary'] }}</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($detail['internal_notes'] as $note)
                <article style="padding:12px;border:1px solid var(--bd);border-radius:12px" data-testid="internal-ticket-note-entry">
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $note['actor_label'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $note['actor_name'] }}</div>
                        </div>
                        <div style="font-size:12px;color:var(--td)">{{ $note['created_at_label'] }}</div>
                    </div>
                    <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $note['body'] }}</div>
                </article>
            @empty
                <div style="font-size:13px;color:var(--td)">No internal notes recorded yet.</div>
            @endforelse
        </div>

        @if($canManageTickets)
            <form method="POST" action="{{ route('internal.tickets.notes.store', $detail['route_key']) }}" data-testid="internal-ticket-note-form" style="margin-top:16px">
                @csrf
                <label for="ticket-note-body" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">Add internal note</label>
                <textarea id="ticket-note-body" name="body" class="input" rows="4" data-testid="internal-ticket-note-body" placeholder="Internal-only note for support and operations."></textarea>
                <div style="margin-top:10px">
                    <button type="submit" class="btn btn-pr" data-testid="internal-ticket-note-submit">Add note</button>
                </div>
            </form>
        @endif
    </section>
</div>

<section class="card" data-testid="internal-ticket-workflow-activity-card" style="margin-top:24px">
    <div class="card-title">Workflow activity</div>
    <div style="font-size:13px;color:var(--td);margin-bottom:12px">{{ $detail['workflow_activity_summary'] }}</div>
    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse($detail['workflow_activity'] as $activity)
            <article style="padding:12px;border:1px solid var(--bd);border-radius:12px" data-testid="internal-ticket-workflow-activity-entry">
                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                    <div>
                        <div style="font-weight:700;color:var(--tx)">{{ $activity['headline'] }}</div>
                        <div style="font-size:12px;color:var(--td)">{{ $activity['actor_name'] }}</div>
                    </div>
                    <div style="font-size:12px;color:var(--td)">{{ $activity['created_at_label'] }}</div>
                </div>
                <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $activity['detail'] }}</div>
            </article>
        @empty
            <div style="font-size:13px;color:var(--td)">No workflow activity recorded yet.</div>
        @endforelse
    </div>
</section>
@endsection
