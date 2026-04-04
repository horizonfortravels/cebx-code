@extends('layouts.app')
@section('title', 'تفاصيل المحفظة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.billing.index') }}" style="color:inherit;text-decoration:none">المحفظة والفوترة</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $account->name }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل محفظة الحساب</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            رؤية للقراءة فقط للرصيد والسجل والحجوزات المسبقة وأحداث المحفظة المرتبطة بالشحنات لموظفي العمليات. تستبعد هذه الواجهة عمدًا وسائل الدفع وروابط الدفع وبيانات البوابات والبيانات الخام وغيرها من تفاصيل الفوترة غير الآمنة.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.billing.index') }}" class="btn btn-s">العودة إلى قائمة الفوترة</a>
        @if($canViewAccount)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s" data-testid="internal-billing-account-link">فتح تفاصيل الحساب المرتبط</a>
        @endif
        <a href="{{ route('internal.billing.show', $account) }}" class="btn btn-pr">تحديث التفاصيل</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="BAL" label="الرصيد الحالي" :value="$walletSummary['current_balance']" />
    <x-stat-card icon="RSV" label="الرصيد المحجوز" :value="$walletSummary['reserved_balance']" />
    <x-stat-card icon="AVL" label="الرصيد المتاح" :value="$walletSummary['available_balance']" />
    <x-stat-card icon="STS" label="حالة المحفظة" :value="$walletSummary['status_label']" />
</div>

@if($walletBackfillOnly)
    <div class="card" style="margin-bottom:24px;border-color:#f59e0b">
        <div class="card-title">المسار الاحتياطي للمحفظة القديمة</div>
        <p style="margin:0;color:var(--td);font-size:13px">
            يستخدم هذا الحساب حاليًا المسار الاحتياطي للمحفظة القديمة فقط. تبقى رؤية الرصيد للقراءة متاحة، لكن لوحات السجل والحجز المسبق والتمويل تظل فارغة عمدًا حتى يتوفر مصدر الحقيقة الخاص بمحفظة الفوترة.
        </p>
    </div>
@endif

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-billing-summary-card">
        <div class="card-title">ملخص المحفظة</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">الحساب</dt>
            <dd style="margin:0;color:var(--tx)">{{ $account->name }}</dd>

            <dt style="color:var(--tm)">نوع الحساب</dt>
            <dd style="margin:0;color:var(--tx)">{{ $account->isOrganization() ? 'منظمة' : 'فردي' }}</dd>

            <dt style="color:var(--tm)">مصدر المحفظة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['source_label'] }}</dd>

            <dt style="color:var(--tm)">العملة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['currency'] }}</dd>

            <dt style="color:var(--tm)">الرصيد الحالي</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['current_balance'] }}</dd>

            <dt style="color:var(--tm)">الرصيد المحجوز</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['reserved_balance'] }}</dd>

            <dt style="color:var(--tm)">الرصيد المتاح</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['available_balance'] }}</dd>

            <dt style="color:var(--tm)">إجمالي المبالغ المضافة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['total_credited'] }}</dd>

            <dt style="color:var(--tm)">إجمالي المبالغ المخصومة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['total_debited'] }}</dd>

            <dt style="color:var(--tm)">ملاحظة الملخص</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['summary_note'] }}</dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-billing-kyc-card">
        <div class="card-title">سياق KYC والقيود</div>
        @if($kycSummary)
            <div style="display:flex;flex-direction:column;gap:12px">
                <div>
                    <div style="font-size:12px;color:var(--tm)">حالة KYC الحالية</div>
                    <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['status_label'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">الأثر التشغيلي</div>
                    <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['queue_summary'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">الإجراء التالي</div>
                    <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['action_label'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">طبقات القيود</div>
                    @if($kycSummary['restriction_names'] !== [])
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px">
                            @foreach($kycSummary['restriction_names'] as $restrictionName)
                                <span class="badge">{{ $restrictionName }}</span>
                            @endforeach
                        </div>
                    @else
                        <div style="color:var(--tx)">لا توجد قيود مسماة نشطة حاليًا.</div>
                    @endif
                </div>
            </div>
        @else
            <div class="empty-state">لا يوجد ملخص KYC مرتبط متاح لهذا الحساب حاليًا.</div>
        @endif
    </section>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-billing-ledger-card">
        <div class="card-title">ملخص السجل الأخير</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($ledgerEntries as $entry)
                <div data-testid="internal-billing-ledger-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $entry['type'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $entry['reference'] }}</div>
                        </div>
                        <div style="text-align:left">
                            <div style="font-weight:700;color:var(--tx)">{{ $entry['amount'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $entry['direction'] }}</div>
                        </div>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">الرصيد الجاري: {{ $entry['running_balance'] }} | {{ $entry['created_at'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $entry['note'] }}</div>
                    <div style="margin-top:10px">
                        <a href="{{ route('internal.billing.ledger.show', ['account' => $account, 'entry' => $entry['id']]) }}" class="btn btn-s" data-testid="internal-billing-ledger-detail-link">عرض تفاصيل السجل</a>
                    </div>
                </div>
            @empty
                <div class="empty-state">لا توجد إدخالات ملخص سجل آمنة ظاهرة حاليًا.</div>
            @endforelse
        </div>
    </section>

    <section class="card" data-testid="internal-billing-topups-card">
        <div class="card-title">آخر عمليات الشحن والتسويات</div>

        <div style="margin-bottom:12px">
            <div style="font-size:12px;color:var(--tm);margin-bottom:8px">عمليات الشحن</div>
            <div style="display:flex;flex-direction:column;gap:10px">
                @forelse($topups as $topup)
                    <div data-testid="internal-billing-topup-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <div>
                                <div style="font-weight:700;color:var(--tx)">{{ $topup['amount'] }}</div>
                                <div style="font-size:12px;color:var(--td)">{{ $topup['gateway'] }}</div>
                            </div>
                            <span class="badge">{{ $topup['status'] }}</span>
                        </div>
                        <div style="font-size:12px;color:var(--tm);margin-top:8px">أُنشئت: {{ $topup['created_at'] }} | أُكدت: {{ $topup['confirmed_at'] }}</div>
                    </div>
                @empty
                    <div class="empty-state">لا يوجد ملخص حديث لعمليات الشحن.</div>
                @endforelse
            </div>
        </div>

        <div>
            <div style="font-size:12px;color:var(--tm);margin-bottom:8px">التسويات</div>
            <div style="display:flex;flex-direction:column;gap:10px">
                @forelse($adjustments as $adjustment)
                    <div data-testid="internal-billing-adjustment-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <div style="font-weight:700;color:var(--tx)">{{ $adjustment['amount'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $adjustment['direction'] }}</div>
                        </div>
                        <div style="font-size:12px;color:var(--tm);margin-top:8px">{{ $adjustment['created_at'] }}</div>
                        <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $adjustment['note'] }}</div>
                    </div>
                @empty
                    <div class="empty-state">لا يوجد ملخص حديث للتسويات.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>

<div class="grid-2">
    <section class="card" data-testid="internal-billing-holds-card">
        <div class="card-title">آخر الحجوزات المسبقة</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($holds as $hold)
                <div data-testid="internal-billing-hold-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $hold['shipment_reference'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $hold['source'] }}</div>
                        </div>
                        <div style="text-align:left">
                            <div style="font-weight:700;color:var(--tx)">{{ $hold['amount'] }}</div>
                            <span class="badge">{{ $hold['status'] }}</span>
                        </div>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">حالة الشحنة: {{ $hold['shipment_status'] }} | إجمالي الشحنة: {{ $hold['shipment_total'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $hold['outcome'] }}</div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">أُنشئ: {{ $hold['created_at'] }} | ينتهي: {{ $hold['expires_at'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">تم التحصيل: {{ $hold['captured_at'] }} | تم الإفراج: {{ $hold['released_at'] }}</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                        <a href="{{ route('internal.billing.preflights.show', ['account' => $account, 'hold' => $hold['id']]) }}" class="btn btn-s" data-testid="internal-billing-hold-detail-link">عرض تفاصيل الحجز المسبق</a>
                        @if($canViewShipment && $hold['shipment_id'] !== '')
                            <a href="{{ route('internal.shipments.show', $hold['shipment_id']) }}" class="btn btn-s">فتح الشحنة المرتبطة</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">لا يوجد ملخص حديث للحجوزات المسبقة.</div>
            @endforelse
        </div>
    </section>

    <section class="card" data-testid="internal-billing-shipment-events-card">
        <div class="card-title">أحداث المحفظة المرتبطة بالشحنات</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($shipmentWalletEvents as $event)
                <div data-testid="internal-billing-shipment-event-entry" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $event['label'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $event['shipment_reference'] }}</div>
                        </div>
                        <div style="font-weight:700;color:var(--tx)">{{ $event['amount'] }}</div>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">{{ $event['shipment_status'] }} | {{ $event['created_at'] }}</div>
                    <div style="font-size:12px;color:var(--td);margin-top:6px">{{ $event['note'] }}</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                        <a href="{{ route('internal.billing.ledger.show', ['account' => $account, 'entry' => $event['ledger_id']]) }}" class="btn btn-s" data-testid="internal-billing-shipment-event-ledger-link">عرض تفاصيل السجل</a>
                        @if($canViewShipment && $event['shipment_id'] !== '')
                            <a href="{{ route('internal.shipments.show', $event['shipment_id']) }}" class="btn btn-s">فتح الشحنة المرتبطة</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">لا توجد أحداث محفظة حديثة مرتبطة بالشحنات ظاهرة بعد.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
