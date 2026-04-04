@extends('layouts.app')
@section('title', 'تفاصيل الشحنة')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <div class="header-main">
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.shipments.index') }}" style="color:inherit;text-decoration:none">الشحنات</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $shipmentSummary['reference'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل الشحنة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            رؤية داخلية للقراءة فقط للشحنة مع الحالة الموحّدة ومخرجات شركة الشحن وحالة التتبع العامة وملخصات أثر KYC المرتبطة. تخفي هذه الصفحة عمدًا مسارات تخزين المستندات الخام وحمولات شركات الشحن وقيم الرموز الخاصة وغيرها من البيانات غير الآمنة.
        </p>
    </div>
    <div class="header-actions">
        <a href="{{ route('internal.shipments.index') }}" class="btn btn-s">العودة إلى الصف</a>
        <a href="{{ route('internal.shipments.show', $shipment) }}" class="btn btn-pr">تحديث التفاصيل</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="REF" label="المرجع" :value="$shipmentSummary['reference']" />
    <x-stat-card icon="STS" label="سير العمل" :value="$shipmentSummary['workflow_status_label']" />
    <x-stat-card icon="TRK" label="الحالة الموحّدة" :value="$shipmentSummary['normalized_status_label']" />
    <x-stat-card icon="DOC" label="المستندات" :value="number_format($documents->count())" />
</div>

<section class="card" data-testid="internal-shipment-actions-card" style="margin-bottom:24px">
    <div class="card-title">إجراءات تشغيلية</div>
    <p style="margin:0 0 12px;color:var(--td);font-size:13px">
        تعرض هذه اللوحة الداخلية فقط الروابط التشغيلية الآمنة الموجودة أصلًا ضمن عقد المنتج الحي. وتبقى إعادة المحاولة وإعادة الإصدار والإلغاء وتحرير الحالة يدويًا غير متاحة عمدًا من هذه الواجهة.
    </p>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.shipments.show', $shipment) }}"
           class="btn btn-pr"
           data-testid="internal-shipment-refresh-link">تحديث العرض التشغيلي</a>

        @if($canCreateTickets)
            <a href="{{ route('internal.shipments.tickets.create', $shipment) }}"
               class="btn btn-s"
               data-testid="internal-shipment-create-linked-ticket-link">إنشاء تذكرة مرتبطة</a>
        @endif

        @if($canViewDocuments)
            <a href="{{ route('internal.shipments.documents.index', $shipment) }}"
               class="btn btn-s"
               data-testid="internal-shipment-documents-workspace-link">فتح مساحة المستندات</a>
        @endif

        @if($canViewAccount && isset($accountSummary['account']))
            <a href="{{ route('internal.accounts.show', $accountSummary['account']) }}"
               class="btn btn-s"
               data-testid="internal-shipment-actions-account-link">فتح تفاصيل الحساب المرتبط</a>
        @endif

        @if($canViewKyc && isset($accountSummary['account']))
            <a href="{{ route('internal.kyc.show', $accountSummary['account']) }}"
               class="btn btn-s"
               data-testid="internal-shipment-actions-kyc-link">فتح KYC والقيود</a>
        @endif

        @if(!empty($publicTracking['url']))
            <a href="{{ $publicTracking['url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               class="btn btn-s"
               data-testid="internal-shipment-actions-public-tracking-link">فتح صفحة التتبع العامة</a>
            <button type="button"
                    class="btn btn-s"
                    data-testid="internal-shipment-copy-public-tracking-link"
                    data-copy-text="{{ $publicTracking['url'] }}"
                    data-copy-target="internal-shipment-copy-status">نسخ رابط التتبع العام</button>
        @endif
    </div>

    <div id="internal-shipment-copy-status"
         data-testid="internal-shipment-copy-status"
         aria-live="polite"
         style="font-size:12px;color:var(--tm);margin-top:12px">
        استخدم هذه الاختصارات للمتابعة التشغيلية دون تجاوز ضوابط الإقرار أو المحفظة أو الامتثال.
    </div>
</section>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="internal-shipment-summary-card">
        <div class="card-title">ملخص الشحنة</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">المرجع</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['reference'] }}</dd>

            <dt style="color:var(--tm)">حالة سير العمل</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['workflow_status_label'] }}</dd>

            <dt style="color:var(--tm)">الحالة الموحّدة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['normalized_status_label'] }}</dd>

            <dt style="color:var(--tm)">المصدر</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['source_label'] }}</dd>

            <dt style="color:var(--tm)">أُنشئت</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['created_at'] ?? '—' }}</dd>

            <dt style="color:var(--tm)">حُدثت</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['updated_at'] ?? '—' }}</dd>

            <dt style="color:var(--tm)">الوجهة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $shipmentSummary['recipient_city'] ?: '—' }} @if($shipmentSummary['recipient_country']) • {{ $shipmentSummary['recipient_country'] }} @endif</dd>

            <dt style="color:var(--tm)">العلامات</dt>
            <dd style="margin:0;color:var(--tx)">
                @if($shipmentSummary['flags'] !== [])
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        @foreach($shipmentSummary['flags'] as $flag)
                            <span class="badge">{{ $flag }}</span>
                        @endforeach
                    </div>
                @else
                    لا توجد
                @endif
            </dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-shipment-linked-account-card">
        <div class="card-title">الحساب المرتبط</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div>
                <div style="font-weight:700;color:var(--tx)">{{ $accountSummary['name'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $accountSummary['type_label'] }} • {{ $accountSummary['slug'] }}</div>
            </div>
            <div style="font-size:13px;color:var(--tx)">{{ $accountSummary['owner_label'] }}</div>
            <div style="font-size:12px;color:var(--td)">{{ $accountSummary['owner_secondary'] }}</div>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                @if($canViewAccount && isset($accountSummary['account']))
                    <a href="{{ route('internal.accounts.show', $accountSummary['account']) }}" data-testid="internal-shipment-account-link" class="btn btn-s">فتح تفاصيل الحساب</a>
                @endif
                @if($canViewKyc && isset($accountSummary['account']))
                    <a href="{{ route('internal.kyc.show', $accountSummary['account']) }}" data-testid="internal-shipment-kyc-link" class="btn btn-s">فتح تفاصيل KYC</a>
                @endif
            </div>
        </div>
    </section>
</div>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="internal-shipment-operational-state-card">
        <div class="card-title">شركة الشحن والتتبع</div>
        <dl style="display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">شركة الشحن</dt>
            <dd style="margin:0;color:var(--tx)">{{ $carrierSummary['carrier_label'] }}</dd>

            <dt style="color:var(--tm)">الخدمة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $carrierSummary['service_label'] }}</dd>

            <dt style="color:var(--tm)">شحنة شركة الشحن</dt>
            <dd style="margin:0;color:var(--tx)">{{ $carrierSummary['carrier_shipment_id'] }}</dd>

            <dt style="color:var(--tm)">التتبع</dt>
            <dd style="margin:0;color:var(--tx)">{{ $trackingSummary['tracking_number'] }}</dd>

            <dt style="color:var(--tm)">رقم بوليصة الشحن</dt>
            <dd style="margin:0;color:var(--tx)">{{ $trackingSummary['awb_number'] }}</dd>

            <dt style="color:var(--tm)">التتبع العام</dt>
            <dd style="margin:0;color:var(--tx)">
                <div>{{ $publicTracking['label'] }}</div>
                <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $publicTracking['detail'] }}</div>
                @if(!empty($publicTracking['url']))
                    <div style="margin-top:10px">
                        <a href="{{ $publicTracking['url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn btn-s"
                           data-testid="internal-shipment-public-tracking-link">فتح صفحة التتبع العامة</a>
                    </div>
                @endif
                <div style="font-size:12px;color:var(--tm);margin-top:6px">فُعل في: {{ $publicTracking['enabled_at'] }} • ينتهي في: {{ $publicTracking['expires_at'] }}</div>
            </dd>
        </dl>
    </section>

    <section class="card" data-testid="internal-shipment-parcels-card">
        <div class="card-title">ملخص الطرود</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($shipment->parcels as $parcel)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">الطرد {{ $parcel->sequence }}</div>
                    <div style="font-size:13px;color:var(--td)">
                        الوزن: {{ number_format((float) ($parcel->weight ?? 0), 2) }} كغ
                        • الوزن المحتسب: {{ number_format((float) $parcel->chargeableWeight(), 2) }} كغ
                    </div>
                    <div style="font-size:12px;color:var(--tm)">
                        {{ strtoupper((string) ($parcel->packaging_type ?? 'custom')) }}
                        @if($parcel->reference)
                            • المرجع: {{ $parcel->reference }}
                        @endif
                        @if($parcel->description)
                            • {{ $parcel->description }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">لا توجد سجلات طرود ظاهرة على هذه الشحنة.</div>
            @endforelse
        </div>
    </section>
</div>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="internal-shipment-timeline-card">
        <div class="card-title">معاينة الخط الزمني</div>
        <div style="font-size:13px;color:var(--td);margin-bottom:12px">
            الحالة الحالية: <strong style="color:var(--tx)">{{ $timeline['current_status_label'] }}</strong>
            • آخر تحديث: {{ $timeline['last_updated'] ?? '—' }}
            • الأحداث: {{ number_format($timeline['total_events']) }}
        </div>

        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($timeline['events'] as $event)
                <div data-testid="internal-shipment-event-item" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $event['event_type_label'] }}</div>
                    <div style="font-size:13px;color:var(--td)">{{ $event['description'] }}</div>
                    <div style="font-size:12px;color:var(--tm);margin-top:6px">
                        {{ $event['status_label'] }} • {{ $event['source_label'] }} • {{ $event['event_time_display'] ?? '—' }}
                        @if(!empty($event['location']))
                            • {{ $event['location'] }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">لا توجد أحداث خط زمني متاحة حاليًا.</div>
            @endforelse
        </div>
    </section>

    <section class="card" data-testid="internal-shipment-documents-card">
        <div class="card-title">ملخص المستندات</div>
        <p style="margin:0 0 12px;color:var(--td);font-size:13px">{{ $documentHeadline }}</p>
        @if($canViewDocuments)
            <div style="margin:0 0 12px">
                <a href="{{ route('internal.shipments.documents.index', $shipment) }}"
                   class="btn btn-s"
                   data-testid="internal-shipment-documents-link">فتح مساحة المستندات</a>
            </div>
        @endif
        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($documents as $document)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $document['document_type_label'] }}</div>
                    <div style="font-size:13px;color:var(--td)">{{ $document['filename'] }}</div>
                    <div style="font-size:12px;color:var(--tm)">
                        {{ $document['carrier_label'] }} • {{ $document['format_label'] }} • {{ $document['retrieval_mode_label'] }} • {{ $document['size_label'] }} • {{ $document['created_at_display'] ?? '—' }}
                    </div>
                    @if(!empty($document['tracking_number']))
                        <div style="font-size:12px;color:var(--td);margin-top:6px">التتبع: {{ $document['tracking_number'] }}</div>
                    @endif
                    @if(!empty($document['notes']))
                        <div style="font-size:12px;color:var(--td);margin-top:6px">{{ collect($document['notes'])->implode(' • ') }}</div>
                    @endif
                </div>
            @empty
                <div class="empty-state">لا توجد ملخصات مستندات آمنة متاحة حاليًا لهذه الشحنة.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="card" data-testid="internal-shipment-notifications-card" style="margin-bottom:24px">
    <div class="card-title">نشاط الإشعارات</div>
    @if(!$notifications['visible'])
        <div class="empty-state">عرض الإشعارات غير مفعّل لهذا الدور.</div>
    @else
        <div class="grid-auto-200" style="margin-bottom:14px">
            <div>
                <div style="font-size:12px;color:var(--tm)">إجمالي السجلات</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($notifications['total_count']) }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">أُرسلت أو سُلّمت</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($notifications['delivered_count']) }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">تحتاج إلى مراجعة</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($notifications['issue_count']) }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">آخر نشاط</div>
                <div style="font-weight:700;color:var(--tx)">{{ $notifications['latest_created_at'] }}</div>
            </div>
        </div>

        <div style="font-size:12px;color:var(--tm);margin-bottom:12px">
            القنوات:
            @if($notifications['channels'] !== [])
                {{ collect($notifications['channels'])->implode(' / ') }}
            @else
                لم يُسجل شيء بعد
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:10px">
            @forelse($notifications['items'] as $notification)
                <div data-testid="internal-shipment-notification-item" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                        <div>
                            <div style="font-weight:700;color:var(--tx)">{{ $notification['subject'] }}</div>
                            <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $notification['event_type_label'] }} / {{ $notification['channel_label'] }}</div>
                        </div>
                        <span class="badge">{{ $notification['status_label'] }}</span>
                    </div>
                    <div style="font-size:12px;color:var(--tm);margin-top:8px">
                        أُنشئ: {{ $notification['created_at_display'] }}
                        @if($notification['sent_at_display'] !== '-')
                            | أُرسل: {{ $notification['sent_at_display'] }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">لا يوجد نشاط إشعارات مرتبط بالشحنة ظاهر حاليًا.</div>
            @endforelse
        </div>
    @endif
</section>

<section class="card" data-testid="internal-shipment-kyc-summary-card">
    <div class="card-title">أثر التحقق والقيود</div>
    @if($kycSummary)
        <div class="grid-auto-200">
            <div>
                <div style="font-size:12px;color:var(--tm)">حالة التحقق</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['label'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">أثر الشحنة</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['queue_summary'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">إجراء إضافي</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycSummary['action_label'] }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm)">الشحنات المحجوبة</div>
                <div style="font-weight:700;color:var(--tx)">{{ number_format($kycSummary['blocked_shipments_count']) }}</div>
            </div>
        </div>

        @if($kycSummary['restriction_names'] !== [])
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                @foreach($kycSummary['restriction_names'] as $restrictionName)
                    <span class="badge">{{ $restrictionName }}</span>
                @endforeach
            </div>
        @endif
    @else
        <div class="empty-state">لا يتوفر ملخص تحقق مرتبط بالحساب لهذه الشحنة.</div>
    @endif
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('click', async function (event) {
    const trigger = event.target.closest('[data-copy-text]');

    if (!trigger) {
        return;
    }

    const text = trigger.getAttribute('data-copy-text') || '';
    const statusId = trigger.getAttribute('data-copy-target');
    const statusNode = statusId ? document.getElementById(statusId) : null;

    if (!text) {
        return;
    }

    const setStatus = function (message) {
        if (statusNode) {
            statusNode.textContent = message;
        }
    };

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const helper = document.createElement('textarea');
            helper.value = text;
            helper.setAttribute('readonly', 'readonly');
            helper.style.position = 'absolute';
            helper.style.left = '-9999px';
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            document.body.removeChild(helper);
        }

        setStatus('تم نسخ رابط التتبع العام للمتابعة الداخلية.');
    } catch (error) {
        setStatus('تعذر نسخ رابط التتبع العام تلقائيًا. ما يزال بإمكانك فتحه مباشرة.');
    }
});
</script>
@endpush
