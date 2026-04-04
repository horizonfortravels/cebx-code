@extends('layouts.app')
@section('title', 'تفاصيل الحساب')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <div class="header-main">
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.accounts.index') }}" style="color:inherit;text-decoration:none">حسابات العملاء</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $account->name }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">ملخص الحساب</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            هذه الواجهة الداخلية تجمع ملخص الحساب مع إجراءات الدعم الآمنة المتاحة في هذه المرحلة، مثل إرسال رابط إعادة تعيين كلمة المرور وإعادة إرسال الدعوات الناضجة عندما تكون متاحة.
        </p>
    </div>
    <div class="header-actions">
        @if($canUpdateAccount)
            <a href="{{ route('internal.accounts.edit', $account) }}" class="btn btn-pr">تحرير الحساب</a>
        @endif
        @if($canCreateTickets)
            <a href="{{ route('internal.accounts.tickets.create', $account) }}" class="btn btn-s" data-testid="account-create-linked-ticket-link">Create linked ticket</a>
        @endif
        <a href="{{ route('internal.kyc.show', $account) }}" class="btn btn-s">KYC Center</a>
        <a href="{{ route('internal.accounts.index') }}" class="btn btn-s">العودة إلى القائمة</a>
        <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-pr">تحديث التفاصيل</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="ACC" label="نوع الحساب" :value="$typeLabel" />
    <x-stat-card icon="STS" label="الحالة" :value="$statusLabel" />
    <x-stat-card icon="USR" label="المستخدمون الخارجيون" :value="number_format($externalUsersCount)" />
    <x-stat-card icon="SHP" label="الشحنات" :value="number_format($shipmentsCount)" />
</div>

@if($canManageLifecycle)
    <x-card title="إدارة دورة الحياة" style="margin-bottom:24px">
        @if($availableLifecycleActions === [])
            <div class="empty-state">لا توجد إجراءات انتقال متاحة لهذه الحالة حاليًا.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:12px">
                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    @foreach($availableLifecycleActions as $action)
                        <form method="POST" action="{{ route('internal.accounts.' . $action['action'], $account) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            @csrf
                            <input type="text" name="note" class="input" placeholder="ملاحظة تدقيق اختيارية" style="min-width:220px">
                            <button type="submit" class="btn {{ $action['tone'] === 'danger' ? 'btn-s' : 'btn-pr' }}">{{ $action['label'] }}</button>
                        </form>
                    @endforeach
                </div>
                <div style="font-size:12px;color:var(--td)">كل تغيير حالة يُكتب في سجل التدقيق الداخلي ويعيد تقييم وصول العميل الخارجي.</div>
            </div>
        @endif
    </x-card>
@endif

@if($canManageSupportActions)
    <x-card title="إجراءات الدعم" style="margin-bottom:24px" data-testid="account-support-actions-card">
        <div style="display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;flex-direction:column;gap:10px">
                <div style="font-size:12px;color:var(--tm)">المستخدم المستهدف لإعادة التعيين</div>
                @if($passwordResetTarget)
                    <div style="font-weight:700;color:var(--tx)">{{ $passwordResetTarget->name }}</div>
                    <div style="font-size:13px;color:var(--td)">{{ $passwordResetTarget->email }}</div>
                    <form method="POST" action="{{ route('internal.accounts.password-reset', $account) }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap" data-testid="account-password-reset-form">
                        @csrf
                        <button type="submit" class="btn btn-pr" data-testid="account-password-reset-button">إرسال رابط إعادة تعيين كلمة المرور</button>
                        <span style="font-size:12px;color:var(--td)">تُستخدم آلية Laravel الحالية دون كشف أي رمز أو كلمة مرور.</span>
                    </form>
                @else
                    <div class="empty-state">لا يوجد مستخدم خارجي مناسب لإرسال رابط إعادة التعيين لهذا الحساب.</div>
                @endif
            </div>

            <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03);font-size:13px;color:var(--td)">
                حالة التحقق في هذه المرحلة للعرض فقط. لا يوجد مسار ناضج وآمن لإعادة إرسال التحقق من هذه الواجهة حتى الآن.
            </div>
        </div>
    </x-card>
@endif

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <x-card title="المالك الأساسي">
        @if($owner)
            <div style="display:flex;flex-direction:column;gap:10px">
                <div>
                    <div style="font-size:12px;color:var(--tm)">الاسم</div>
                    <div style="font-weight:700;color:var(--tx)">{{ $owner->name }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">البريد</div>
                    <div style="color:var(--tx)">{{ $owner->email }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">حالة المستخدم</div>
                    <div style="color:var(--tx)">{{ $owner->status ?? 'active' }}</div>
                </div>
            </div>
        @else
            <div class="empty-state">لا يوجد مستخدم خارجي واضح كمالك أساسي لهذا الحساب حتى الآن.</div>
        @endif
    </x-card>

    <x-card title="حالة التحقق" data-testid="account-verification-status-card">
        <div style="display:flex;flex-direction:column;gap:10px">
            <div>
                <div style="font-size:12px;color:var(--tm)">الحالة الحالية</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kyc['label'] }}</div>
            </div>
            <div style="color:var(--td);font-size:13px">{{ $kyc['description'] }}</div>
            <div class="field-grid-compact">
                <div>
                    <div style="font-size:12px;color:var(--tm)">تاريخ الإرسال</div>
                    <div style="color:var(--tx)">{{ $kyc['submitted_at'] ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">تاريخ المراجعة</div>
                    <div style="color:var(--tx)">{{ $kyc['reviewed_at'] ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm)">الانتهاء</div>
                    <div style="color:var(--tx)">{{ $kyc['expires_at'] ?? '—' }}</div>
                </div>
            </div>
            @if($kyc['rejection_reason'])
                <div style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(248,113,113,.06);font-size:13px;color:var(--tx)">
                    سبب الرفض الحالي: {{ $kyc['rejection_reason'] }}
                </div>
            @endif
            @if(!$verificationResendAvailable)
                <div style="font-size:12px;color:var(--td)">هذه البطاقة تعرض الحالة الحالية فقط. إعادة إرسال التحقق ليست جزءًا من هذه المرحلة.</div>
            @endif
        </div>
    </x-card>
</div>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <x-card title="الأثر التشغيلي للتحقق" data-testid="account-kyc-operational-effect-card">
        <div style="display:flex;flex-direction:column;gap:12px">
            <div data-testid="account-kyc-shipping-operability">
                <div style="font-size:12px;color:var(--tm)">وضع الشحن الآن</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycOperationalEffect['shipping_label'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $kycOperationalEffect['shipping_detail'] }}</div>
            </div>
            <div data-testid="account-kyc-international-shipping-state">
                <div style="font-size:12px;color:var(--tm)">الشحن الدولي</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycOperationalEffect['international_label'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $kycOperationalEffect['international_detail'] }}</div>
            </div>
            <div data-testid="account-kyc-next-action">
                <div style="font-size:12px;color:var(--tm)">الحاجة إلى إجراء إضافي</div>
                <div style="font-weight:700;color:var(--tx)">{{ $kycOperationalEffect['action_label'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $kycOperationalEffect['action_detail'] }}</div>
            </div>
            <div class="field-grid-compact">
                <div data-testid="account-kyc-shipping-limit-value">
                    <div style="font-size:12px;color:var(--tm)">حد الشحن الكلي</div>
                    <div style="color:var(--tx)">{{ $kycOperationalEffect['shipping_limit'] !== null ? number_format($kycOperationalEffect['shipping_limit']) : 'غير محدد' }}</div>
                </div>
                <div data-testid="account-kyc-daily-shipment-limit-value">
                    <div style="font-size:12px;color:var(--tm)">الحد اليومي</div>
                    <div style="color:var(--tx)">{{ $kycOperationalEffect['daily_shipment_limit'] !== null ? number_format($kycOperationalEffect['daily_shipment_limit']) : 'غير محدد' }}</div>
                </div>
                <div data-testid="account-kyc-blocked-shipments-count">
                    <div style="font-size:12px;color:var(--tm)">شحنات محجوبة حاليًا</div>
                    <div style="color:var(--tx)">{{ number_format($kycBlockedShipmentsCount) }}</div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="font-size:12px;color:var(--td)">يعرض هذا الملخص الأثر التشغيلي الحقيقي لحالة KYC الحالية والشحنات المحجوبة فعليًا.</div>
                <a href="{{ route('internal.kyc.show', $account) }}" class="btn btn-pr">فتح ملف KYC</a>
            </div>
            @if($recentKycImpactedShipments->isNotEmpty())
                <div data-testid="account-kyc-impacted-shipments-card" style="padding:12px;border:1px solid var(--bd);border-radius:12px;background:rgba(15,23,42,.03)">
                    <div style="font-weight:700;color:var(--tx);margin-bottom:8px">أحدث الشحنات المتأثرة</div>
                    <div style="display:flex;flex-direction:column;gap:8px">
                        @foreach($recentKycImpactedShipments as $shipment)
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
        </div>
    </x-card>
</div>

<div class="grid-main-sidebar-tight" style="margin-bottom:24px">
    <x-card title="القيود الحالية">
        @forelse($restrictions as $restriction)
            <div style="padding-bottom:12px;margin-bottom:12px;border-bottom:1px solid var(--bd)">
                <div style="font-weight:700;color:var(--tx)">{{ $restriction->name }}</div>
                <div style="font-size:12px;color:var(--td)">{{ $restriction->description ?: $restriction->restriction_key }}</div>
            </div>
        @empty
            <div class="empty-state">لا توجد قيود تحقق نشطة لهذا الحساب حاليًا.</div>
        @endforelse
    </x-card>

    <x-card title="المحفظة">
        <div style="display:flex;flex-direction:column;gap:10px">
            <div>
                <div style="font-size:12px;color:var(--tm)">الرصيد</div>
                <div style="font-weight:700;color:var(--tx)">{{ $wallet['headline'] }}</div>
            </div>
            <div style="font-size:13px;color:var(--td)">{{ $wallet['meta'] }}</div>
            <div>
                <div style="font-size:12px;color:var(--tm)">حالة المحفظة</div>
                <div style="color:var(--tx)">{{ $wallet['status'] ?? 'غير متاح' }}</div>
            </div>
        </div>
    </x-card>
</div>

@if($account->isOrganization())
    <div class="grid-main-sidebar-tight" style="margin-bottom:24px">
        <x-card title="ملخص المؤسسة">
            @if($organizationProfile)
                <div class="field-grid-compact">
                    <div>
                        <div style="font-size:12px;color:var(--tm)">الاسم القانوني</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $organizationProfile->legal_name }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm)">الاسم التجاري</div>
                        <div style="color:var(--tx)">{{ $organizationProfile->trade_name ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm)">السجل التجاري</div>
                        <div style="color:var(--tx)">{{ $organizationProfile->registration_number ?: '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm)">القطاع والحجم</div>
                        <div style="color:var(--tx)">{{ $organizationProfile->industry ?: '—' }} @if($organizationProfile->company_size) • {{ $organizationProfile->company_size }} @endif</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm)">الموقع</div>
                        <div style="color:var(--tx)">{{ $organizationProfile->country ?: '—' }} @if($organizationProfile->city) • {{ $organizationProfile->city }} @endif</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm)">بريد المؤسسة</div>
                        <div style="color:var(--tx)">{{ $organizationProfile->email ?: '—' }}</div>
                    </div>
                </div>
            @else
                <div class="empty-state">هذا حساب منظمة، لكن ملف المؤسسة لم يُستكمل بعد.</div>
            @endif
        </x-card>

        <x-card title="حدود هذه المرحلة">
            <div style="font-size:14px;color:var(--td);line-height:1.8">
                هذا حساب منظمة. في هذا النطاق يمكن عرض الأعضاء الحاليين والدعوات المعلقة دائمًا، ويمكن لـ super_admin إرسال دعوة عضو جديدة وتعطيل أو إعادة تفعيل الأعضاء غير المالكين، بينما يظل دور الدعم محصورًا في العرض وإعادة إرسال الدعوات الآمنة فقط.
            </div>
        </x-card>
    </div>

    <x-card title="أعضاء المنظمة" style="margin-bottom:24px" data-testid="organization-members-card">
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse($organizationMembers as $member)
                <div style="padding-bottom:12px;border-bottom:1px solid var(--bd)" data-testid="organization-member-row">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                        <div style="display:flex;flex-direction:column;gap:6px">
                            <div style="font-weight:700;color:var(--tx)">{{ $member['name'] }}</div>
                            <div style="font-size:13px;color:var(--td)">{{ $member['email'] }}</div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:12px;color:var(--td)">
                                <span>الحالة: {{ $member['status_label'] }}</span>
                                <span>•</span>
                                <span>الأدوار: {{ implode('، ', $member['role_labels']) }}</span>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            @if($member['is_owner'])
                                <span style="font-size:12px;color:var(--td)">مالك الحساب محمي</span>
                            @elseif($canManageMembers && $member['can_deactivate'])
                                <form method="POST" action="{{ route('internal.accounts.members.deactivate', [$account, $member['id']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-s" data-testid="organization-member-deactivate-button">تعطيل العضو</button>
                                </form>
                            @elseif($canManageMembers && $member['can_reactivate'])
                                <form method="POST" action="{{ route('internal.accounts.members.reactivate', [$account, $member['id']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-pr" data-testid="organization-member-reactivate-button">إعادة التفعيل</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">لا يوجد أعضاء منظمة ظاهرون لهذا الحساب حتى الآن.</div>
            @endforelse
        </div>
    </x-card>

    @if($canManageMembers)
        <x-card title="إضافة / دعوة عضو" style="margin-bottom:24px" data-testid="organization-member-invite-card">
            @if($memberRoleOptions->isNotEmpty())
                <form method="POST" action="{{ route('internal.accounts.members.invite', $account) }}" class="field-grid" data-testid="organization-member-invite-form">
                    @csrf
                    <div>
                        <label class="input-label">الاسم</label>
                        <input type="text" name="name" class="input" placeholder="اسم العضو" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label class="input-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="input" placeholder="member@example.test" value="{{ old('email') }}" required>
                    </div>
                    <div>
                        <label class="input-label">الدور</label>
                        <select name="role_id" class="input" data-testid="organization-member-role-select" required>
                            @foreach($memberRoleOptions as $roleOption)
                                <option value="{{ $roleOption['id'] }}" @selected(old('role_id') === $roleOption['id'])>{{ $roleOption['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="grid-column:1 / -1;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                        <div style="font-size:12px;color:var(--td)">تتم إضافة الأعضاء في هذا النطاق عبر دعوة آمنة فقط، ولا يتم إنشاء كلمات مرور أو عرض أسرار من الواجهة الداخلية.</div>
                        <button type="submit" class="btn btn-pr" data-testid="organization-member-invite-submit">إرسال الدعوة</button>
                    </div>
                </form>
            @else
                <div class="empty-state">لا توجد أدوار منظمة آمنة متاحة لإرسال دعوة عضو جديدة من هذا الحساب.</div>
            @endif
        </x-card>
    @endif

    @if($canManageSupportActions)
        <x-card title="الدعوات المعلقة" style="margin-bottom:24px" data-testid="organization-pending-invitations-card">
            <div style="display:flex;flex-direction:column;gap:12px">
                @forelse($pendingInvitations as $invitation)
                    <div style="padding-bottom:12px;border-bottom:1px solid var(--bd)" data-testid="pending-invitation-row">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                            <div style="display:flex;flex-direction:column;gap:6px">
                                <div style="font-weight:700;color:var(--tx)">{{ $invitation['email'] }}</div>
                                @if($invitation['name'])
                                    <div style="font-size:13px;color:var(--td)">{{ $invitation['name'] }}</div>
                                @endif
                                <div style="font-size:12px;color:var(--td)">
                                    الدور: {{ $invitation['role_label'] }} • آخر صلاحية: {{ $invitation['expires_at'] ?? '—' }} • مرات الإرسال: {{ number_format($invitation['send_count']) }}
                                </div>
                            </div>
                            @if($invitation['can_resend'])
                                <form method="POST" action="{{ route('internal.accounts.invitations.resend', [$account, $invitation['id']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-pr" data-testid="pending-invitation-resend-button">إعادة الإرسال</button>
                                </form>
                            @else
                                <div style="font-size:12px;color:var(--td)">هذه الدعوة ليست ضمن الحالات الآمنة لإعادة الإرسال.</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">لا توجد دعوات معلقة قابلة للمتابعة من هذا الحساب حاليًا.</div>
                @endforelse
            </div>
        </x-card>
    @endif
@else
    <x-card title="قاعدة الحساب الفردي" style="margin-bottom:24px">
        <div style="font-size:14px;color:var(--td);line-height:1.8">
            هذا حساب فردي، لذلك لا تظهر أي أدوات لدعوات أعضاء أو إعادة إرسال دعوات مؤسسية في هذه المرحلة.
        </div>
    </x-card>
@endif

<x-card title="آخر الشحنات">
    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse($recentShipments as $shipment)
            <div style="padding-bottom:12px;border-bottom:1px solid var(--bd)">
                <div style="font-weight:700;color:var(--tx)">{{ $shipment->tracking_number ?? $shipment->reference_number ?? $shipment->id }}</div>
                <div style="font-size:12px;color:var(--td)">{{ $shipment->status ?? '—' }} • {{ optional($shipment->created_at)->format('Y-m-d H:i') ?? '—' }}</div>
            </div>
        @empty
            <div class="empty-state">لا توجد شحنات حديثة لهذا الحساب حتى الآن.</div>
        @endforelse
    </div>
</x-card>
@endsection
