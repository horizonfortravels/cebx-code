@extends('layouts.app')
@section('title', 'تفاصيل تكامل شركة الشحن')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.carriers.index') }}" style="color:inherit;text-decoration:none">تكاملات شركات الشحن</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $detail['name'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل تكامل شركة الشحن</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            {{ $detail['name'] }} • {{ $detail['provider_key'] }} • {{ $detail['enabled_label'] }}
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.integrations.show', 'carrier~' . $detail['provider_key']) }}" class="btn btn-s">فتح تفاصيل التكامل الأوسع</a>
        <a href="{{ route('internal.carriers.index') }}" class="btn btn-pr">العودة إلى شركات الشحن</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CAR" label="شركة الشحن" :value="$detail['provider_name']" />
    <x-stat-card icon="ON" label="الحالة" :value="$detail['enabled_label']" />
    <x-stat-card icon="MD" label="وضع الاتصال" :value="$detail['mode_label']" />
    <x-stat-card icon="HLT" label="الصحة" :value="$detail['health_label']" />
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-carrier-summary-card">
        <div class="card-title">ملخص شركة الشحن</div>
        <dl style="display:grid;grid-template-columns:minmax(150px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">اسم شركة الشحن</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['name'] }}</dd>
            <dt style="color:var(--tm)">مفتاح المزوّد</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['provider_key'] }}</dd>
            <dt style="color:var(--tm)">حالة التفعيل</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['enabled_label'] }}</dd>
            <dt style="color:var(--tm)">الإعداد</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['configuration_label'] }}</dd>
            <dt style="color:var(--tm)">وضع الاتصال</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['mode_summary'] }}</dd>
            <dt style="color:var(--tm)">حساب الشاحن</dt>
            <dd style="margin:0;color:var(--tx)">{{ $detail['shipper_account_summary']['summary'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-carrier-health-card">
        <div class="card-title">حالة الاتصال والاختبار</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="font-weight:700;color:var(--tx)">{{ $detail['connection_test_summary']['headline'] }}</div>
            <div style="font-size:13px;color:var(--td)">{{ $detail['connection_test_summary']['detail'] }}</div>
            <div class="grid-3">
                <div>
                    <div style="font-size:12px;color:var(--tm)">آخر فحص</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['checked_at'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">زمن الاستجابة</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['response_time'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">الطلبات</div>
                    <div style="color:var(--tx)">{{ $detail['health_summary']['request_summary'] }}</div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    @if($canManageCarriers)
        <section class="card" data-testid="internal-carrier-actions-card">
            <div class="card-title">إجراءات تشغيلية</div>
            <div style="display:flex;flex-direction:column;gap:16px">
                <form method="post" action="{{ route('internal.carriers.toggle', $detail['provider_key']) }}" data-testid="internal-carrier-toggle-form">
                    @csrf
                    <input type="hidden" name="is_enabled" value="{{ $detail['is_enabled'] ? 0 : 1 }}">
                    <label style="display:flex;flex-direction:column;gap:8px">
                        <span style="font-size:12px;color:var(--tm)">سبب المشغل</span>
                        <textarea name="reason" rows="3" style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)" placeholder="اشرح سبب {{ $detail['is_enabled'] ? 'تعطيل' : 'تفعيل' }} شركة الشحن من البوابة الداخلية." required>{{ old('reason') }}</textarea>
                    </label>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px">
                        <div style="font-size:13px;color:var(--td)">
                            {{ $detail['is_enabled'] ? 'عطّل بوابة شركة الشحن هذه للاستخدام التشغيلي الداخلي.' : 'فعّل بوابة شركة الشحن هذه للاستخدام التشغيلي الداخلي.' }}
                        </div>
                        <button type="submit" class="btn btn-pr" data-testid="internal-carrier-toggle-button">
                            {{ $detail['is_enabled'] ? 'تعطيل شركة الشحن' : 'تفعيل شركة الشحن' }}
                        </button>
                    </div>
                </form>

                <form method="post" action="{{ route('internal.carriers.test', $detail['provider_key']) }}" data-testid="internal-carrier-test-form">
                    @csrf
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">تشغيل اختبار اتصال آمن</div>
                            <div style="font-size:13px;color:var(--td)">يتحقق هذا من عقد إعداد شركة الشحن ويسجل نتيجة فحص صحة جديدة دون لمس بيانات الشحنات.</div>
                        </div>
                        <button type="submit" class="btn btn-s" data-testid="internal-carrier-test-button">تشغيل اختبار الاتصال</button>
                    </div>
                </form>

                <form method="post" action="{{ route('internal.carriers.credentials.update', $detail['provider_key']) }}" data-testid="internal-carrier-credentials-update-form">
                    @csrf
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">تحديث بيانات اعتماد شركة الشحن المخزنة</div>
                            <div style="font-size:13px;color:var(--td)">اترك أي حقل فارغًا للاحتفاظ بالقيمة المخزنة الحالية. تبقى بيانات الاعتماد المحفوظة مشفرة ومقنّعة داخل البوابة.</div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                            @foreach($credentialFields as $field)
                                <label style="display:flex;flex-direction:column;gap:8px">
                                    <span style="font-size:12px;color:var(--tm)">{{ $field['label'] }}</span>
                                    <input
                                        type="{{ $field['input_type'] }}"
                                        name="{{ $field['name'] }}"
                                        value=""
                                        autocomplete="off"
                                        style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)"
                                    >
                                    <span style="font-size:12px;color:var(--td)">الحالي: {{ $field['current_value'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <label style="display:flex;flex-direction:column;gap:8px">
                            <span style="font-size:12px;color:var(--tm)">سبب المشغل</span>
                            <textarea name="reason" rows="3" style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)" placeholder="اشرح سبب تحديث بيانات اعتماد شركة الشحن المخزنة من البوابة الداخلية." required></textarea>
                        </label>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                            <div style="font-size:13px;color:var(--td)">سيُشغّل اختبار اتصال آمن جديد مباشرة بعد الحفظ.</div>
                            <button type="submit" class="btn btn-pr" data-testid="internal-carrier-credentials-save-button">حفظ بيانات الاعتماد</button>
                        </div>
                    </div>
                </form>

                @if($supportsRotation)
                    <form method="post" action="{{ route('internal.carriers.credentials.rotate', $detail['provider_key']) }}" data-testid="internal-carrier-credentials-rotate-form">
                        @csrf
                        <div style="display:flex;flex-direction:column;gap:12px">
                            <div>
                                <div style="font-weight:700;color:var(--tx)">تدوير بيانات اعتماد API النشطة</div>
                                <div style="font-size:13px;color:var(--td)">يستبدل هذا زوج بيانات اعتماد API المخزن حاليًا لهذه الشركة ويعيد اختبار الاتصال فورًا. ولا يكشف أو يصدّر السر السابق.</div>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                                @foreach($rotationFields as $field)
                                    <label style="display:flex;flex-direction:column;gap:8px">
                                        <span style="font-size:12px;color:var(--tm)">{{ $field['label'] }}</span>
                                        <input
                                            type="{{ $field['input_type'] }}"
                                            name="{{ $field['name'] }}"
                                            value=""
                                            autocomplete="off"
                                            style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)"
                                        >
                                        <span style="font-size:12px;color:var(--td)">الحالي: {{ $field['current_value'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <label style="display:flex;flex-direction:column;gap:8px">
                                <span style="font-size:12px;color:var(--tm)">سبب المشغل</span>
                                <textarea name="reason" rows="3" style="width:100%;padding:12px;border:1px solid var(--bd);border-radius:12px;background:var(--bg);color:var(--tx)" placeholder="اشرح سبب تدوير مفتاح API أو السر النشط من البوابة الداخلية." required></textarea>
                            </label>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                                <div style="font-size:13px;color:var(--td)">لا تُستبدل أثناء التدوير إلا حقول بيانات اعتماد API الجديدة المقدمة.</div>
                                <button type="submit" class="btn btn-s" data-testid="internal-carrier-credentials-rotate-button">تدوير بيانات الاعتماد</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <section class="card" data-testid="internal-carrier-activity-card">
        <div class="card-title">نشاط شركة الشحن الأخير</div>
        <div style="font-weight:700;color:var(--tx);margin-bottom:8px">{{ $detail['activity_summary']['headline'] }}</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:14px">{{ $detail['activity_summary']['detail'] }}</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($detail['activity_summary']['items'] as $item)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-size:12px;color:var(--tm)">{{ $item['label'] }}</div>
                    <div style="color:var(--tx)">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card" data-testid="internal-carrier-error-card">
        <div class="card-title">ملخص آخر خطأ</div>
        <div style="font-weight:700;color:var(--tx);margin-bottom:8px">{{ $detail['last_error_summary']['headline'] }}</div>
        <div style="font-size:13px;color:var(--td)">{{ $detail['last_error_summary']['detail'] }}</div>
    </section>
</div>

@if($canViewCredentials)
    <section class="card" data-testid="internal-carrier-credentials-card">
        <div class="card-title">ملخص بيانات الاعتماد المقنّعة</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:14px">{{ $detail['masked_api_summary'] }} لا تُعرض الأسرار النصية الصريحة مجددًا بعد الحفظ.</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            @forelse($credentialFields as $field)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-size:12px;color:var(--tm)">{{ $field['label'] }}</div>
                    <div style="color:var(--tx)">{{ $field['current_value'] }}</div>
                </div>
            @empty
                <div class="empty-state">لا توجد حقول بيانات اعتماد مهيأة ظاهرة لهذه الشركة.</div>
            @endforelse
        </div>
    </section>
@endif
@endsection
