@extends('layouts.app')
@section('title', 'التذاكر الداخلية')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <span>التذاكر</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">التذاكر الداخلية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            رؤية تشغيلية لتذاكر دعم العملاء والحسابات المرتبطة والشحنات المرتبطة والمسند إليهم وسجل المحادثة الآمن مع العميل.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($canCreateTickets)
            <a href="{{ route('internal.tickets.create') }}" class="btn btn-s" data-testid="internal-tickets-create-link">إنشاء تذكرة</a>
        @endif
        <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">تحديث</a>
        <a href="{{ route('internal.home') }}" class="btn btn-pr">العودة إلى الرئيسية الداخلية</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="TKT" label="إجمالي التذاكر" :value="number_format($stats['total'])" />
    <x-stat-card icon="OPN" label="قائمة التذاكر المفتوحة" :value="number_format($stats['open'])" />
    <x-stat-card icon="URG" label="العاجلة" :value="number_format($stats['urgent'])" />
    <x-stat-card icon="SHP" label="المرتبطة بشحنات" :value="number_format($stats['linked_shipments'])" />
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">بحث وفلاتر أساسية</div>
    <form method="GET" action="{{ route('internal.tickets.index') }}" class="filter-grid-fluid">
        <div class="filter-field-wide">
            <label for="ticket-search" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بحث</label>
            <input id="ticket-search" type="text" name="q" value="{{ $filters['q'] }}" class="input" placeholder="ابحث برقم التذكرة أو الموضوع أو مقدم الطلب أو الحساب أو الشحنة أو المسند إليه">
        </div>
        <div>
            <label for="ticket-status" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة</label>
            <select id="ticket-status" name="status" class="input" data-testid="internal-ticket-filter-status">
                <option value="">كل الحالات</option>
                @foreach($statusOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ ['open' => 'مفتوحة', 'in_progress' => 'قيد المعالجة', 'waiting_customer' => 'بانتظار العميل', 'waiting_agent' => 'بانتظار الفريق', 'resolved' => 'محلولة', 'closed' => 'مغلقة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-priority" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الأولوية</label>
            <select id="ticket-priority" name="priority" class="input" data-testid="internal-ticket-filter-priority">
                <option value="">كل الأولويات</option>
                @foreach($priorityOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['priority'] === $key)>{{ ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'مرتفعة', 'urgent' => 'عاجلة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-category" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">التصنيف</label>
            <select id="ticket-category" name="category" class="input">
                <option value="">كل التصنيفات</option>
                @foreach($categoryOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ ['shipping' => 'الشحن', 'shipment' => 'الشحن', 'billing' => 'الفوترة', 'technical' => 'تقنية', 'account' => 'الحساب', 'carrier' => 'الناقل', 'general' => 'عام'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-account-filter" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحساب المرتبط</label>
            <select id="ticket-account-filter" name="account_id" class="input" data-testid="internal-ticket-filter-account">
                <option value="">كل الحسابات</option>
                @foreach($accountFilterOptions as $account)
                    <option value="{{ $account['id'] }}" @selected($filters['account_id'] === $account['id'])>{{ $account['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-shipment-scope" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الشحنة المرتبطة</label>
            <select id="ticket-shipment-scope" name="shipment_scope" class="input" data-testid="internal-ticket-filter-shipment">
                <option value="">كل التذاكر</option>
                @foreach($shipmentScopeOptions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['shipment_scope'] === $key)>{{ ['linked' => 'مرتبطة بشحنة', 'unlinked' => 'بدون شحنة مرتبطة'][$key] ?? $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ticket-assignee-filter" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">المسند إليه</label>
            <select id="ticket-assignee-filter" name="assignee_id" class="input" data-testid="internal-ticket-filter-assignee">
                <option value="">كل المسند إليهم</option>
                <option value="{{ $assigneeFilterUnassigned }}" @selected($filters['assignee_id'] === $assigneeFilterUnassigned)>غير مسندة</option>
                @foreach($assigneeFilterOptions as $assignee)
                    <option value="{{ $assignee['id'] }}" @selected($filters['assignee_id'] === $assignee['id'])>{{ $assignee['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-pr" data-testid="internal-ticket-filter-submit">تطبيق الفلاتر</button>
            <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">إعادة الضبط</a>
        </div>
    </form>
</div>

<div class="card" data-testid="internal-tickets-table">
    <div class="card-title">التذاكر الظاهرة</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>التذكرة</th>
                <th>مقدم الطلب والحساب</th>
                <th>الحالة والأولوية</th>
                <th>الشحنة المرتبطة</th>
                <th>المسند إليه</th>
                <th>أحدث نشاط</th>
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
                            <div style="font-size:12px;color:var(--td)">لا توجد شحنة مرتبطة</div>
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
                        <div style="font-size:12px;color:var(--td)">{{ $row['recent_activity_at'] }} - {{ number_format($row['replies_count']) }} ردود</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">لا توجد تذاكر مطابقة للفلاتر الحالية.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $tickets->links() }}</div>
</div>
@endsection
