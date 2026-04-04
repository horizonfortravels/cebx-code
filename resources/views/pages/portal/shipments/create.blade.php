@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | إنشاء طلب شحنة')

@section('content')
@php
    $addressFormDefaults = $addressFormDefaults ?? [];
    $cloneFormDefaults = $cloneFormDefaults ?? [];
    $cloneSourceShipment = $cloneSourceShipment ?? null;
    $cloneDropsAdditionalParcels = $cloneDropsAdditionalParcels ?? false;
    $selectedSenderAddress = $selectedSenderAddress ?? null;
    $selectedRecipientAddress = $selectedRecipientAddress ?? null;
    $senderAddresses = $senderAddresses ?? collect();
    $recipientAddresses = $recipientAddresses ?? collect();
    $canViewAddressBook = $canViewAddressBook ?? false;
    $canManageAddressBook = $canManageAddressBook ?? false;
    $prefill = static function (string $key, mixed $fallback = null) use ($addressFormDefaults, $cloneFormDefaults, $draftShipment) {
        return data_get($addressFormDefaults, $key, data_get($cloneFormDefaults, $key, data_get($draftShipment, $key, $fallback)));
    };
    $firstParcel = old('parcels.0', [
        'weight' => data_get($cloneFormDefaults, 'parcels.0.weight', data_get($draftShipment, 'parcels.0.weight', '1.0')),
        'length' => data_get($cloneFormDefaults, 'parcels.0.length', data_get($draftShipment, 'parcels.0.length')),
        'width' => data_get($cloneFormDefaults, 'parcels.0.width', data_get($draftShipment, 'parcels.0.width')),
        'height' => data_get($cloneFormDefaults, 'parcels.0.height', data_get($draftShipment, 'parcels.0.height')),
    ]);
    $addressSelectionBaseQuery = collect([
        'draft' => request()->query('draft'),
        'clone' => request()->query('clone'),
    ])->filter(fn ($value) => filled($value))->all();

    $workflowBadges = [
        'draft' => ['label' => 'مسودة', 'color' => '#475569'],
        'validated' => ['label' => 'تم التحقق من البيانات', 'color' => '#2563eb'],
        'kyc_blocked' => ['label' => 'موقوف بسبب التحقق أو القيود', 'color' => '#b91c1c'],
        'ready_for_rates' => ['label' => 'جاهز للانتقال لاحقًا إلى التسعير', 'color' => '#047857'],
        'rated' => ['label' => 'تم تجهيز العروض', 'color' => '#7c3aed'],
        'offer_selected' => ['label' => 'تم تثبيت العرض', 'color' => '#ea580c'],
        'declaration_required' => ['label' => 'إقرار المحتوى مطلوب', 'color' => '#ea580c'],
        'declaration_complete' => ['label' => 'اكتمل إقرار المحتوى', 'color' => '#0f766e'],
        'requires_action' => ['label' => 'تتطلب هذه الشحنة إجراءً إضافيًا', 'color' => '#b91c1c'],
    ];

    $currentBadge = $workflowBadges[$workflowState] ?? ['label' => $workflowState, 'color' => '#334155'];
    $canContinueToOffers = $draftShipment && in_array($draftShipment->status, ['ready_for_rates', 'rated', 'offer_selected', 'declaration_required', 'declaration_complete', 'requires_action'], true);
    $selectedSenderAddressId = (string) old('sender_address_id', $prefill('sender_address_id'));
    $selectedRecipientAddressId = (string) old('recipient_address_id', $prefill('recipient_address_id'));
    $addressValidationResults = session('address_validation_results', []);
    $senderValidation = data_get($addressValidationResults, 'sender');
    $recipientValidation = data_get($addressValidationResults, 'recipient');
    $validationFieldLabels = [
        'address_1' => __('portal_shipments.address_validation.fields.address_1'),
        'address_2' => __('portal_shipments.address_validation.fields.address_2'),
        'city' => __('portal_shipments.address_validation.fields.city'),
        'state' => __('portal_shipments.address_validation.fields.state'),
        'postal_code' => __('portal_shipments.address_validation.fields.postal_code'),
        'country' => __('portal_shipments.address_validation.fields.country'),
    ];
    $addressValidationStyle = static function (?array $result): array {
        return match (data_get($result, 'classification')) {
            'exact_validation_pass' => [
                'border' => 'rgba(4,120,87,.22)',
                'background' => 'rgba(4,120,87,.08)',
                'color' => '#065f46',
            ],
            'normalized_suggestion' => [
                'border' => 'rgba(37,99,235,.18)',
                'background' => 'rgba(37,99,235,.06)',
                'color' => '#1d4ed8',
            ],
            default => [
                'border' => 'rgba(234,88,12,.18)',
                'background' => 'rgba(234,88,12,.08)',
                'color' => '#9a3412',
            ],
        };
    };
    $validationProviderUnavailable = collect($addressValidationResults)->contains(
        fn ($result) => data_get($result, 'source') === 'provider_unavailable'
    );
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route($portalConfig['dashboard_route']) }}" style="color:inherit;text-decoration:none">{{ $portalConfig['label'] }}</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['index_route']) }}" style="color:inherit;text-decoration:none">الشحنات</a>
            <span style="margin:0 6px">/</span>
            <span>إنشاء طلب</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">{{ $portalConfig['headline'] }}</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:780px">{{ $portalConfig['description'] }}</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route($portalConfig['index_route']) }}" class="btn btn-s">العودة إلى صفحة الشحنات</a>
    </div>
</div>

@if(session('success'))
    <div style="margin-bottom:20px;padding:16px 18px;border-radius:16px;border:1px solid rgba(4,120,87,.22);background:rgba(4,120,87,.08);color:#065f46">
        <div style="font-weight:800;margin-bottom:4px">تم تجهيز المسودة بنجاح</div>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if($workflowFeedback)
    <div style="margin-bottom:20px;padding:18px;border-radius:18px;border:1px solid {{ ($workflowFeedback['level'] ?? 'warning') === 'success' ? 'rgba(4,120,87,.22)' : 'rgba(185,28,28,.18)' }};background:{{ ($workflowFeedback['level'] ?? 'warning') === 'success' ? 'rgba(4,120,87,.08)' : 'rgba(185,28,28,.06)' }}">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:6px">حالة التدفق الحالية</div>
                <div style="font-size:20px;font-weight:800;color:var(--tx)">{{ $workflowFeedback['message'] ?? 'تم تحديث حالة المسودة.' }}</div>
            </div>
            <div style="padding:8px 12px;border-radius:999px;background:{{ $currentBadge['color'] }};color:white;font-size:12px;font-weight:700">
                {{ $currentBadge['label'] }}
            </div>
        </div>

        @if(!empty($workflowFeedback['next_action']))
            <div style="margin-top:12px;color:var(--td);font-size:14px">
                <strong style="color:var(--tx)">الخطوة التالية:</strong>
                {{ $workflowFeedback['next_action'] }}
            </div>
        @endif

        @if(!empty($workflowFeedback['validation_errors']))
            <div style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--bd)">
                <div style="font-weight:700;color:var(--tx);margin-bottom:8px">أخطاء قابلة للتصحيح قبل التسعير</div>
                @foreach($workflowFeedback['validation_errors'] as $section => $messages)
                    <div style="margin-bottom:10px">
                        <div style="font-size:13px;font-weight:700;color:var(--tx)">{{ ucfirst($section) }}</div>
                        <ul style="margin:6px 0 0;padding-right:18px;color:var(--td);font-size:13px">
                            @foreach($messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif

        @if(!empty($workflowFeedback['kyc_status']) || !empty($workflowFeedback['reason_code']))
            <div style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--bd);display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                @if(!empty($workflowFeedback['kyc_status']))
                    <div style="padding:12px;border:1px solid var(--bd);border-radius:14px;background:white">
                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة التحقق</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $workflowFeedback['kyc_status'] }}</div>
                    </div>
                @endif
                @if(!empty($workflowFeedback['reason_code']))
                    <div style="padding:12px;border:1px solid var(--bd);border-radius:14px;background:white">
                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">رمز السبب</div>
                        <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ $workflowFeedback['reason_code'] }}</div>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endif

@if($cloneSourceShipment)
    <div style="margin-bottom:20px;padding:18px;border-radius:18px;border:1px solid rgba(37,99,235,.18);background:rgba(37,99,235,.06)" data-testid="clone-prefill-banner">
        <div style="font-size:20px;font-weight:800;color:var(--tx)">
            {{ __('portal_shipments.clone.banner_title', ['reference' => $cloneSourceShipment->reference_number ?? $cloneSourceShipment->id]) }}
        </div>
        <div style="margin-top:8px;color:var(--td);font-size:14px">
            {{ __('portal_shipments.clone.banner_body') }}
        </div>
        @if($cloneDropsAdditionalParcels)
            <div style="margin-top:10px;color:var(--td);font-size:13px">
                {{ __('portal_shipments.clone.first_parcel_only') }}
            </div>
        @endif
    </div>
@endif

@if($canViewAddressBook)
    <div style="margin-bottom:20px;padding:18px;border-radius:18px;border:1px solid rgba(15,23,42,.08);background:white" data-testid="saved-address-toolbar">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px">
            <div>
                <div style="font-size:18px;font-weight:800;color:var(--tx)">{{ __('portal_addresses.common.address_book') }}</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px;max-width:720px">{{ __('portal_addresses.common.picker_help') }}</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route($portalConfig['addresses_index_route']) }}" class="btn btn-s">{{ __('portal_addresses.common.manage_link') }}</a>
                @if($canManageAddressBook)
                    <a href="{{ route($portalConfig['addresses_create_route']) }}" class="btn btn-s">{{ __('portal_addresses.common.new_address_cta') }}</a>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route($portalConfig['create_route']) }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:end">
            @foreach($addressSelectionBaseQuery as $queryKey => $queryValue)
                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
            @endforeach

            <label>
                <span class="f-label">{{ __('portal_addresses.common.sender_picker') }}</span>
                <select class="f-input" name="sender_address" data-testid="sender-address-picker">
                    <option value="">{{ __('portal_addresses.common.picker_placeholder') }}</option>
                    @foreach($senderAddresses as $address)
                        <option value="{{ $address->id }}" @selected($selectedSenderAddressId === (string) $address->id)>
                            {{ $address->label ?: $address->contact_name }}{{ $address->city ? ' - ' . $address->city : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="f-label">{{ __('portal_addresses.common.recipient_picker') }}</span>
                <select class="f-input" name="recipient_address" data-testid="recipient-address-picker">
                    <option value="">{{ __('portal_addresses.common.picker_placeholder') }}</option>
                    @foreach($recipientAddresses as $address)
                        <option value="{{ $address->id }}" @selected($selectedRecipientAddressId === (string) $address->id)>
                            {{ $address->label ?: $address->contact_name }}{{ $address->city ? ' - ' . $address->city : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="submit" class="btn btn-s" data-testid="saved-address-apply">{{ __('portal_addresses.common.use_saved_cta') }}</button>
                @if(request()->filled('sender_address') || request()->filled('recipient_address'))
                    <a href="{{ route($portalConfig['create_route'], $addressSelectionBaseQuery) }}" class="btn btn-s" data-testid="saved-address-clear">{{ __('portal_addresses.common.clear_saved_cta') }}</a>
                @endif
            </div>
        </form>
    </div>
@endif

@if($validationProviderUnavailable)
    <div style="margin-bottom:20px;padding:16px 18px;border-radius:16px;border:1px solid rgba(15,23,42,.12);background:rgba(15,23,42,.04);color:var(--tx)" data-testid="address-validation-provider-warning">
        <div style="font-weight:800;margin-bottom:4px">{{ __('portal_shipments.address_validation.provider_title') }}</div>
        <div>{{ __('portal_shipments.address_validation.provider_message') }}</div>
    </div>
@endif

<div class="grid-2-1">
    <x-card title="بيانات طلب الشحنة">
        <form method="POST" action="{{ route($portalConfig['store_route']) }}" style="display:flex;flex-direction:column;gap:18px">
            @csrf
            @if(request()->filled('draft'))
                <input type="hidden" name="draft" value="{{ request()->query('draft') }}">
            @endif
            @if(request()->filled('clone'))
                <input type="hidden" name="clone" value="{{ request()->query('clone') }}">
            @endif
            <input type="hidden" name="sender_address_id" value="{{ $selectedSenderAddressId }}">
            <input type="hidden" name="recipient_address_id" value="{{ $selectedRecipientAddressId }}">

            <div>
                @php($senderValidationStyle = $addressValidationStyle($senderValidation))
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:8px">
                    <div style="font-size:13px;font-weight:700;color:var(--tx)">المرسل</div>
                    <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="validate_sender" data-testid="validate-sender-address">
                        {{ __('portal_shipments.address_validation.sender_cta') }}
                    </button>
                </div>
                @if($selectedSenderAddress)
                    <div style="margin-bottom:12px;padding:12px 14px;border-radius:14px;border:1px solid rgba(37,99,235,.18);background:rgba(37,99,235,.06)" data-testid="selected-sender-address-banner">
                        <div style="font-weight:700;color:var(--tx)">{{ __('portal_addresses.common.selected_sender') }}</div>
                        <div style="color:var(--td);font-size:13px;margin-top:4px">{{ $selectedSenderAddress->label ?: $selectedSenderAddress->contact_name }}</div>
                    </div>
                @endif
                @if($senderValidation)
                    <div style="margin-bottom:12px;padding:14px;border-radius:16px;border:1px solid {{ $senderValidationStyle['border'] }};background:{{ $senderValidationStyle['background'] }}" data-testid="sender-address-validation">
                        <div style="font-weight:800;color:{{ $senderValidationStyle['color'] }}">{{ data_get($senderValidation, 'title') }}</div>
                        <div style="color:var(--td);font-size:13px;margin-top:4px">{{ data_get($senderValidation, 'message') }}</div>

                        @if(data_get($senderValidation, 'changes'))
                            <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
                                @foreach(data_get($senderValidation, 'changes', []) as $change)
                                    <div style="padding:10px 12px;border-radius:12px;background:white;border:1px solid rgba(15,23,42,.08)">
                                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">
                                            {{ data_get($validationFieldLabels, data_get($change, 'field_key'), data_get($change, 'field_key')) }}
                                        </div>
                                        <div style="font-size:13px;color:var(--td)">
                                            <strong style="color:var(--tx)">{{ data_get($change, 'suggested') }}</strong>
                                            @if(filled(data_get($change, 'original')))
                                                <span style="margin:0 6px">/</span>
                                                <span>{{ data_get($change, 'original') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(data_get($senderValidation, 'warnings'))
                            <ul style="margin:10px 0 0;padding-right:18px;color:var(--td);font-size:13px">
                                @foreach(data_get($senderValidation, 'warnings', []) as $warning)
                                    <li>{{ data_get($warning, 'message') }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if(data_get($senderValidation, 'classification') === 'normalized_suggestion')
                            @foreach(data_get($senderValidation, 'normalized', []) as $field => $value)
                                <input type="hidden" name="address_validation_suggestions[sender][{{ $field }}]" value="{{ $value ?? '' }}">
                            @endforeach
                            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                                <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="apply_sender" data-testid="apply-sender-address-suggestion">
                                    {{ __('portal_shipments.address_validation.accept') }}
                                </button>
                                <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="dismiss_sender" data-testid="dismiss-sender-address-suggestion">
                                    {{ __('portal_shipments.address_validation.keep_original') }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                    <label>
                        <span class="f-label">الاسم</span>
                        <input class="f-input" name="sender_name" value="{{ old('sender_name', $prefill('sender_name')) }}" required>
                    </label>
                    <label>
                        <span class="f-label">المنشأة</span>
                        <input class="f-input" name="sender_company" value="{{ old('sender_company', $prefill('sender_company')) }}">
                    </label>
                    <label>
                        <span class="f-label">الهاتف</span>
                        <input class="f-input" name="sender_phone" value="{{ old('sender_phone', $prefill('sender_phone')) }}" required>
                    </label>
                    <label>
                        <span class="f-label">البريد الإلكتروني</span>
                        <input class="f-input" type="email" name="sender_email" value="{{ old('sender_email', $prefill('sender_email')) }}">
                    </label>
                    <label style="grid-column:1/-1">
                        <span class="f-label">العنوان</span>
                        <input class="f-input" name="sender_address_1" value="{{ old('sender_address_1', $prefill('sender_address_1')) }}" required>
                    </label>
                    <label style="grid-column:1/-1">
                        <span class="f-label">العنوان الإضافي</span>
                        <input class="f-input" name="sender_address_2" value="{{ old('sender_address_2', $prefill('sender_address_2')) }}">
                    </label>
                    <label>
                        <span class="f-label">المدينة</span>
                        <input class="f-input" name="sender_city" value="{{ old('sender_city', $prefill('sender_city')) }}" required>
                    </label>
                    <label>
                        <span class="f-label">الولاية / المقاطعة</span>
                        <input class="f-input" name="sender_state" value="{{ old('sender_state', $prefill('sender_state')) }}" placeholder="مثل NY أو TX">
                    </label>
                    <label>
                        <span class="f-label">الرمز البريدي</span>
                        <input class="f-input" name="sender_postal_code" value="{{ old('sender_postal_code', $prefill('sender_postal_code')) }}">
                    </label>
                    <label>
                        <span class="f-label">الدولة (ISO-2)</span>
                        <input class="f-input" name="sender_country" maxlength="2" value="{{ old('sender_country', $prefill('sender_country', 'SA')) }}" required>
                    </label>
                </div>
                <div style="margin-top:8px;font-size:12px;color:var(--tm)">
                    أدخل رمز الولاية أو المقاطعة عندما يكون عنوان المرسل داخل الولايات المتحدة.
                </div>
            </div>

            <div>
                @php($recipientValidationStyle = $addressValidationStyle($recipientValidation))
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:8px">
                    <div style="font-size:13px;font-weight:700;color:var(--tx)">المستلم</div>
                    <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="validate_recipient" data-testid="validate-recipient-address">
                        {{ __('portal_shipments.address_validation.recipient_cta') }}
                    </button>
                </div>
                @if($selectedRecipientAddress)
                    <div style="margin-bottom:12px;padding:12px 14px;border-radius:14px;border:1px solid rgba(37,99,235,.18);background:rgba(37,99,235,.06)" data-testid="selected-recipient-address-banner">
                        <div style="font-weight:700;color:var(--tx)">{{ __('portal_addresses.common.selected_recipient') }}</div>
                        <div style="color:var(--td);font-size:13px;margin-top:4px">{{ $selectedRecipientAddress->label ?: $selectedRecipientAddress->contact_name }}</div>
                    </div>
                @endif
                @if($recipientValidation)
                    <div style="margin-bottom:12px;padding:14px;border-radius:16px;border:1px solid {{ $recipientValidationStyle['border'] }};background:{{ $recipientValidationStyle['background'] }}" data-testid="recipient-address-validation">
                        <div style="font-weight:800;color:{{ $recipientValidationStyle['color'] }}">{{ data_get($recipientValidation, 'title') }}</div>
                        <div style="color:var(--td);font-size:13px;margin-top:4px">{{ data_get($recipientValidation, 'message') }}</div>

                        @if(data_get($recipientValidation, 'changes'))
                            <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
                                @foreach(data_get($recipientValidation, 'changes', []) as $change)
                                    <div style="padding:10px 12px;border-radius:12px;background:white;border:1px solid rgba(15,23,42,.08)">
                                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">
                                            {{ data_get($validationFieldLabels, data_get($change, 'field_key'), data_get($change, 'field_key')) }}
                                        </div>
                                        <div style="font-size:13px;color:var(--td)">
                                            <strong style="color:var(--tx)">{{ data_get($change, 'suggested') }}</strong>
                                            @if(filled(data_get($change, 'original')))
                                                <span style="margin:0 6px">/</span>
                                                <span>{{ data_get($change, 'original') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(data_get($recipientValidation, 'warnings'))
                            <ul style="margin:10px 0 0;padding-right:18px;color:var(--td);font-size:13px">
                                @foreach(data_get($recipientValidation, 'warnings', []) as $warning)
                                    <li>{{ data_get($warning, 'message') }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if(data_get($recipientValidation, 'classification') === 'normalized_suggestion')
                            @foreach(data_get($recipientValidation, 'normalized', []) as $field => $value)
                                <input type="hidden" name="address_validation_suggestions[recipient][{{ $field }}]" value="{{ $value ?? '' }}">
                            @endforeach
                            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                                <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="apply_recipient" data-testid="apply-recipient-address-suggestion">
                                    {{ __('portal_shipments.address_validation.accept') }}
                                </button>
                                <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="dismiss_recipient" data-testid="dismiss-recipient-address-suggestion">
                                    {{ __('portal_shipments.address_validation.keep_original') }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                    <label>
                        <span class="f-label">الاسم</span>
                        <input class="f-input" name="recipient_name" value="{{ old('recipient_name', $prefill('recipient_name')) }}" required>
                    </label>
                    <label>
                        <span class="f-label">المنشأة</span>
                        <input class="f-input" name="recipient_company" value="{{ old('recipient_company', $prefill('recipient_company')) }}">
                    </label>
                    <label>
                        <span class="f-label">الهاتف</span>
                        <input class="f-input" name="recipient_phone" value="{{ old('recipient_phone', $prefill('recipient_phone')) }}" required>
                    </label>
                    <label>
                        <span class="f-label">البريد الإلكتروني</span>
                        <input class="f-input" type="email" name="recipient_email" value="{{ old('recipient_email', $prefill('recipient_email')) }}">
                    </label>
                    <label style="grid-column:1/-1">
                        <span class="f-label">العنوان</span>
                        <input class="f-input" name="recipient_address_1" value="{{ old('recipient_address_1', $prefill('recipient_address_1')) }}" required>
                    </label>
                    <label style="grid-column:1/-1">
                        <span class="f-label">العنوان الإضافي</span>
                        <input class="f-input" name="recipient_address_2" value="{{ old('recipient_address_2', $prefill('recipient_address_2')) }}">
                    </label>
                    <label>
                        <span class="f-label">المدينة</span>
                        <input class="f-input" name="recipient_city" value="{{ old('recipient_city', $prefill('recipient_city')) }}" required>
                    </label>
                    <label>
                        <span class="f-label">الولاية / المقاطعة</span>
                        <input class="f-input" name="recipient_state" value="{{ old('recipient_state', $prefill('recipient_state')) }}" placeholder="مثل NY أو CA">
                    </label>
                    <label>
                        <span class="f-label">الرمز البريدي</span>
                        <input class="f-input" name="recipient_postal_code" value="{{ old('recipient_postal_code', $prefill('recipient_postal_code')) }}">
                    </label>
                    <label>
                        <span class="f-label">الدولة (ISO-2)</span>
                        <input class="f-input" name="recipient_country" maxlength="2" value="{{ old('recipient_country', $prefill('recipient_country', 'SA')) }}" required>
                    </label>
                </div>
                <div style="margin-top:8px;font-size:12px;color:var(--tm)">
                    أدخل رمز الولاية أو المقاطعة عندما يكون عنوان المستلم داخل الولايات المتحدة.
                </div>
            </div>

            <div>
                <div style="font-size:13px;font-weight:700;color:var(--tx);margin-bottom:8px">الطرد الأول</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
                    <label>
                        <span class="f-label">الوزن</span>
                        <input class="f-input" type="number" step="0.01" min="0.01" name="parcels[0][weight]" value="{{ data_get($firstParcel, 'weight', '1.0') }}" required>
                    </label>
                    <label>
                        <span class="f-label">الطول</span>
                        <input class="f-input" type="number" step="0.1" min="0.1" name="parcels[0][length]" value="{{ data_get($firstParcel, 'length') }}">
                    </label>
                    <label>
                        <span class="f-label">العرض</span>
                        <input class="f-input" type="number" step="0.1" min="0.1" name="parcels[0][width]" value="{{ data_get($firstParcel, 'width') }}">
                    </label>
                    <label>
                        <span class="f-label">الارتفاع</span>
                        <input class="f-input" type="number" step="0.1" min="0.1" name="parcels[0][height]" value="{{ data_get($firstParcel, 'height') }}">
                    </label>
                </div>
            </div>

            @if ($errors->any())
                <div style="padding:14px;border-radius:14px;background:rgba(185,28,28,.06);border:1px solid rgba(185,28,28,.18)">
                    <div style="font-weight:700;color:#991b1b;margin-bottom:6px">تعذر حفظ الطلب</div>
                    <ul style="margin:0;padding-right:18px;color:#7f1d1d;font-size:13px">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="submit" class="btn btn-pr">حفظ المسودة وتشغيل التحقق</button>
                <a href="{{ route($portalConfig['index_route']) }}" class="btn btn-s">إلغاء</a>
            </div>
        </form>
    </x-card>

    <div style="display:flex;flex-direction:column;gap:18px">
        <x-card title="مراحل هذا التدفق">
            <div style="display:flex;flex-direction:column;gap:12px">
                @foreach([
                    'draft' => '1. إنشاء مسودة الشحنة',
                    'validated' => '2. التحقق من اكتمال البيانات',
                    'kyc_blocked' => '3. إيقاف الطلب عند وجود قيد تحقق أو استخدام',
                    'ready_for_rates' => '4. جاهز للانتقال لاحقًا إلى التسعير',
                    'rated' => '5. تمت تهيئة عروض الشحن للمقارنة',
                    'offer_selected' => '6. تم اختيار عرض واحد ومتابعة التدفق',
                    'declaration_required' => '7. يجب إكمال إقرار المحتوى قبل أي متابعة لاحقة',
                    'declaration_complete' => '8. اكتمل إقرار المحتوى وأصبحت الشحنة جاهزة للمرحلة اللاحقة',
                    'requires_action' => '9. تم تعليق الشحنة وتحتاج إلى متابعة يدوية',
                ] as $stateKey => $label)
                    <div style="padding:12px 14px;border-radius:14px;border:1px solid var(--bd);background:{{ $workflowState === $stateKey ? 'rgba(37,99,235,.08)' : 'white' }}">
                        <div style="font-weight:700;color:var(--tx)">{{ $label }}</div>
                        @if($workflowState === $stateKey)
                            <div style="font-size:12px;color:var(--tm);margin-top:4px">الحالة الحالية</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>

        @if($draftShipment)
            <x-card title="ملخص آخر مسودة">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
                    <div>
                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">المرجع</div>
                        <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ $draftShipment->reference_number ?? $draftShipment->id }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الحالة</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $currentBadge['label'] }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الوجهة</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $draftShipment->recipient_city }} / {{ $draftShipment->recipient_country }}</div>
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--tm);margin-bottom:4px">عدد الطرود</div>
                        <div style="font-weight:700;color:var(--tx)">{{ $draftShipment->parcels_count ?? $draftShipment->parcels->count() }}</div>
                    </div>
                </div>
                @if($canContinueToOffers)
                    <div style="margin-top:16px;padding-top:16px;border-top:1px dashed var(--bd);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                        <a href="{{ route($portalConfig['offers_route'], ['id' => $draftShipment->id]) }}" class="btn btn-pr">مقارنة العروض المتاحة</a>
                        <div style="font-size:13px;color:var(--td)">
                            هذه الشحنة أصبحت جاهزة لعرض الأسعار. افتح صفحة العروض للمقارنة ثم اختر عرضًا واحدًا للمتابعة.
                        </div>
                    </div>
                @endif
            </x-card>
        @endif

        <x-card title="آخر المسودات في الحساب">
            <div style="overflow:auto">
                <table class="table">
                    <thead>
                    <tr>
                        <th>المرجع</th>
                        <th>الحالة</th>
                        <th>الوجهة</th>
                        <th>التاريخ</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentDrafts as $shipment)
                        <tr>
                            <td class="td-mono">{{ $shipment->reference_number ?? $shipment->id }}</td>
                            <td>{{ data_get($workflowBadges, $shipment->status . '.label', $shipment->status) }}</td>
                            <td>{{ $shipment->recipient_city ?? 'غير محددة' }}</td>
                            <td>{{ optional($shipment->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">لا توجد مسودات سابقة بعد.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>
@endsection
