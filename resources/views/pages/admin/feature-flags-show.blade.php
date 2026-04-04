@extends('layouts.app')
@section('title', 'تفاصيل علم الميزة الداخلي')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.feature-flags.index') }}" style="color:inherit;text-decoration:none">أعلام الميزات</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل علم الميزة الداخلي</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            {{ $detail['name'] }} • {{ $detail['key'] }} • {{ $detail['state_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.feature-flags.index') }}" class="btn btn-pr">العودة إلى أعلام الميزات</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="STA" label="الحالة الحالية" :value="$detail['state_label']" />
    <x-stat-card icon="RLO" label="الإطلاق" :value="$detail['rollout_label']" />
    <x-stat-card icon="ACC" label="الحسابات المستهدفة" :value="number_format($detail['target_account_count'])" />
    <x-stat-card icon="PLN" label="الخطط المستهدفة" :value="number_format($detail['target_plan_count'])" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-feature-flag-summary-card">
        <div class="card-title">ملخص العلم</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">الاسم</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">المفتاح</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['key'] }}</dd>
            <dt style="color:var(--tm)">الوصف</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['description'] }}</dd>
            <dt style="color:var(--tm)">الحالة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['state_label'] }}</dd>
            <dt style="color:var(--tm)">الإطلاق</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['rollout_label'] }}</dd>
            <dt style="color:var(--tm)">أنشئ بواسطة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['created_by'] }}</dd>
            <dt style="color:var(--tm)">آخر تحديث</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['updated_at'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-feature-flag-runtime-card">
        <div class="card-title">مصدر التشغيل والاستهداف</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">مصدر التشغيل</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['source_label'] }}</dd>
            <dt style="color:var(--tm)">القيمة الافتراضية في الإعدادات</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['config_default_label'] }}</dd>
            <dt style="color:var(--tm)">الاستهداف</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['targeting_summary'] }}</dd>
            <dt style="color:var(--tm)">الحسابات</dt>
            <dd style="margin:0;color:var(--tx)">{{ number_format($detail['target_account_count']) }} حسابات مستهدفة</dd>
            <dt style="color:var(--tm)">الخطط</dt>
            <dd style="margin:0;color:var(--tx)">{{ number_format($detail['target_plan_count']) }} خطط مستهدفة</dd>
            <dt style="color:var(--tm)">ملاحظة تشغيلية</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['runtime_note'] }}</dd>
        </dl>
    </section>
</div>

<section class="card" data-testid="internal-feature-flag-audit-card" style="margin-bottom:24px">
    <div class="card-title">سجل التدقيق الداخلي</div>
    <div style="display:grid;gap:12px">
        @forelse($detail['audit_items'] as $item)
            <div style="padding:14px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.02)">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <strong style="color:var(--tx)">{{ $item['headline'] }}</strong>
                    <span style="font-size:12px;color:var(--td)">{{ $item['created_at'] }}</span>
                </div>
                <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $item['performed_by'] }}</div>
                <div style="font-size:13px;color:var(--tx);margin-top:8px">{{ $item['detail'] !== '' ? $item['detail'] : 'تم تسجيل تغيير الحالة.' }}</div>
            </div>
        @empty
            <div class="empty-state">لا يوجد تدقيق على التبديل الداخلي مسجل لهذا العلم بعد.</div>
        @endforelse
    </div>
</section>

@if($canManageFlags)
    <section class="card" data-testid="internal-feature-flag-toggle-form">
        <div class="card-title">{{ $detail['state_key'] === 'enabled' ? 'تعطيل العلم بأمان' : 'تفعيل العلم بأمان' }}</div>
        <p style="color:var(--td);font-size:13px;margin-top:0">
            يغيّر هذا الإجراء سجل علم الميزة المعتمد على قاعدة البيانات فقط، ويسجل إدخال تدقيق داخلي يتضمن سبب المشغل.
        </p>
        <form method="POST" action="{{ route('internal.feature-flags.toggle', $detail['id']) }}" style="display:flex;flex-direction:column;gap:10px">
            @csrf
            <input type="hidden" name="is_enabled" value="{{ $detail['state_key'] === 'enabled' ? 0 : 1 }}">
            <label for="feature-flag-toggle-reason" style="font-size:12px;color:var(--tm)">السبب الداخلي</label>
            <textarea id="feature-flag-toggle-reason" name="reason" rows="3" class="input" maxlength="500" placeholder="اشرح سبب وجوب تغيير حالة علم الميزة الداخلي." required>{{ old('reason') }}</textarea>
            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn {{ $detail['state_key'] === 'enabled' ? 'btn-danger' : 'btn-pr' }}" data-testid="internal-feature-flag-toggle-button">
                    {{ $detail['state_key'] === 'enabled' ? 'تعطيل العلم' : 'تفعيل العلم' }}
                </button>
            </div>
        </form>
    </section>
@endif
@endsection
