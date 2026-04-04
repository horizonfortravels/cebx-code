@extends('layouts.app')
@section('title', 'سجل التدقيق')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">📜 سجل التدقيق</h1>
    <a href="{{ route('audit.export') }}" class="btn btn-s">📥 تصدير CSV</a>
</div>

{{-- Filters --}}
<x-card>
    <form method="GET" action="{{ route('audit.index') }}" class="filter-grid-fluid">
        <div class="filter-field">
            <label class="form-label">المستخدم</label>
            <select name="user_id" class="form-input">
                <option value="">الكل</option>
                @foreach($users ?? [] as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label class="form-label">الحدث</label>
            <select name="event" class="form-input">
                <option value="">الكل</option>
                @foreach(['create' => 'إنشاء', 'update' => 'تعديل', 'delete' => 'حذف', 'login' => 'تسجيل دخول', 'logout' => 'تسجيل خروج', 'export' => 'تصدير'] as $k => $v)
                    <option value="{{ $k }}" {{ request('event') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label class="form-label">من تاريخ</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-input">
        </div>
        <div class="filter-field">
            <label class="form-label">إلى تاريخ</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-input">
        </div>
        <div class="filter-actions filter-actions-wide">
            <button type="submit" class="btn btn-pr">بحث</button>
        </div>
    </form>
</x-card>

{{-- Audit Log --}}
<x-card title="📋 السجلات">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>الوقت</th><th>المستخدم</th><th>الحدث</th><th>الموديل</th><th>التفاصيل</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse($logs ?? [] as $log)
                    @php
                        $eventLabels = ['create' => ['إنشاء', '🟢'], 'update' => ['تعديل', '🟡'], 'delete' => ['حذف', '🔴'], 'login' => ['دخول', '🔵'], 'logout' => ['خروج', '⚪'], 'export' => ['تصدير', '📥']];
                        $el = $eventLabels[$log->event] ?? [$log->event, '⚪'];
                    @endphp
                    <tr>
                        <td style="font-size:12px;white-space:nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->user->name ?? '—' }}</td>
                        <td><span class="badge badge-in">{{ $el[1] }} {{ $el[0] }}</span></td>
                        <td class="td-mono" style="font-size:12px">{{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id ?? '' }}</td>
                        <td style="max-width:200px;font-size:12px;color:var(--td);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {{ Str::limit(json_encode($log->new_values ?? [], JSON_UNESCAPED_UNICODE), 80) }}
                        </td>
                        <td class="td-mono" style="font-size:11px">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">لا توجد سجلات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($logs ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $logs->links() }}</div>
    @endif
</x-card>
@endsection
