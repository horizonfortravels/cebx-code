@extends('layouts.app')
@section('title', 'ملف التحقق')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <div class="header-main">
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.kyc.index') }}" style="color:inherit;text-decoration:none">حالات التحقق</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $account->name }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">ملف التحقق</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:780px">
            {{ $account->name }} • {{ $account->slug ?? '—' }}<br>
            صفحة قراءة تشغيلية توحد ملخص الحساب، حالة التحقق، الوثائق المرسلة، القيود الحالية، وآخر المراجعات المسجلة دون إظهار مسارات التخزين أو المحتوى الخام للوثائق.
        </p>
    </div>
    <div class="header-actions">
        @if($canViewAccount)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s" data-testid="kyc-account-summary-link">Account Summary</a>
        @endif
        <a href="{{ route('internal.kyc.index') }}" class="btn btn-s">العودة إلى الطابور</a>
        <a href="{{ route('internal.kyc.show', $account) }}" class="btn btn-pr">تحديث الملف</a>
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="KYC" label="حالة التحقق" :value="$kyc['label']" />
    <x-stat-card icon="ACC" label="نوع الحساب" :value="$accountTypeLabel" />
    <x-stat-card icon="DOC" label="الوثائق المستلمة" :value="number_format($documents->count()) . ' / ' . number_format(count($kyc['required_documents']))" />
    <x-stat-card icon="RST" label="القيود الحالية" :value="$restrictions->isNotEmpty() ? 'نشطة' : 'لا توجد قيود'" />
</div>

@if($canReviewKyc)
    <section class="card" data-testid="kyc-review-card" style="margin-bottom:24px">
        <div class="card-title">إدارة قرار التحقق</div>
        @if($canTakeDecision)
            <p style="margin:0 0 14px;color:var(--td);font-size:14px">
                تعتمد هذه المرحلة على قرار صريح ومُدقَّق. يمكن إضافة ملاحظة داخلية مع الاعتماد، بينما يتطلب الرفض سببًا واضحًا وملاحظة داخلية اختيارية.
            </p>
            <div class="grid-main-sidebar-tight">
                <form method="POST" action="{{ route('internal.kyc.approve', $account) }}" data-testid="kyc-approve-form" style="display:flex;flex-direction:column;gap:12px">
                    @csrf
                    <div style="font-weight:700;color:var(--tx)">اعتماد الحالة</div>
                    <label style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:12px;color:var(--tm)">ملاحظة داخلية اختيارية</span>
                        <textarea name="notes" rows="3" class="input" style="min-height:92px">{{ old('notes') }}</textarea>
                    </label>
                    <button type="submit" class="btn btn-pr" data-testid="kyc-approve-button">اعتماد حالة التحقق</button>
                </form>

                <form method="POST" action="{{ route('internal.kyc.reject', $account) }}" data-testid="kyc-reject-form" style="display:flex;flex-direction:column;gap:12px">
                    @csrf
                    <div style="font-weight:700;color:var(--tx)">رفض الحالة</div>
                    <label style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:12px;color:var(--tm)">سبب الرفض</span>
                        <input type="text" name="reason" class="input" maxlength="255" value="{{ old('reason') }}" required>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:6px">
                        <span style="font-size:12px;color:var(--tm)">ملاحظة داخلية اختيارية</span>
                        <textarea name="notes" rows="3" class="input" style="min-height:92px">{{ old('notes') }}</textarea>
                    </label>
                    <button type="submit" class="btn btn-d" data-testid="kyc-reject-button">رفض حالة التحقق</button>
                </form>
            </div>
        @else
            <p style="margin:0;color:var(--td)" data-testid="kyc-review-state-note">
                لا توجد قرارات متاحة لهذا الملف حاليًا. يدعم النموذج الحالي اعتماد أو رفض الحالات التي ما تزال قيد المراجعة فقط.
            </p>
        @endif
    </section>
@endif

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="kyc-account-summary-card">
        <div class="card-title">ملخص الحساب</div>
        <dl style="display:grid;grid-template-columns:minmax(110px,150px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">اسم الحساب</dt>
            <dd style="margin:0;color:var(--tx)">{{ $account->name }}</dd>

            <dt style="color:var(--tm)">المعرّف المختصر</dt>
            <dd style="margin:0;color:var(--tx)">{{ $account->slug ?? '—' }}</dd>

            <dt style="color:var(--tm)">نوع الحساب</dt>
            <dd style="margin:0;color:var(--tx)">{{ $accountTypeLabel }}</dd>

            <dt style="color:var(--tm)">حالة الحساب</dt>
            <dd style="margin:0;color:var(--tx)">{{ $accountStatusLabel }}</dd>

            @if($account->isOrganization())
                <dt style="color:var(--tm)">المؤسسة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $account->organizationProfile?->legal_name ?: 'ملف المؤسسة غير مكتمل' }}</dd>

                <dt style="color:var(--tm)">السجل التجاري</dt>
                <dd style="margin:0;color:var(--tx)">{{ $account->organizationProfile?->registration_number ?: '—' }}</dd>
            @else
                <dt style="color:var(--tm)">المالك</dt>
                <dd style="margin:0;color:var(--tx)">{{ $owner?->name ?? 'لا يوجد مالك ظاهر' }}</dd>

                <dt style="color:var(--tm)">البريد</dt>
                <dd style="margin:0;color:var(--tx)">{{ $owner?->email ?? 'لا يوجد بريد ظاهر' }}</dd>
            @endif
        </dl>
    </section>

    <section class="card" data-testid="kyc-status-card">
        <div class="card-title">حالة التحقق والمراجعة</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div style="font-weight:700;color:var(--tx)" data-testid="kyc-current-status-label">{{ $kyc['label'] }}</div>
            <div style="font-size:13px;color:var(--td)">{{ $kyc['description'] }}</div>
            <div class="field-grid-compact">
                <div>
                    <div style="font-size:12px;color:var(--tm)">نوع التحقق</div>
                    <div style="color:var(--tx)">{{ $kyc['verification_type'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">مستوى التحقق</div>
                    <div style="color:var(--tx)">{{ $kyc['verification_level'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">تاريخ الإرسال</div>
                    <div style="color:var(--tx)">{{ $kyc['submitted_at'] ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">تاريخ المراجعة</div>
                    <div style="color:var(--tx)">{{ $kyc['reviewed_at'] ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">المراجع</div>
                    <div style="color:var(--tx)">{{ $kyc['reviewer_name'] ?? 'غير محدد' }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">انتهاء الصلاحية</div>
                    <div style="color:var(--tx)">{{ $kyc['expires_at'] ?? '—' }}</div>
                </div>
            </div>
            @if($kyc['rejection_reason'])
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(248,113,113,.06);font-size:13px;color:var(--tx)" data-testid="kyc-rejection-reason">
                    سبب الرفض الحالي: {{ $kyc['rejection_reason'] }}
                </div>
            @endif
            @if($kyc['review_notes_excerpt'])
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03);font-size:13px;color:var(--tx)">
                    ملخص ملاحظات المراجعة: {{ $kyc['review_notes_excerpt'] }}
                </div>
            @endif
        </div>
    </section>
</div>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <section class="card" data-testid="kyc-documents-card">
        <div class="card-title">ملخص الوثائق</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:6px">الوثائق المطلوبة</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @forelse($kyc['required_documents'] as $document)
                        <span class="badge">{{ $document['label'] }}</span>
                    @empty
                        <span style="font-size:13px;color:var(--td)">لا توجد وثائق مطلوبة ظاهرة لهذا الملف.</span>
                    @endforelse
                </div>
            </div>

            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:6px">الوثائق المشار إلى استلامها</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @forelse($kyc['submitted_documents'] as $document)
                        <span class="badge">{{ $document['label'] }}</span>
                    @empty
                        <span style="font-size:13px;color:var(--td)">لا توجد وثائق مرسلة في الملخص الحالي.</span>
                    @endforelse
                </div>
            </div>

            @if($hasDocumentVisibility)
                <div style="display:flex;flex-direction:column;gap:10px">
                    @forelse($documents as $document)
                        <div data-testid="kyc-document-item" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                            <div style="font-weight:700;color:var(--tx)">{{ $document['type_label'] }}</div>
                            <div style="font-size:13px;color:var(--td)">{{ $document['filename'] }}</div>
                            <div style="font-size:12px;color:var(--td)">{{ $document['mime_type'] }} • {{ $document['human_size'] }} • {{ $document['uploaded_at'] ?? '—' }}</div>
                            <div style="font-size:12px;color:var(--tm);margin-top:6px">{{ $document['sensitivity_label'] }} • بواسطة {{ $document['uploaded_by'] }}</div>
                        </div>
                    @empty
                        <div class="empty-state">لا توجد سجلات وثائق آمنة قابلة للعرض ضمن هذا الملف حتى الآن.</div>
                    @endforelse
                </div>
            @else
                <div class="empty-state">ملخص الوثائق التفصيلي غير ظاهر لهذا الدور، لكن حالة التحقق العامة ما زالت متاحة للقراءة.</div>
            @endif
        </div>
    </section>

    <section class="card" data-testid="kyc-restrictions-card">
        <div class="card-title">القيود وحدود الشحن</div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03)">
                <div style="font-weight:700;color:var(--tx)">{{ $shipmentLimitSummary['headline'] }}</div>
                <div style="font-size:13px;color:var(--td);margin-top:6px">{{ $shipmentLimitSummary['detail'] }}</div>
            </div>

            <div data-testid="kyc-operational-effects-card" style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                <div class="field-grid-compact">
                    <div data-testid="kyc-shipping-operability">
                        <div style="font-size:12px;color:var(--tm)">وضع الشحن الآن</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $operationalEffect['shipping_label'] }}</div>
                        <div style="font-size:13px;color:var(--td);margin-top:4px">{{ $operationalEffect['shipping_detail'] }}</div>
                    </div>
                    <div data-testid="kyc-international-shipping-state">
                        <div style="font-size:12px;color:var(--tm)">الشحن الدولي</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $operationalEffect['international_label'] }}</div>
                        <div style="font-size:13px;color:var(--td);margin-top:4px">{{ $operationalEffect['international_detail'] }}</div>
                    </div>
                    <div data-testid="kyc-next-action">
                        <div style="font-size:12px;color:var(--tm)">الإجراء المطلوب</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $operationalEffect['action_label'] }}</div>
                        <div style="font-size:13px;color:var(--td);margin-top:4px">{{ $operationalEffect['action_detail'] }}</div>
                    </div>
                </div>

                <div class="field-grid-compact" style="margin-top:12px">
                    <div data-testid="kyc-shipping-limit-value">
                        <div style="font-size:12px;color:var(--tm)">حد الشحن الكلي</div>
                        <div style="color:var(--tx)">{{ $operationalEffect['shipping_limit'] !== null ? number_format($operationalEffect['shipping_limit']) : 'غير محدد' }}</div>
                    </div>
                    <div data-testid="kyc-daily-shipment-limit-value">
                        <div style="font-size:12px;color:var(--tm)">الحد اليومي</div>
                        <div style="color:var(--tx)">{{ $operationalEffect['daily_shipment_limit'] !== null ? number_format($operationalEffect['daily_shipment_limit']) : 'غير محدد' }}</div>
                    </div>
                    <div data-testid="kyc-blocked-shipments-count">
                        <div style="font-size:12px;color:var(--tm)">شحنات محجوبة حاليًا</div>
                        <div style="color:var(--tx)">{{ number_format($operationalEffect['blocked_shipments_count']) }}</div>
                    </div>
                </div>
            </div>

            @if($recentImpactedShipments->isNotEmpty())
                <div data-testid="kyc-impacted-shipments-card" style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03)">
                    <div style="font-weight:700;color:var(--tx);margin-bottom:8px">أحدث الشحنات المتأثرة</div>
                    <div style="display:flex;flex-direction:column;gap:8px">
                        @foreach($recentImpactedShipments as $shipment)
                            <div style="font-size:13px;color:var(--td)">
                                <span style="font-weight:700;color:var(--tx)">{{ $shipment['reference'] }}</span>
                                <span>•</span>
                                <span>{{ $shipment['status'] }}</span>
                                <span>•</span>
                                <span>{{ $shipment['created_at'] ?? '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @forelse($restrictions as $restriction)
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px">
                    <div style="font-weight:700;color:var(--tx)">{{ $restriction['name'] }}</div>
                    <div style="font-size:13px;color:var(--td);margin-top:4px">{{ $restriction['description'] }}</div>
                    <div style="font-size:12px;color:var(--tm);margin-top:6px">{{ $restriction['summary'] }}</div>
                </div>
            @empty
                <div class="empty-state">لا توجد قيود تحقق نشطة على هذا الحساب حاليًا.</div>
            @endforelse
        </div>
    </section>
</div>

@if($canManageRestrictions)
    <section class="card" data-testid="kyc-restriction-management-card" style="margin-bottom:24px">
        <div class="card-title">إدارة قيود التحقق التشغيلية</div>
        <p style="margin:0 0 14px;color:var(--td);font-size:14px">
            تتحكم هذه النماذج في سجلات القيود المطبقة على حالة التحقق الحالية فقط. لا تغير هذه الواجهة محتوى الوثائق الخام ولا تعيد كتابة منطق الشحن العميق خارج نموذج القيود الحالي.
        </p>
        <div class="grid-main-sidebar-tight">
            @foreach($restrictionControls as $control)
                <section style="padding:14px;border:1px solid var(--bd);border-radius:14px;display:flex;flex-direction:column;gap:12px">
                    <div>
                        <div style="font-weight:700;color:var(--tx)">{{ $control['label'] }}</div>
                        <div style="font-size:13px;color:var(--td);margin-top:4px">{{ $control['description'] }}</div>
                        <div style="font-size:12px;color:var(--tm);margin-top:6px">{{ $control['current_summary'] }}</div>
                    </div>

                    @if($control['type'] === 'block_feature')
                        <form method="POST"
                              action="{{ route('internal.kyc.restrictions.sync', ['account' => $account, 'feature' => $control['feature_key']]) }}"
                              data-testid="kyc-{{ $control['feature_key'] }}-restriction-form"
                              style="display:flex;flex-direction:column;gap:10px">
                            @csrf
                            <label style="display:flex;flex-direction:column;gap:6px">
                                <span style="font-size:12px;color:var(--tm)">ملاحظة تدقيقية اختيارية</span>
                                <textarea name="note" rows="3" class="input" style="min-height:88px">{{ old('note') }}</textarea>
                            </label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap">
                                <button type="submit" name="mode" value="enable" class="btn btn-pr" data-testid="kyc-{{ $control['feature_key'] }}-enable-button">تفعيل القيد</button>
                                <button type="submit" name="mode" value="disable" class="btn btn-s" data-testid="kyc-{{ $control['feature_key'] }}-disable-button">تعطيل القيد</button>
                            </div>
                        </form>
                    @else
                        <form method="POST"
                              action="{{ route('internal.kyc.restrictions.sync', ['account' => $account, 'feature' => $control['feature_key']]) }}"
                              data-testid="kyc-{{ $control['feature_key'] }}-restriction-form"
                              style="display:flex;flex-direction:column;gap:10px">
                            @csrf
                            <label style="display:flex;flex-direction:column;gap:6px">
                                <span style="font-size:12px;color:var(--tm)">القيمة العددية</span>
                                <input type="number" min="1" name="quota_value" class="input" value="{{ old('quota_value', $control['quota_value']) }}">
                            </label>
                            <label style="display:flex;flex-direction:column;gap:6px">
                                <span style="font-size:12px;color:var(--tm)">ملاحظة تدقيقية اختيارية</span>
                                <textarea name="note" rows="3" class="input" style="min-height:88px">{{ old('note') }}</textarea>
                            </label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap">
                                <button type="submit" name="mode" value="set" class="btn btn-pr" data-testid="kyc-{{ $control['feature_key'] }}-save-button">حفظ القيد</button>
                                <button type="submit" name="mode" value="clear" class="btn btn-s" data-testid="kyc-{{ $control['feature_key'] }}-clear-button">إزالة القيد</button>
                            </div>
                        </form>
                    @endif
                </section>
            @endforeach
        </div>
    </section>
@endif

<section class="card" data-testid="kyc-audit-card">
    <div class="card-title">ملخص المراجعات والتدقيق</div>
    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse($auditEntries as $entry)
            <div style="padding-bottom:12px;border-bottom:1px solid var(--bd)">
                <div style="font-weight:700;color:var(--tx)">{{ $entry['action'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $entry['actor'] }} • {{ $entry['at'] ?? '—' }}</div>
                <div style="font-size:12px;color:var(--tm)">الشدة: {{ $entry['severity'] }}</div>
            </div>
        @empty
            <div class="empty-state">لا توجد سجلات تدقيق KYC ظاهرة لهذا الحساب بعد.</div>
        @endforelse
    </div>
</section>
@endsection
