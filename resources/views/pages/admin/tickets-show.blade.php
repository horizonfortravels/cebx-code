@extends('layouts.app')
@section('title', 'تفاصيل التذكرة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.tickets.index') }}" style="color:inherit;text-decoration:none">التذاكر</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['ticket_number'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل التذكرة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            {{ $detail['ticket_number'] }} - {{ $detail['subject'] }} - {{ $detail['category_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($detail['shipment_summary'] && $canViewShipment)
            <a href="{{ route('internal.shipments.show', $detail['shipment_summary']['shipment']) }}" class="btn btn-s" data-testid="internal-ticket-shipment-link">فتح الشحنة المرتبطة</a>
        @endif
        @if($detail['account_summary'] && $canViewAccount)
            <a href="{{ route('internal.accounts.show', $detail['account_summary']['account']) }}" class="btn btn-s" data-testid="internal-ticket-account-link">فتح الحساب المرتبط</a>
        @endif
        <a href="{{ route('internal.tickets.index') }}" class="btn btn-pr">العودة إلى التذاكر</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="TKT" label="التذكرة" :value="$detail['ticket_number']" />
    <x-stat-card icon="STA" label="الحالة" :value="$detail['status_label']" />
    <x-stat-card icon="PRI" label="الأولوية" :value="$detail['priority_label']" />
    <x-stat-card icon="ACT" label="آخر نشاط رد" :value="$detail['recent_activity_at']" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-ticket-summary-card">
        <div class="card-title">ملخص التذكرة</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">رقم التذكرة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['ticket_number'] }}</dd>
            <dt style="color:var(--tm)">الموضوع</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['subject'] }}</dd>
            <dt style="color:var(--tm)">الفئة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['category_label'] }}</dd>
            <dt style="color:var(--tm)">الحالة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['status_label'] }}</dd>
            <dt style="color:var(--tm)">الأولوية</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['priority_label'] }}</dd>
            <dt style="color:var(--tm)">تاريخ الإنشاء</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['created_at_label'] }}</dd>
            <dt style="color:var(--tm)">آخر تحديث</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['updated_at_label'] }}</dd>
            <dt style="color:var(--tm)">تاريخ الحل</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['resolved_at_label'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-ticket-context-card">
        <div class="card-title">مقدم الطلب والسياق المرتبط</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">مقدم الطلب</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['requester'])
                    {{ $detail['requester']['name'] }} - {{ $detail['requester']['email'] }}
                @else
                    مقدم طلب غير معروف
                @endif
            </dd>
            <dt style="color:var(--tm)">المسند إليه</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['assignee'])
                    {{ $detail['assignee']['name'] }} - {{ $detail['assigned_team'] }}
                @else
                    {{ $detail['assigned_team'] }}
                @endif
            </dd>
            <dt style="color:var(--tm)">الحساب</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['account_summary'])
                    {{ $detail['account_summary']['name'] }} - {{ $detail['account_summary']['type_label'] }} - {{ $detail['account_summary']['slug'] }}
                @else
                    لا يوجد ملخص حساب مرتبط
                @endif
            </dd>
            @if($detail['account_summary'] && !empty($detail['account_summary']['organization_label']))
                <dt style="color:var(--tm)">المنظمة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $detail['account_summary']['organization_label'] }}</dd>
            @endif
            <dt style="color:var(--tm)">الشحنة المرتبطة</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($detail['shipment_summary'])
                    {{ $detail['shipment_summary']['reference'] }} - {{ $detail['shipment_summary']['status_label'] }} - {{ $detail['shipment_summary']['tracking_summary'] }}
                @else
                    لا توجد شحنة مرتبطة
                @endif
            </dd>
        </dl>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-ticket-workflow-card">
        <div class="card-title">سير العمل والملكية</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">
            حدّث حالة سير العمل والإسناد الداخليين دون تغيير فصل الرد الظاهر للعميل عن الملاحظة الداخلية.
        </div>

        @if($canManageTicketActions)
            <form method="POST" action="{{ route('internal.tickets.status', $detail['route_key']) }}" style="padding-bottom:16px;border-bottom:1px solid var(--bd);margin-bottom:16px" data-testid="internal-ticket-status-form">
                @csrf
                <label class="form-label" for="internal-ticket-status-select">حالة سير العمل</label>
                <select id="internal-ticket-status-select" name="status" class="form-input" data-testid="internal-ticket-status-select">
                    @foreach($manualStatusOptions as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $detail['status_key']) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="internal-ticket-status-note" style="margin-top:12px">سبب داخلي</label>
                <textarea id="internal-ticket-status-note" name="note" class="form-input" rows="3" data-testid="internal-ticket-status-note" placeholder="مطلوب عند النقل إلى بانتظار العميل أو تم الحل أو مغلقة.">{{ old('note') }}</textarea>
                <div style="display:flex;justify-content:flex-end;margin-top:12px">
                    <button type="submit" class="btn btn-pr" data-testid="internal-ticket-status-submit">تحديث الحالة</button>
                </div>
            </form>

            <form method="POST" action="{{ route('internal.tickets.assignment', $detail['route_key']) }}" data-testid="internal-ticket-assignment-form">
                @csrf
                <label class="form-label" for="internal-ticket-assignment-select">إسناد التذكرة</label>
                <select id="internal-ticket-assignment-select" name="assigned_to" class="form-input" data-testid="internal-ticket-assignment-select">
                    <option value="">غير مسندة</option>
                    @foreach($assignableUsers as $assignee)
                        <option value="{{ $assignee['id'] }}" @selected(old('assigned_to', $detail['assignee']['id'] ?? '') === $assignee['id'])>{{ $assignee['label'] }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="internal-ticket-assignment-note" style="margin-top:12px">سبب داخلي</label>
                <textarea id="internal-ticket-assignment-note" name="note" class="form-input" rows="3" data-testid="internal-ticket-assignment-note" placeholder="سياق داخلي اختياري لعملية تسليم الإسناد.">{{ old('note') }}</textarea>
                <div style="display:flex;justify-content:flex-end;margin-top:12px">
                    <button type="submit" class="btn btn-s" data-testid="internal-ticket-assignment-submit">تحديث الإسناد</button>
                </div>
            </form>
        @else
            <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">حالة سير العمل</dt>
                <dd style="margin:0;color:var(--tx)">{{ $detail['status_label'] }}</dd>
                <dt style="color:var(--tm)">المسند إليه</dt>
                <dd style="margin:0;color:var(--tx)">
                    @if($detail['assignee'])
                        {{ $detail['assignee']['name'] }} - {{ $detail['assigned_team'] }}
                    @else
                        {{ $detail['assigned_team'] }}
                    @endif
                </dd>
            </dl>
        @endif
    </section>

    <section class="card" data-testid="internal-ticket-triage-card">
        <div class="card-title">الأولوية والفئة</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">
            اجعل فرز التذكرة واضحًا حتى يبقى الصف سهل الترتيب والتسليم.
        </div>

        @if($canManageTicketActions)
            <form method="POST" action="{{ route('internal.tickets.triage', $detail['route_key']) }}" data-testid="internal-ticket-triage-form">
                @csrf
                <label class="form-label" for="internal-ticket-priority-select">الأولوية</label>
                <select id="internal-ticket-priority-select" name="priority" class="form-input" data-testid="internal-ticket-priority-select">
                    @foreach($triagePriorityOptions as $key => $label)
                        <option value="{{ $key }}" @selected(old('priority', $detail['priority_key']) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="internal-ticket-category-select" style="margin-top:12px">الفئة</label>
                <select id="internal-ticket-category-select" name="category" class="form-input" data-testid="internal-ticket-category-select">
                    @foreach($triageCategoryOptions as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $detail['category_key']) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="internal-ticket-triage-note" style="margin-top:12px">سبب داخلي</label>
                <textarea id="internal-ticket-triage-note" name="note" class="form-input" rows="3" data-testid="internal-ticket-triage-note" placeholder="سياق داخلي اختياري لتحديث الفرز.">{{ old('note') }}</textarea>
                <div style="display:flex;justify-content:flex-end;margin-top:12px">
                    <button type="submit" class="btn btn-pr" data-testid="internal-ticket-triage-submit">تحديث الفرز</button>
                </div>
            </form>
        @else
            <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">الأولوية</dt>
                <dd style="margin:0;color:var(--tx)">{{ $detail['priority_label'] }}</dd>
                <dt style="color:var(--tm)">الفئة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $detail['category_label'] }}</dd>
            </dl>
        @endif
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-ticket-workflow-activity-card">
        <div class="card-title">نشاط سير العمل</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">{{ $detail['workflow_activity_summary'] }}</div>

        @if(count($detail['workflow_activity']) > 0)
            <div style="display:flex;flex-direction:column;gap:12px">
                @foreach($detail['workflow_activity'] as $activity)
                    <article style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.02)">
                        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                            <div>
                                <div style="font-weight:700;color:var(--tx)">{{ $activity['headline'] }}</div>
                                <div style="font-size:12px;color:var(--td)">{{ $activity['actor_name'] }}</div>
                            </div>
                            <div style="font-size:12px;color:var(--td)">{{ $activity['created_at_label'] }}</div>
                        </div>
                        <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $activity['detail'] }}</div>
                    </article>
                @endforeach
            </div>
        @else
            <div style="font-size:13px;color:var(--td)">لا يوجد نشاط سير عمل مسجل بعد.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-ticket-request-card">
        <div class="card-title">ملخص الطلب</div>
        <div style="font-size:14px;line-height:1.8;color:var(--tx)">{{ $detail['description'] }}</div>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-ticket-activity-card">
        <div class="card-title">سلسلة المحادثة الخارجية</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">{{ $detail['recent_activity_summary'] }}</div>
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
                    <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $activity['body'] }}</div>
                </article>
            @endforeach
        </div>

        @if($canManageThread)
            <form method="POST" action="{{ route('internal.tickets.reply.store', $detail['route_key']) }}" style="margin-top:18px;border-top:1px solid var(--bd);padding-top:16px" data-testid="internal-ticket-reply-form">
                @csrf
                <label class="form-label" for="internal-ticket-reply-body">أضف رد دعم ظاهرًا للعميل</label>
                <textarea id="internal-ticket-reply-body" name="body" class="form-input" rows="4" required data-testid="internal-ticket-reply-body" placeholder="اكتب الرد التالي الظاهر للعميل.">{{ old('body') }}</textarea>
                <div style="display:flex;justify-content:flex-end;margin-top:12px">
                    <button type="submit" class="btn btn-pr" data-testid="internal-ticket-reply-submit">إرسال الرد</button>
                </div>
            </form>
        @endif
    </section>

    <section class="card" data-testid="internal-ticket-notes-card">
        <div class="card-title">ملاحظات داخلية</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">{{ $detail['internal_notes_summary'] }}</div>

        @if($detail['internal_notes_count'] > 0)
            <div style="display:flex;flex-direction:column;gap:12px">
                @foreach($detail['internal_notes'] as $note)
                    <article style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.02)" data-testid="internal-ticket-note-entry">
                        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                            <div>
                                <div style="font-weight:700;color:var(--tx)">{{ $note['actor_label'] }}</div>
                                <div style="font-size:12px;color:var(--td)">{{ $note['actor_name'] }}</div>
                            </div>
                            <div style="font-size:12px;color:var(--td)">{{ $note['created_at_label'] }}</div>
                        </div>
                        <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $note['body'] }}</div>
                    </article>
                @endforeach
            </div>
        @else
            <div style="font-size:13px;color:var(--td)">لا توجد ملاحظات داخلية مسجلة بعد.</div>
        @endif

        @if($canManageThread)
            <form method="POST" action="{{ route('internal.tickets.notes.store', $detail['route_key']) }}" style="margin-top:18px;border-top:1px solid var(--bd);padding-top:16px" data-testid="internal-ticket-note-form">
                @csrf
                <label class="form-label" for="internal-ticket-note-body">أضف ملاحظة داخلية</label>
                <textarea id="internal-ticket-note-body" name="body" class="form-input" rows="4" required data-testid="internal-ticket-note-body" placeholder="اكتب سياقًا داخليًا مخصصًا للدعم والعمليات.">{{ old('body') }}</textarea>
                <div style="display:flex;justify-content:flex-end;margin-top:12px">
                    <button type="submit" class="btn btn-s" data-testid="internal-ticket-note-submit">حفظ الملاحظة الداخلية</button>
                </div>
            </form>
        @endif
    </section>
</div>
@endsection
