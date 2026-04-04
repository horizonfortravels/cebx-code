@extends('layouts.app')
@section('title', 'تفاصيل مفتاح التكامل الداخلي')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.api-keys.index') }}" style="color:inherit;text-decoration:none">مفاتيح التكامل</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل مفتاح التكامل الداخلي</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            {{ $detail['name'] }} • {{ $detail['masked_prefix'] }} • {{ $detail['state_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.api-keys.index') }}" class="btn btn-pr">العودة إلى مفاتيح التكامل</a>
    </div>
</div>

@if($plaintextKey)
    <section class="card" data-testid="internal-api-key-plaintext-card" style="margin-bottom:24px;border-color:rgba(15,118,110,.25);background:rgba(15,118,110,.05)">
        <div class="card-title">السر النصي لمرة واحدة</div>
        <p style="color:var(--td);font-size:13px;margin-top:0">
            احفظ هذا المفتاح في مكان آمن الآن. لن تعرضه البوابة الداخلية مرة أخرى بعد اكتمال هذا الطلب.
        </p>
        <code data-testid="internal-api-key-plaintext-value" style="display:block;padding:14px;border:1px dashed var(--bd);border-radius:12px;background:#fff;color:var(--tx);font-size:14px;direction:ltr;text-align:left">{{ $plaintextKey }}</code>
    </section>
@endif

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="KEY" label="البادئة المقنّعة" :value="$detail['masked_prefix']" />
    <x-stat-card icon="STA" label="الحالة" :value="$detail['state_label']" />
    <x-stat-card icon="SCP" label="عدد النطاقات" :value="number_format(count($detail['scope_keys']))" />
    <x-stat-card icon="IP" label="عناوين بروتوكول الإنترنت المسموح بها" :value="number_format($detail['allowed_ip_count'])" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-api-key-summary-card">
        <div class="card-title">ملخص المفتاح</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">الاسم</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">البادئة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['masked_prefix'] }}</dd>
            <dt style="color:var(--tm)">الحالة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['state_label'] }} • {{ $detail['status_detail'] }}</dd>
            <dt style="color:var(--tm)">أُنشئ</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['created_at'] }}</dd>
            <dt style="color:var(--tm)">تم الإنشاء بواسطة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['creator_summary']['name'] }} • {{ $detail['creator_summary']['email'] }}</dd>
            <dt style="color:var(--tm)">آخر استخدام</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['last_used_at'] }}</dd>
            <dt style="color:var(--tm)">ينتهي</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['expires_at'] }}</dd>
            <dt style="color:var(--tm)">تاريخ الإلغاء</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['revoked_at'] }}</dd>
            @if($detail['account_summary'])
                <dt style="color:var(--tm)">الحساب المرتبط</dt>
                <dd style="margin:0;color:var(--tx)">
                    {{ $detail['account_summary']['name'] }} • {{ $detail['account_summary']['type_label'] }}
                    @if($canViewAccount)
                        <div style="margin-top:8px">
                            <a href="{{ route('internal.accounts.show', $detail['account_summary']['account']) }}" class="btn btn-s" data-testid="internal-api-key-account-link">فتح تفاصيل الحساب</a>
                        </div>
                    @endif
                </dd>
            @endif
        </dl>
    </section>

    <section class="card" data-testid="internal-api-key-security-card">
        <div class="card-title">ملخص الأمان</div>
        <dl style="display:grid;grid-template-columns:minmax(130px,180px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">قائمة السماح</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['allowed_ip_summary'] }}</dd>
            <dt style="color:var(--tm)">نموذج السر</dt>
            <dd style="margin:0;color:var(--tx)">يظهر النص الكامل مرة واحدة فقط عند الإنشاء أو التدوير، ثم يُقنّع بشكل دائم.</dd>
            <dt style="color:var(--tm)">السر المخزن</dt>
            <dd style="margin:0;color:var(--tx)">يتم تخزين سجل مفتاح مجزّأ فقط. لا يُعرض النص الكامل ولا تجزئة المفتاح هنا أبدًا.</dd>
        </dl>
    </section>
</div>

<section class="card" data-testid="internal-api-key-scopes-card" style="margin-bottom:24px">
    <div class="card-title">النطاقات</div>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
        @forelse($detail['scope_items'] as $scope)
            <span style="padding:10px 12px;border:1px solid var(--bd);border-radius:999px;background:rgba(15,23,42,.03);color:var(--tx)">
                {{ $scope['label'] }}
            </span>
        @empty
            <div class="empty-state">لا توجد قائمة سماح نطاقات مسجلة لهذا المفتاح، لذلك يتصرف كمفتاح قديم غير مقيّد.</div>
        @endforelse
    </div>
</section>

@if($canManageKeys && $detail['is_rotatable'])
    <div class="grid-2">
        <section class="card" data-testid="internal-api-key-rotate-form">
            <div class="card-title">تدوير المفتاح بأمان</div>
            <form method="POST" action="{{ route('internal.api-keys.rotate', $detail['id']) }}" style="display:flex;flex-direction:column;gap:10px">
                @csrf
                <label for="api-key-rotate-reason" style="font-size:12px;color:var(--tm)">سبب التدوير الداخلي</label>
                <textarea id="api-key-rotate-reason" name="reason" rows="3" class="input" maxlength="500" placeholder="اشرح سبب الحاجة إلى سر جديد." required>{{ old('reason') }}</textarea>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-pr" data-testid="internal-api-key-rotate-button">تدوير المفتاح</button>
                </div>
            </form>
        </section>

        <section class="card" data-testid="internal-api-key-revoke-form">
            <div class="card-title">إلغاء المفتاح بأمان</div>
            <form method="POST" action="{{ route('internal.api-keys.revoke', $detail['id']) }}" style="display:flex;flex-direction:column;gap:10px">
                @csrf
                <label for="api-key-revoke-reason" style="font-size:12px;color:var(--tm)">سبب الإلغاء الداخلي</label>
                <textarea id="api-key-revoke-reason" name="reason" rows="3" class="input" maxlength="500" placeholder="اشرح سبب وجوب تعطيل هذا المفتاح." required>{{ old('reason') }}</textarea>
                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-danger" data-testid="internal-api-key-revoke-button">إلغاء المفتاح</button>
                </div>
            </form>
        </section>
    </div>
@endif
@endsection
