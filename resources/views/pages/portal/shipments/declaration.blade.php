@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | إقرار المحتوى')

@section('content')
@php
    $shipmentStatusLabels = [
        'draft' => 'مسودة',
        'validated' => 'تم التحقق من البيانات',
        'kyc_blocked' => 'موقوف بسبب التحقق أو القيود',
        'ready_for_rates' => 'جاهز لطلب العروض',
        'rated' => 'تم تجهيز العروض',
        'offer_selected' => 'تم تثبيت العرض',
        'declaration_required' => 'إقرار المحتوى مطلوب',
        'declaration_complete' => 'اكتمل إقرار المحتوى',
        'requires_action' => 'تتطلب هذه الشحنة إجراء إضافيًا',
    ];

    $shipmentStatusLabel = $shipmentStatusLabels[$shipment->status] ?? $shipment->status;
    $selectedOffer = $selectedOffer ?? null;
    $declaration = $declaration ?? null;
    $declarationStatusLabels = [
        \App\Models\ContentDeclaration::STATUS_PENDING => 'قيد الانتظار',
        \App\Models\ContentDeclaration::STATUS_COMPLETED => 'مكتمل',
        \App\Models\ContentDeclaration::STATUS_HOLD_DG => 'متوقف بسبب مواد خطرة',
        \App\Models\ContentDeclaration::STATUS_REQUIRES_ACTION => 'متوقف ويتطلب مراجعة',
    ];
    $isBlocked = $declaration?->isBlocked() ?? false;
    $isComplete = (bool) ($declaration?->waiver_accepted && $declaration?->status === \App\Models\ContentDeclaration::STATUS_COMPLETED);
    $canContinue = $workflowReady && ! $isBlocked;
    $documentsAvailable = $shipment->carrierDocuments()->where('is_available', true)->exists();
    $documentsRoute = request()->routeIs('b2b.*')
        ? route('b2b.shipments.documents.index', ['id' => $shipment->id])
        : route('b2c.shipments.documents.index', ['id' => $shipment->id]);
@endphp

@php
    $declarationFeedbackMessage = $declarationFeedback['message'] ?? 'تم تحديث حالة الإقرار.';
    $declarationFeedbackNextAction = $declarationFeedback['next_action'] ?? null;
    if (($declarationFeedback['level'] ?? 'warning') === 'success' && $shipment->status === 'declaration_complete') {
        $declarationFeedbackNextAction = 'انتقل إلى صفحة الشحنة لتفعيل فحص رصيد المحفظة ثم متابعة الإصدار لدى الناقل.';
    }
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route($portalConfig['dashboard_route']) }}" style="color:inherit;text-decoration:none">{{ $portalConfig['label'] }}</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['index_route']) }}" style="color:inherit;text-decoration:none">الشحنات</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" style="color:inherit;text-decoration:none">العروض</a>
            <span style="margin:0 6px">/</span>
            <span>إقرار المحتوى</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">إقرار المحتوى والتصريح بالمواد الخطرة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            هذه الخطوة إلزامية بعد اختيار العرض. يجب أن تصرح بوضوح عما إذا كانت الشحنة تحتوي على مواد خطرة، أو توافق على الإقرار القانوني إذا كانت الشحنة خالية منها.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى العروض</a>
        @if($documentsAvailable)
            <a href="{{ $documentsRoute }}" class="btn btn-s">عرض الوثائق</a>
        @endif
    </div>
</div>

@if($offerFeedback)
    <div style="margin-bottom:20px;padding:18px;border-radius:18px;border:1px solid rgba(4,120,87,.22);background:rgba(4,120,87,.08)">
        <div style="font-size:18px;font-weight:800;color:var(--tx)">{{ $offerFeedback['message'] ?? 'تم تحديث حالة الشحنة.' }}</div>
        @if(!empty($offerFeedback['next_action']))
            <div style="margin-top:10px;color:var(--td);font-size:14px">
                <strong style="color:var(--tx)">الخطوة التالية:</strong>
                {{ $offerFeedback['next_action'] }}
            </div>
        @endif
    </div>
@endif

@if($declarationFeedback)
    @php($isSuccessFeedback = ($declarationFeedback['level'] ?? 'warning') === 'success')
    <div style="margin-bottom:20px;padding:18px;border-radius:18px;border:1px solid {{ $isSuccessFeedback ? 'rgba(4,120,87,.22)' : 'rgba(185,28,28,.18)' }};background:{{ $isSuccessFeedback ? 'rgba(4,120,87,.08)' : 'rgba(185,28,28,.06)' }}">
        <div style="font-size:18px;font-weight:800;color:var(--tx)">{{ $declarationFeedbackMessage }}</div>
        @if(!empty($declarationFeedbackNextAction))
            <div style="margin-top:10px;color:var(--td);font-size:14px">
                <strong style="color:var(--tx)">الإجراء التالي:</strong>
                {{ $declarationFeedbackNextAction }}
            </div>
        @endif
        @if(!empty($declarationFeedback['error_code']))
            <div class="td-mono" style="margin-top:10px;font-size:12px;color:var(--tm)">{{ $declarationFeedback['error_code'] }}</div>
        @endif
    </div>
@endif

<div class="grid-2" style="margin-bottom:24px">
    <x-card title="ملخص الشحنة والعرض المختار">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">مرجع الشحنة</div>
                <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ $shipment->reference_number ?? $shipment->id }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة الشحنة</div>
                <div style="font-weight:700;color:var(--tx)">{{ $shipmentStatusLabel }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الناقل</div>
                <div style="font-weight:700;color:var(--tx)">{{ $selectedOffer?->carrier_name ?? 'لم يتم اختيار عرض بعد' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الخدمة</div>
                <div style="font-weight:700;color:var(--tx)">{{ $selectedOffer?->service_name ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">السعر المعروض</div>
                <div style="font-weight:700;color:var(--tx)">
                    @if($selectedOffer)
                        {{ number_format((float) $selectedOffer->retail_rate, 2) }} {{ $selectedOffer->currency }}
                    @else
                        —
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الوصول المتوقع</div>
                <div style="font-weight:700;color:var(--tx)">
                    {{ $selectedOffer?->estimated_delivery_at ? \Illuminate\Support\Carbon::parse($selectedOffer->estimated_delivery_at)->format('Y-m-d H:i') : 'غير متاح' }}
                </div>
            </div>
        </div>
    </x-card>

    <x-card title="حالة الإقرار الحالية">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة الإقرار</div>
                <div style="font-weight:700;color:var(--tx)">{{ $declaration ? ($declarationStatusLabels[$declaration->status] ?? $declaration->status) : 'لم يبدأ بعد' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">تم تحديد وجود مواد خطرة</div>
                <div style="font-weight:700;color:var(--tx)">
                    @if(!$declaration || !$declaration->dg_flag_declared)
                        لم يُحدد بعد
                    @else
                        {{ $declaration->contains_dangerous_goods ? 'نعم' : 'لا' }}
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الموافقة على الإقرار القانوني</div>
                <div style="font-weight:700;color:var(--tx)">{{ $declaration?->waiver_accepted ? 'تمت الموافقة' : 'لم تتم بعد' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">آخر تحديث</div>
                <div style="font-weight:700;color:var(--tx)">{{ $declaration?->updated_at ? $declaration->updated_at->format('Y-m-d H:i') : '—' }}</div>
            </div>
        </div>
    </x-card>
</div>

@if(!$workflowReady)
    <x-card title="هذه الخطوة ليست جاهزة بعد">
        <div class="empty-state" style="padding:20px;border-radius:18px;border:1px dashed var(--bd);background:rgba(15,23,42,.02)">
            <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">لا يمكن متابعة إقرار المحتوى بعد</div>
            <div style="color:var(--td);font-size:14px;max-width:760px">
                يجب اختيار عرض شحن صالح أولًا قبل فتح هذه الخطوة. بعد اختيار العرض ستنتقل هذه الشحنة إلى بوابة إقرار المحتوى تلقائيًا.
            </div>
            <div style="margin-top:14px">
                <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-pr">العودة إلى العروض</a>
            </div>
        </div>
    </x-card>
@elseif($isBlocked)
    <x-card title="الشحنة متوقفة بسبب مواد خطرة">
        <div style="padding:18px;border-radius:18px;border:1px solid rgba(185,28,28,.18);background:rgba(185,28,28,.06)">
            <div style="font-size:20px;font-weight:800;color:#991b1b;margin-bottom:8px">تم تعليق المسار العادي لهذه الشحنة</div>
            <div style="color:var(--td);font-size:14px;max-width:760px">
                لأنك صرحت بوجود مواد خطرة، لم يعد من الممكن متابعة الإصدار العادي لهذه الشحنة عبر التدفق الذاتي.
            </div>
            @if($declaration?->hold_reason)
                <div style="margin-top:10px;color:#991b1b;font-size:14px">
                    <strong>سبب الإيقاف:</strong> {{ $declaration->hold_reason }}
                </div>
            @endif
            <div style="margin-top:12px;color:var(--td);font-size:14px">
                <strong style="color:var(--tx)">الإجراء التالي:</strong>
                تواصل مع فريق الدعم أو العمليات لاستكمال المعالجة اليدوية لهذه الشحنة.
            </div>
        </div>
    </x-card>
@elseif($isComplete)
    <x-card title="اكتمل إقرار المحتوى">
        <div style="padding:18px;border-radius:18px;border:1px solid rgba(4,120,87,.22);background:rgba(4,120,87,.08)">
            <div style="font-size:20px;font-weight:800;color:#0f766e;margin-bottom:8px">تم حفظ الإقرار القانوني بنجاح</div>
            <div style="color:var(--td);font-size:14px;max-width:760px">
                تم التصريح بأن الشحنة لا تحتوي على مواد خطرة، وتم حفظ موافقتك على الإقرار القانوني كسجل تدقيقي دائم مرتبط بالشحنة.
            </div>
            <div style="margin-top:12px;color:var(--td);font-size:14px">
                <strong style="color:var(--tx)">الخطوة التالية:</strong>
                الشحنة أصبحت جاهزة للمرحلة التالية من التدفق عند تفعيل مرحلة الدفع لاحقًا.
            </div>
            @if($declaration?->waiverVersion)
                <div class="td-mono" style="margin-top:10px;font-size:12px;color:var(--tm)">
                    نسخة الإقرار: {{ $declaration->waiverVersion->version }} / {{ strtoupper($declaration->waiverVersion->locale) }}
                </div>
            @endif
            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
                <a href="{{ route($portalConfig['show_route'], ['id' => $shipment->id]) }}" class="btn btn-pr" data-testid="shipment-completion-link">الانتقال إلى خطوة المحفظة والإصدار</a>
                <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">مراجعة العرض المختار</a>
            </div>
        </div>
    </x-card>
@else
    <div class="grid-2">
        <x-card title="التصريح الإلزامي">
            <form method="POST" action="{{ route($portalConfig['declaration_submit_route'], ['id' => $shipment->id]) }}" style="display:flex;flex-direction:column;gap:16px">
                @csrf

                <div>
                    <div style="font-size:16px;font-weight:800;color:var(--tx);margin-bottom:10px">هل تحتوي هذه الشحنة على مواد خطرة؟</div>
                    <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--bd);border-radius:14px;margin-bottom:10px">
                        <input type="radio" name="contains_dangerous_goods" value="no" @checked(old('contains_dangerous_goods', ($declaration && $declaration->dg_flag_declared && ! $declaration->contains_dangerous_goods) ? 'no' : '') === 'no')>
                        <span>
                            <strong>لا</strong>
                            <span style="display:block;color:var(--td);font-size:13px">سأتابع عبر الإقرار القانوني الإلزامي وأؤكد صحة التصريح.</span>
                        </span>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--bd);border-radius:14px">
                        <input type="radio" name="contains_dangerous_goods" value="yes" @checked(old('contains_dangerous_goods', ($declaration && $declaration->dg_flag_declared && $declaration->contains_dangerous_goods) ? 'yes' : '') === 'yes')>
                        <span>
                            <strong>نعم</strong>
                            <span style="display:block;color:var(--td);font-size:13px">سيتم تعليق هذه الشحنة للتحقق اليدوي، ولن يستمر المسار العادي للإصدار.</span>
                        </span>
                    </label>
                    @error('contains_dangerous_goods')
                        <div style="margin-top:8px;color:#991b1b;font-size:13px">{{ $message }}</div>
                    @enderror
                </div>

                <div style="padding:16px;border-radius:16px;border:1px solid var(--bd);background:rgba(15,23,42,.02)">
                    <div style="font-size:16px;font-weight:800;color:var(--tx);margin-bottom:8px">الإقرار القانوني الإلزامي</div>
                    <div style="color:var(--td);font-size:14px;line-height:1.8;white-space:pre-line">{{ $waiver?->waiver_text ?? 'لا توجد نسخة إقرار قانوني نشطة حاليًا.' }}</div>
                    @if($waiver)
                        <div class="td-mono" style="margin-top:10px;font-size:12px;color:var(--tm)">الإصدار: {{ $waiver->version }} / {{ strtoupper($waiver->locale) }}</div>
                    @endif

                    <label style="display:flex;align-items:flex-start;gap:10px;margin-top:14px">
                        <input type="checkbox" name="accept_disclaimer" value="1" @checked(old('accept_disclaimer'))>
                        <span style="color:var(--td);font-size:14px">
                            أؤكد أن هذه الشحنة لا تحتوي على مواد خطرة، وأوافق على الإقرار القانوني أعلاه وأتحمل مسؤولية صحة هذا التصريح.
                        </span>
                    </label>
                    @error('accept_disclaimer')
                        <div style="margin-top:8px;color:#991b1b;font-size:13px">{{ $message }}</div>
                    @enderror
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" class="btn btn-pr">حفظ الإقرار والمتابعة</button>
                    <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">مراجعة العروض مرة أخرى</a>
                </div>
            </form>
        </x-card>

        <x-card title="ما الذي سيحدث بعد هذا الاختيار؟">
            <div style="display:flex;flex-direction:column;gap:14px">
                <div style="padding:14px;border:1px solid var(--bd);border-radius:16px">
                    <div style="font-size:15px;font-weight:800;color:var(--tx);margin-bottom:6px">إذا اخترت "نعم"</div>
                    <div style="color:var(--td);font-size:14px">
                        ستتوقف الشحنة فورًا في حالة تتطلب إجراءً يدويًا، ولن يمكن متابعة الإصدار العادي حتى يراجعها فريق الدعم أو العمليات.
                    </div>
                </div>
                <div style="padding:14px;border:1px solid var(--bd);border-radius:16px">
                    <div style="font-size:15px;font-weight:800;color:var(--tx);margin-bottom:6px">إذا اخترت "لا"</div>
                    <div style="color:var(--td);font-size:14px">
                        يجب أن توافق على الإقرار القانوني الإلزامي. من دون هذه الموافقة لن تنتقل الشحنة إلى أي خطوة لاحقة.
                    </div>
                </div>
                <div style="padding:14px;border:1px solid var(--bd);border-radius:16px">
                    <div style="font-size:15px;font-weight:800;color:var(--tx);margin-bottom:6px">السجل القانوني</div>
                    <div style="color:var(--td);font-size:14px">
                        سيُحفظ هذا التصريح مع وقت الإرسال وعنوان IP والمستخدم الذي نفّذ الإقرار ونسخة النص القانوني المرتبطة بالشحنة.
                    </div>
                </div>
            </div>
        </x-card>
    </div>
@endif
@endsection
