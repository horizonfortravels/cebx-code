@extends('layouts.app')
@section('title', $staffUser->name . ' - فريق المنصة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.staff.index') }}" style="color:inherit;text-decoration:none">فريق المنصة</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $staffUser->name }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">{{ $staffUser->name }}</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            {{ $staffUser->email }}<br>
            {{ $staffSummary['description'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($canUpdateStaff)
            <a href="{{ route('internal.staff.edit', $staffUser) }}" class="btn btn-pr" data-testid="internal-staff-edit-cta">تحرير الموظف</a>
        @endif
        <a href="{{ route('internal.staff.index') }}" class="btn btn-s">العودة إلى الدليل</a>
    </div>
</div>

@if($canManageLifecycle || $canTriggerPasswordReset)
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-bottom:24px">
        @if($canManageLifecycle)
            <section class="card" data-testid="internal-staff-lifecycle-card">
                <div class="card-title">إدارة حالة الموظف</div>
                @if($lifecycleProtectionMessage)
                    <p style="margin:0;color:var(--wa);font-weight:700" data-testid="internal-staff-lifecycle-guardrail">{{ $lifecycleProtectionMessage }}</p>
                @elseif($availableLifecycleActions === [])
                    <p style="margin:0;color:var(--td)">لا توجد إجراءات حالة متاحة لهذا الموظف حاليًا.</p>
                @else
                    <div style="display:flex;flex-wrap:wrap;gap:12px">
                        @foreach($availableLifecycleActions as $action)
                            <form method="POST" action="{{ route('internal.staff.' . $action['action'], $staffUser) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn {{ $action['tone'] === 'danger' ? 'btn-d' : ($action['tone'] === 'warning' ? 'btn-s' : 'btn-pr') }}"
                                    data-testid="internal-staff-{{ $action['action'] }}-button"
                                >
                                    {{ $action['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if($canTriggerPasswordReset)
            <section class="card" data-testid="internal-staff-support-card">
                <div class="card-title">إجراءات الدعم</div>
                <p style="margin:0 0 12px;color:var(--td);font-size:14px">
                    يتم إرسال رابط إعادة تعيين كلمة المرور عبر المسار المعتمد دون إظهار أي رموز أو أسرار من هذه الواجهة.
                </p>
                <form method="POST" action="{{ route('internal.staff.password-reset', $staffUser) }}" data-testid="internal-staff-password-reset-form">
                    @csrf
                    <button type="submit" class="btn btn-pr" data-testid="internal-staff-password-reset-button">إرسال رابط إعادة تعيين كلمة المرور</button>
                </form>
            </section>
        @endif
    </div>
@endif

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="STS" label="الحالة" :value="$statusLabel" />
    <x-stat-card icon="ROL" label="الدور المعتمد" :value="$staffSummary['label']" />
    <x-stat-card icon="PER" label="إجمالي الصلاحيات" :value="number_format($permissionSummary['count'])" />
    <x-stat-card icon="LOG" label="آخر دخول" :value="$lastLoginAt ?? 'غير متوفر'" />
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
    <section class="card" data-testid="staff-identity-card">
        <div class="card-title">ملخص الموظف</div>
        <dl style="display:grid;grid-template-columns:minmax(110px,140px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">الاسم</dt>
            <dd style="margin:0;color:var(--tx)">{{ $staffUser->name }}</dd>

            <dt style="color:var(--tm)">البريد</dt>
            <dd style="margin:0;color:var(--tx)">{{ $staffUser->email }}</dd>

            <dt style="color:var(--tm)">الحالة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $statusLabel }}</dd>

            <dt style="color:var(--tm)">تأكيد البريد</dt>
            <dd style="margin:0;color:var(--tx)">{{ $emailVerifiedAt ?? 'غير مؤكد بعد' }}</dd>

            <dt style="color:var(--tm)">آخر دخول</dt>
            <dd style="margin:0;color:var(--tx)">{{ $lastLoginAt ?? 'لا يوجد دخول مسجل بعد' }}</dd>

            <dt style="color:var(--tm)">اللغة / المنطقة</dt>
            <dd style="margin:0;color:var(--tx)">{{ ($staffUser->locale ?? '—') . ' / ' . ($staffUser->timezone ?? '—') }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="staff-role-card">
        <div class="card-title">الدور الداخلي</div>
        <p style="margin:0 0 12px;color:var(--tx);font-weight:700">{{ $staffSummary['label'] }}</p>
        @if($staffSummary['primaryRole'])
            <div data-testid="staff-canonical-role-key" style="margin:0 0 12px;font-size:12px;color:var(--tm)">
                المعرف المعتمد:
                <code style="padding:2px 8px;border-radius:999px;background:#eef2ff;color:#312e81">{{ $staffSummary['primaryRole'] }}</code>
            </div>
        @endif
        @if($staffSummary['canonicalRoleLabels'] !== [])
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                @foreach($staffSummary['canonicalRoleLabels'] as $roleLabel)
                    <span class="badge">{{ $roleLabel }}</span>
                @endforeach
            </div>
        @endif
        <p style="margin:0;color:var(--td);font-size:14px">{{ $staffSummary['description'] }}</p>
        @if($staffSummary['landingRoute'])
            <div style="margin-top:14px;font-size:12px;color:var(--tm)">
                نقطة الهبوط المتوقعة:
                <strong style="color:var(--tx)">{{ $staffSummary['landingRoute'] }}</strong>
            </div>
        @elseif($staffSummary['landingRouteNote'])
            <div data-testid="staff-role-alignment-note" style="margin-top:14px;font-size:12px;color:var(--wa);font-weight:700">
                {{ $staffSummary['landingRouteNote'] }}
            </div>
        @endif
    </section>

    <section class="card" data-testid="staff-permissions-card">
        <div class="card-title">ملخص الصلاحيات</div>
        @if($permissionSummary['isVisible'])
            @if($permissionSummary['groups'] !== [])
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @foreach($permissionSummary['groups'] as $group => $count)
                        <span class="badge">{{ $group }} ({{ $count }})</span>
                    @endforeach
                </div>
            @else
                <p style="margin:0;color:var(--td)">لا توجد صلاحيات نشطة مرتبطة بهذا الحساب حاليًا.</p>
            @endif
            <p data-testid="staff-permissions-note" style="margin:12px 0 0;color:var(--tm);font-size:12px">{{ $permissionSummary['note'] }}</p>
        @else
            <p data-testid="staff-permissions-note" style="margin:0;color:var(--wa);font-weight:700">{{ $permissionSummary['note'] }}</p>
        @endif
    </section>

    <section class="card" data-testid="staff-activity-card">
        <div class="card-title">آخر تسجيل نشاط</div>
        <dl style="display:grid;grid-template-columns:minmax(110px,140px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">آخر دخول</dt>
            <dd style="margin:0;color:var(--tx)">{{ $lastLoginAt ?? 'لا يوجد دخول مسجل بعد' }}</dd>

            <dt style="color:var(--tm)">آخر نشاط مدون</dt>
            <dd style="margin:0;color:var(--tx)">{{ $latestActivity['action'] ?? 'لا يوجد نشاط مسجل بعد' }}</dd>

            <dt style="color:var(--tm)">توقيت النشاط</dt>
            <dd style="margin:0;color:var(--tx)">{{ $latestActivity['at'] ?? '—' }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="staff-governance-card">
        <div class="card-title">الحوكمة والمواءمة</div>
        @if($staffSummary['hasDeprecatedAssignments'])
            <p style="margin:0;color:var(--wa);font-weight:700">تم إخفاء أي تعيينات قديمة من الواجهة النشطة لهذا الحساب.</p>
            <p style="margin:10px 0 0;color:var(--td)">يظل العرض محصورًا في الدور المعتمد أو في تنبيه مواءمة عام حتى لا تظهر مسميات قديمة مثل الأدوار المرحلة أو الملغاة.</p>
        @else
            <p style="margin:0;color:var(--td)">هذا الحساب متوافق مع الدور الداخلي المعتمد ولا يحتوي على مسميات قديمة ظاهرة.</p>
        @endif
    </section>
</div>
@endsection
