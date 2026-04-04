@extends('layouts.app')
@section('title', 'إدارة المخاطر')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">⚠️ إدارة المخاطر</h1>
    <button class="btn btn-pr" data-modal-open="add-rule">+ قاعدة جديدة</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="⚠️" label="تنبيهات نشطة" :value="$activeAlerts ?? 0" />
    <x-stat-card icon="🔴" label="مخاطر عالية" :value="$highRiskCount ?? 0" />
    <x-stat-card icon="🟡" label="مخاطر متوسطة" :value="$mediumRiskCount ?? 0" />
    <x-stat-card icon="🟢" label="مخاطر منخفضة" :value="$lowRiskCount ?? 0" />
</div>

{{-- Risk Score Distribution --}}
<div class="grid-2-1" style="margin-bottom:24px">
    <x-card title="📊 تنبيهات المخاطر الأخيرة">
        <div style="display:flex;flex-direction:column">
            @forelse($alerts ?? [] as $alert)
                @php
                    $levelMap = ['high' => ['🔴 عالي', '#EF4444', 'rgba(239,68,68,0.1)'], 'medium' => ['🟡 متوسط', '#F59E0B', 'rgba(245,158,11,0.1)'], 'low' => ['🟢 منخفض', '#10B981', 'rgba(16,185,129,0.1)']];
                    $lv = $levelMap[$alert->level] ?? ['⚪ غير محدد', 'var(--td)', 'var(--sf)'];
                @endphp
                <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid var(--bd)">
                    <div style="width:36px;height:36px;border-radius:8px;background:{{ $lv[2] }};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">⚠️</div>
                    <div style="flex:1">
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <span style="font-weight:600;font-size:13px;color:var(--tx)">{{ $alert->title }}</span>
                            <span class="badge" style="background:{{ $lv[2] }};color:{{ $lv[1] }};font-size:11px">{{ $lv[0] }}</span>
                        </div>
                        <p style="font-size:12px;color:var(--td);margin:4px 0 0">{{ $alert->description }}</p>
                        <span style="font-size:11px;color:var(--tm)">{{ $alert->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <div class="empty-state">لا توجد تنبيهات</div>
            @endforelse
        </div>
    </x-card>

    <x-card title="📈 توزيع المخاطر">
        @foreach([
            ['label' => 'عالية', 'pct' => $highPct ?? 10, 'color' => '#EF4444'],
            ['label' => 'متوسطة', 'pct' => $mediumPct ?? 25, 'color' => '#F59E0B'],
            ['label' => 'منخفضة', 'pct' => $lowPct ?? 65, 'color' => '#10B981'],
        ] as $bar)
            <div style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                    <span style="color:var(--tx)">{{ $bar['label'] }}</span>
                    <span style="color:var(--td)">{{ $bar['pct'] }}%</span>
                </div>
                <div style="height:8px;background:var(--bd);border-radius:4px">
                    <div style="height:100%;width:{{ $bar['pct'] }}%;background:{{ $bar['color'] }};border-radius:4px;transition:width 1s ease"></div>
                </div>
            </div>
        @endforeach
    </x-card>
</div>

{{-- Risk Rules --}}
<x-card title="📋 قواعد المخاطر">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>القاعدة</th><th>الشرط</th><th>مستوى المخاطرة</th><th>الإجراء</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($rules ?? [] as $rule)
                    <tr>
                        <td style="font-weight:600">{{ $rule->name }}</td>
                        <td style="font-size:12px;color:var(--td)">{{ $rule->condition_description }}</td>
                        <td>
                            @php
                                $rlMap = ['high' => 'badge-dg', 'medium' => 'badge-wn', 'low' => 'badge-ac'];
                                $rlLabel = ['high' => 'عالي', 'medium' => 'متوسط', 'low' => 'منخفض'];
                            @endphp
                            <span class="badge {{ $rlMap[$rule->risk_level] ?? 'badge-td' }}">{{ $rlLabel[$rule->risk_level] ?? '—' }}</span>
                        </td>
                        <td style="font-size:12px">{{ $rule->action_description ?? 'إيقاف + إشعار' }}</td>
                        <td><span style="color:{{ $rule->is_active ? 'var(--ac)' : 'var(--dg)' }}">● {{ $rule->is_active ? 'مفعّل' : 'معطّل' }}</span></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-s" style="font-size:12px">تعديل</button>
                                <button class="btn btn-s" style="font-size:12px;color:var(--dg)">حذف</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">لا توجد قواعد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

<x-modal id="add-rule" title="إضافة قاعدة مخاطر" wide>
    <form method="POST" action="{{ route('risk.index') }}">
        @csrf
        <div class="form-grid-2">
            <div style="grid-column:1 / -1"><label class="form-label">اسم القاعدة</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">مستوى المخاطرة</label><select name="risk_level" class="form-input"><option value="low">منخفض</option><option value="medium">متوسط</option><option value="high">عالي</option></select></div>
            <div><label class="form-label">الإجراء</label><select name="action" class="form-input"><option>إيقاف الشحنة</option><option>إشعار المدير</option><option>مراجعة يدوية</option><option>إيقاف + إشعار</option></select></div>
            <div style="grid-column:1 / -1"><label class="form-label">وصف الشرط</label><textarea name="condition_description" class="form-input" rows="2" placeholder="مثال: إذا كانت قيمة الشحنة أكثر من 50,000 ريال"></textarea></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">إضافة</button>
        </div>
    </form>
</x-modal>
@endsection
