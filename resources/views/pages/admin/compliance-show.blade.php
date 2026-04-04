@extends('layouts.app')
@section('title', 'حالة الامتثال')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <div class="header-main">
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.compliance.index') }}" style="color:inherit;text-decoration:none">صف الامتثال</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $shipmentSummary['reference'] ?? ('الحالة ' . $declaration->id) }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل حالة الامتثال</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            رؤية تشغيلية للقراءة فقط لحالة الإقرار والإقرار القانوني وبيانات البضائع الخطرة الوصفية ونشاط تدقيق الامتثال الأخير. تخفي هذه الصفحة عمدًا نصوص الإعفاء الخام والتجزئات وعناوين IP ووكلاء المستخدم والحمولات الخام للتدقيق.
        </p>
    </div>
    <div class="header-actions">
        @if($canViewShipment && $shipment)
            <a href="{{ route('internal.shipments.show', $shipment) }}" class="btn btn-s" data-testid="internal-compliance-shipment-link">فتح الشحنة المرتبطة</a>
        @endif
        @if($canViewAccount && $account)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s" data-testid="internal-compliance-account-link">فتح الحساب المرتبط</a>
        @endif
        @if($canViewKyc && $account)
            <a href="{{ route('internal.kyc.show', $account) }}" class="btn btn-s" data-testid="internal-compliance-kyc-link">فتح KYC المرتبط</a>
        @endif
        @if($canViewBilling && $account && $hasBillingContext)
            <a href="{{ route('internal.billing.show', $account) }}" class="btn btn-s" data-testid="internal-compliance-billing-link">فتح الفوترة المرتبطة</a>
            @if($linkedPreflightHold)
                <a href="{{ route('internal.billing.preflights.show', ['account' => $account, 'hold' => $linkedPreflightHold]) }}" class="btn btn-s" data-testid="internal-compliance-preflight-link">فتح الحجز المسبق المرتبط</a>
            @endif
        @endif
        <a href="{{ route('internal.compliance.index') }}" class="btn btn-s">العودة إلى الصف</a>
        <a href="{{ route('internal.compliance.show', $declaration) }}" class="btn btn-pr">تحديث التفاصيل</a>
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="CMP" label="حالة الامتثال" :value="$declarationSummary['status']" />
    <x-stat-card icon="REV" label="حالة المراجعة" :value="$reviewSummary['label']" />
    <x-stat-card icon="DG" label="يحتوي على بضائع خطرة" :value="$declarationSummary['contains_dg']" />
    <x-stat-card icon="LGL" label="الإقرار القانوني" :value="$legalSummary['state_label']" />
</div>

@if($canManageComplianceActions)
    <section class="card" data-testid="internal-compliance-actions-card" style="margin-bottom:24px">
        <div class="card-title">إجراءات الامتثال الداخلية</div>
        @if($requestCorrectionAction['is_available'])
            <p style="margin:0 0 14px;color:var(--td);font-size:14px">
                {{ $requestCorrectionAction['detail'] }}
            </p>
            <form method="POST"
                  action="{{ route('internal.compliance.requires-action', $declaration) }}"
                  data-testid="internal-compliance-requires-action-form"
                  style="display:flex;flex-direction:column;gap:12px">
                @csrf
                <label style="display:flex;flex-direction:column;gap:6px">
                    <span style="font-size:12px;color:var(--tm)">سبب المراجعة الداخلية</span>
                    <textarea name="reason" rows="3" class="input" style="min-height:92px" required>{{ old('reason') }}</textarea>
                </label>
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                    <div style="font-size:12px;color:var(--tm)">{{ $requestCorrectionAction['headline'] }}</div>
                    <button type="submit" class="btn btn-pr" data-testid="internal-compliance-requires-action-button">طلب تصحيح</button>
                </div>
            </form>
        @else
            <p style="margin:0;color:var(--td)" data-testid="internal-compliance-action-state-note">
                {{ $requestCorrectionAction['headline'] }}. {{ $requestCorrectionAction['detail'] }}
            </p>
        @endif
    </section>
@endif

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-case-summary-card">
        <div class="card-title">ملخص الحالة</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">الحالة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['status'] }}</dd>

            <dt style="color:var(--tm)">حالة المراجعة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $reviewSummary['label'] }}</dd>

            <dt style="color:var(--tm)">تفاصيل المراجعة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $reviewSummary['detail'] }}</dd>

            <dt style="color:var(--tm)">تم التصريح بالبضائع الخطرة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['dg_answered'] }}</dd>

            <dt style="color:var(--tm)">يحتوي على بضائع خطرة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['contains_dg'] }}</dd>

            <dt style="color:var(--tm)">تم التصريح في</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['declared_at'] }}</dd>

            <dt style="color:var(--tm)">آخر تحديث</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['updated_at'] }}</dd>

            <dt style="color:var(--tm)">سبب الإيقاف</dt>
            <dd style="margin:0;color:var(--tx)">{{ $declarationSummary['hold_reason'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-compliance-shipment-card">
        <div class="card-title">سياق الشحنة</div>
        @if($shipmentSummary)
            <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">المرجع</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['reference'] }}</dd>

                <dt style="color:var(--tm)">حالة سير العمل</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['workflow_status'] }}</dd>

                <dt style="color:var(--tm)">المصدر</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['source'] }}</dd>

                <dt style="color:var(--tm)">البضائع الخطرة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['dangerous_goods'] }}</dd>

                <dt style="color:var(--tm)">سبب الحالة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['status_reason'] }}</dd>

                <dt style="color:var(--tm)">أُنشئت</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['created_at'] }}</dd>

                <dt style="color:var(--tm)">حُدثت</dt>
                <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['updated_at'] }}</dd>
            </dl>
        @else
            <div class="empty-state">لا يتوفر سياق شحنة لهذه الحالة.</div>
        @endif
    </section>
</div>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-account-card">
        <div class="card-title">سياق الحساب والمنظمة</div>
        @if($accountSummary)
            <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">الحساب</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['name'] }}</dd>

                <dt style="color:var(--tm)">المعرّف</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['slug'] }}</dd>

                <dt style="color:var(--tm)">النوع</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['type'] }}</dd>

                <dt style="color:var(--tm)">دورة الحياة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['status'] }}</dd>

                <dt style="color:var(--tm)">المنظمة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['organization'] }}</dd>

                <dt style="color:var(--tm)">المالك</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['owner'] }}</dd>

                <dt style="color:var(--tm)">بريد المالك</dt>
                <dd style="margin:0;color:var(--tx)">{{ $accountSummary['owner_email'] }}</dd>
            </dl>
        @else
            <div class="empty-state">لا يتوفر سياق حساب لهذه الحالة.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-compliance-legal-card">
        <div class="card-title">ملخص الإقرار القانوني</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">الحالة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['state_label'] }}</dd>

            <dt style="color:var(--tm)">التفاصيل</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['detail'] }}</dd>

            <dt style="color:var(--tm)">الإصدار</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['version'] }}</dd>

            <dt style="color:var(--tm)">تم القبول في</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['accepted_at'] }}</dd>

            <dt style="color:var(--tm)">اللغة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $legalSummary['locale'] }}</dd>
        </dl>
    </section>
</div>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-workflow-card">
        <div class="card-title">أثر سير عمل الإقرار</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">سير عمل الشحنة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['shipment_workflow_state'] }}</dd>

            <dt style="color:var(--tm)">محجوبة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['is_blocked'] ? 'نعم' : 'لا' }}</dd>

            <dt style="color:var(--tm)">الإقرار مكتمل</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['declaration_complete'] ? 'نعم' : 'لا' }}</dd>

            <dt style="color:var(--tm)">يتطلب إخلاء مسؤولية</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['requires_disclaimer'] ? 'نعم' : 'لا' }}</dd>

            <dt style="color:var(--tm)">الإجراء التالي</dt>
            <dd style="margin:0;color:var(--tx)">{{ $workflowSummary['next_action'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-compliance-notes-card">
        <div class="card-title">ملاحظات الامتثال</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($notesSummary as $note)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $note['source'] }}</div>
                    <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $note['detail'] }}</div>
                </div>
            @empty
                <div class="empty-state">لا توجد ملاحظات امتثال آمنة ظاهرة لهذه الحالة.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="card" data-testid="internal-compliance-effects-card" style="margin-bottom:24px">
    <div class="card-title">القيود الحالية والأثر التشغيلي</div>
    @if($restrictionSummary)
        <div class="grid-auto-200">
            <div>
                <div style="font-size:12px;color:var(--tm)">حالة KYC</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['status_label'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">ملخص الصف</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['queue_summary'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">أثر الشحنة</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['shipping_label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $restrictionSummary['shipping_detail'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">الشحن الدولي</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['international_label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $restrictionSummary['international_detail'] }}</div>
            </div>
        </div>

        <div class="grid-auto-240" style="margin-top:12px">
            <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03)">
                <div style="font-size:12px;color:var(--tm)">إجراء إضافي</div>
                <div style="font-weight:700;color:var(--tx)">{{ $restrictionSummary['action_label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $restrictionSummary['action_detail'] }}</div>
            </div>
            <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03)">
                <div style="font-size:12px;color:var(--tm)">الشحنات المحجوبة</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($restrictionSummary['blocked_shipments_count']) }}</div>
            </div>
        </div>

        @if($restrictionSummary['restriction_names'] !== [])
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                @foreach($restrictionSummary['restriction_names'] as $restrictionName)
                    <span class="badge">{{ $restrictionName }}</span>
                @endforeach
            </div>
        @endif
    @else
        <div class="empty-state">لا يوجد ملخص قيود مرتبط لهذه الحالة.</div>
    @endif
</section>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="internal-compliance-dg-card">
        <div class="card-title">البيانات الوصفية للبضائع الخطرة</div>
        @if($dgMetadataSummary)
            <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">رقم UN</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['un_number'] }}</dd>

                <dt style="color:var(--tm)">فئة البضائع الخطرة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['dg_class'] }}</dd>

                <dt style="color:var(--tm)">مجموعة التعبئة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['packing_group'] }}</dd>

                <dt style="color:var(--tm)">اسم الشحن الصحيح</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['proper_shipping_name'] }}</dd>

                <dt style="color:var(--tm)">الكمية</dt>
                <dd style="margin:0;color:var(--tx)">{{ $dgMetadataSummary['quantity'] }}</dd>
            </dl>
        @else
            <div class="empty-state">لا توجد بيانات وصفية آمنة للبضائع الخطرة ظاهرة لهذه الحالة.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-compliance-audit-card">
        <div class="card-title">ملخص تدقيق الامتثال الأخير</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($auditEntries as $entry)
                <div data-testid="internal-compliance-audit-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $entry['action'] }}</div>
                    <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $entry['actor_role'] }} | {{ $entry['created_at'] }}</div>
                    @if(!empty($entry['change_summary']))
                        <div data-testid="internal-compliance-audit-change-summary" style="font-size:12px;color:var(--td);margin-top:8px">
                            ملخص التغيير: {{ $entry['change_summary'] }}
                        </div>
                    @endif
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">{{ $entry['note'] }}</div>
                </div>
            @empty
                <div class="empty-state">لا توجد إدخالات تدقيق امتثال ظاهرة لهذه الحالة بعد.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
