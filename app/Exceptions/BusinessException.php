<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    protected string $errorCode;
    protected int $httpStatus;
    protected array $context;

    public function __construct(string $message, string $errorCode, int $httpStatus = 400, array $context = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * FIX P0-1: Alias لحل عدم التوافق مع Handler.php
     * Handler يستدعي getStatusCode() بينما الكلاس يعرّف getHttpStatus()
     */
    public function getStatusCode(): int
    {
        return $this->httpStatus;
    }

    /**
     * FIX P0-1: إضافة context للأخطاء
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        $response = [
            'success'    => false,
            'error_code' => $this->errorCode,
            'message'    => $this->getMessage(),
        ];

        // إضافة context إذا موجود
        if (!empty($this->context)) {
            $response['context'] = $this->context;
        }

        return response()->json($response, $this->httpStatus);
    }

    // ─── FIX P0-1: إضافة make() — يستخدمها CarrierService ──────

    /**
     * Factory method عام لإنشاء BusinessException مع context.
     * يُستخدم في CarrierService وغيرها.
     *
     * @param string $errorCode  كود الخطأ (مثل 'ERR_INVALID_STATE_FOR_CARRIER')
     * @param string $message    رسالة الخطأ
     * @param array  $context    بيانات إضافية اختيارية
     * @param int    $httpStatus HTTP status code (default 400)
     */
    public static function make(
        string $errorCode,
        string $message,
        array $context = [],
        int $httpStatus = 400
    ): self {
        return new self($message, $errorCode, $httpStatus, $context);
    }

    // ─── Named Constructors for FR-IAM error codes ────────────────

    public static function duplicateEmail(): self
    {
        return new self('البريد الإلكتروني مستخدم بالفعل في هذا الحساب.', 'ERR_DUPLICATE_EMAIL', 422);
    }

    public static function userNotFound(): self
    {
        return new self('المستخدم غير موجود.', 'ERR_USER_NOT_FOUND', 404);
    }

    public static function permissionDenied(): self
    {
        return new self('لا تملك صلاحية كافية.', 'ERR_PERMISSION', 403);
    }

    public static function accountUpgradeRequired(): self
    {
        return new self(
            'الحسابات الفردية تدعم مستخدمًا خارجيًا واحدًا فقط. اطلب الترقية إلى حساب منظمة لإدارة فريق.',
            'ERR_ACCOUNT_UPGRADE_REQUIRED',
            409
        );
    }

    public static function individualSingleUserOnly(): self
    {
        return new self(
            'الحساب الفردي يدعم مستخدمًا خارجيًا واحدًا فقط.',
            'ERR_INDIVIDUAL_SINGLE_USER_ONLY',
            409
        );
    }

    public static function cannotModifySelf(string $action): self
    {
        return new self("لا يمكنك {$action} حسابك الخاص.", 'ERR_SELF_MODIFICATION', 422);
    }

    public static function cannotModifyOwner(): self
    {
        return new self('لا يمكن تعديل حالة مالك الحساب.', 'ERR_OWNER_PROTECTED', 422);
    }

    public static function responsibilityTransferRequired(): self
    {
        return new self(
            'يجب نقل مسؤوليات هذا المستخدم أولاً قبل الحذف.',
            'ERR_RESPONSIBILITY_TRANSFER_REQUIRED',
            409
        );
    }

    // ─── FR-IAM-011: Invitation Error Codes ──────────────────────

    public static function invitationNotFound(): self
    {
        return new self('الدعوة غير موجودة.', 'ERR_INVITATION_NOT_FOUND', 404);
    }

    public static function invitationExpired(): self
    {
        return new self('انتهت صلاحية الدعوة. يرجى طلب دعوة جديدة.', 'ERR_INVITATION_EXPIRED', 410);
    }

    public static function invitationRevoked(): self
    {
        return new self('تم إلغاء هذه الدعوة.', 'ERR_INVITATION_REVOKED', 410);
    }

    public static function invitationAlreadyAccepted(): self
    {
        return new self('تم قبول هذه الدعوة مسبقاً.', 'ERR_INVITATION_ALREADY_ACCEPTED', 409);
    }

    public static function invitationAlreadyExists(): self
    {
        return new self(
            'توجد دعوة نشطة بالفعل لهذا البريد الإلكتروني. يمكنك إعادة إرسالها أو إلغاؤها.',
            'ERR_INVITATION_ALREADY_EXISTS',
            409
        );
    }

    public static function invitationCannotResend(): self
    {
        return new self(
            'لا يمكن إعادة إرسال هذه الدعوة.',
            'ERR_INVITATION_CANNOT_RESEND',
            422
        );
    }

    // ─── FR-IAM-014/015/016: KYC ────────────────────────────────

    public static function kycRequired(): self
    {
        return new self('التحقق من الهوية مطلوب.', 'ERR_KYC_REQUIRED', 422);
    }

    public static function kycServiceUnavailable(): self
    {
        return new self('خدمة التحقق غير متاحة حالياً.', 'ERR_KYC_SERVICE_UNAVAILABLE', 503);
    }

    public static function documentNotFound(): self
    {
        return new self('الوثيقة غير موجودة.', 'ERR_DOCUMENT_NOT_FOUND', 404);
    }

    public static function documentPurged(): self
    {
        return new self('تم حذف محتوى هذه الوثيقة.', 'ERR_DOCUMENT_PURGED', 410);
    }

    public static function unauthorizedDocumentAccess(): self
    {
        return new self('لا تملك صلاحية الوصول لوثائق KYC.', 'ERR_UNAUTHORIZED_ACCESS', 403);
    }

    // ─── FR-IAM-009: Store Error Codes ───────────────────────────

    public static function storeExists(): self
    {
        return new self('يوجد متجر بنفس الاسم في هذا الحساب.', 'ERR_STORE_EXISTS', 422);
    }

    public static function maxStoresReached(): self
    {
        return new self('تم الوصول للحد الأقصى لعدد المتاجر.', 'ERR_MAX_STORES_REACHED', 422);
    }

    public static function cannotDeleteDefaultStore(): self
    {
        return new self('لا يمكن حذف المتجر الافتراضي.', 'ERR_CANNOT_DELETE_DEFAULT', 422);
    }

    // ─── FR-IAM-017/019/020: Wallet & Billing ────────────────────

    public static function invalidAmount(): self
    {
        return new self('المبلغ يجب أن يكون أكبر من صفر.', 'ERR_INVALID_AMOUNT', 422);
    }

    public static function walletFrozen(): self
    {
        return new self('المحفظة مجمدة أو مغلقة.', 'ERR_WALLET_FROZEN', 422);
    }

    /**
     * FIX P0-1: نسخة واحدة فقط — تم إزالة التكرار
     */
    public static function insufficientBalance(): self
    {
        return new self('رصيد المحفظة غير كافٍ.', 'ERR_INSUFFICIENT_BALANCE', 422);
    }

    public static function accountDisabled(): self
    {
        return new self('لا يمكن تنفيذ العملية لحساب معطل.', 'ERR_ACCOUNT_DISABLED', 422);
    }

    public static function dataRestoreFailed(): self
    {
        return new self('فشل في استرجاع البيانات.', 'ERR_DATA_RESTORE_FAIL', 500);
    }

    // ─── ST Module: Orders ───────────────────────────────────────

    public static function duplicateOrder(): self
    {
        return new self('طلب بنفس المعرف موجود بالفعل.', 'ERR_DUPLICATE_ORDER', 422);
    }

    public static function orderAlreadyShipped(): self
    {
        return new self('لا يمكن إلغاء طلب تم شحنه.', 'ERR_ORDER_ALREADY_SHIPPED', 422);
    }

    public static function invalidStatusTransition(): self
    {
        return new self('تغيير الحالة غير مسموح.', 'ERR_INVALID_STATUS_TRANSITION', 422);
    }

    public static function syncNotSupported(): self
    {
        return new self('المتجر لا يدعم المزامنة.', 'ERR_SYNC_NOT_SUPPORTED', 422);
    }

    public static function missingRequiredFields(): self
    {
        return new self('حقول مطلوبة مفقودة.', 'ERR_MISSING_REQUIRED_FIELDS', 422);
    }

    // ── SH Module Error Codes ──────────────────────────────────────

    public static function orderNotShippable(): self
    {
        return new self('الطلب غير جاهز للشحن.', 'ERR_ORDER_NOT_SHIPPABLE', 422);
    }

    public static function orderHasShipment(): self
    {
        return new self('الطلب مرتبط بشحنة بالفعل.', 'ERR_ORDER_HAS_SHIPMENT', 422);
    }

    public static function shipmentNotCancellable(): self
    {
        return new self('لا يمكن إلغاء الشحنة في حالتها الحالية.', 'ERR_SHIPMENT_NOT_CANCELLABLE', 422);
    }

    public static function noLabel(): self
    {
        return new self('لا يوجد ملصق لهذه الشحنة.', 'ERR_NO_LABEL', 422);
    }

    public static function invalidState(): self
    {
        return new self('حالة الشحنة غير صالحة لهذه العملية.', 'ERR_INVALID_STATE', 422);
    }

    public static function validationFailed(): self
    {
        return new self('فشل التحقق من بيانات الشحنة.', 'ERR_VALIDATION_FAILED', 422);
    }

    public static function returnNotAllowed(): self
    {
        return new self('لا يمكن إنشاء مرتجع.', 'ERR_RETURN_NOT_ALLOWED', 422);
    }

    public static function cannotModifyParcels(): self
    {
        return new self('لا يمكن تعديل الطرود بعد التسعير.', 'ERR_CANNOT_MODIFY_PARCELS', 422);
    }

    public static function lastParcel(): self
    {
        return new self('لا يمكن حذف آخر طرد.', 'ERR_LAST_PARCEL', 422);
    }

    // ── RT (Rates) Module Error Codes ──────────────────────────────

    public static function noRatesAvailable(): self
    {
        return new self('لا توجد أسعار متاحة.', 'ERR_NO_RATES', 404);
    }

    public static function rateExpired(): self
    {
        return new self('انتهت صلاحية التسعير.', 'ERR_RATE_EXPIRED', 410);
    }

    public static function rateOptionNotFound(): self
    {
        return new self('خيار السعر غير موجود.', 'ERR_RATE_OPTION_NOT_FOUND', 404);
    }

    // ── CR (Carrier) Module Error Codes ────────────────────────────

    public static function carrierCreateFailed(): self
    {
        return new self('فشل إنشاء الشحنة لدى الناقل.', 'ERR_CARRIER_CREATE_FAILED', 502);
    }

    public static function carrierNotCreated(): self
    {
        return new self('لم يتم إنشاء الشحنة لدى الناقل بعد.', 'ERR_CARRIER_NOT_CREATED', 422);
    }

    public static function carrierNotCancellable(): self
    {
        return new self('لا يمكن إلغاء الشحنة لدى الناقل.', 'ERR_CARRIER_NOT_CANCELLABLE', 422);
    }

    public static function carrierCancelFailed(): self
    {
        return new self('فشل إلغاء الشحنة لدى الناقل.', 'ERR_CARRIER_CANCEL_FAILED', 502);
    }

    public static function labelRefetchFailed(): self
    {
        return new self('فشل إعادة جلب الملصق.', 'ERR_LABEL_REFETCH_FAILED', 502);
    }

    public static function documentNotAvailable(): self
    {
        return new self('المستند غير متاح للتنزيل.', 'ERR_DOCUMENT_NOT_AVAILABLE', 404);
    }

    public static function maxRetriesExceeded(): self
    {
        return new self('تم تجاوز الحد الأقصى لمحاولات الإعادة.', 'ERR_MAX_RETRIES_EXCEEDED', 422);
    }

    public static function invalidStateForCarrier(): self
    {
        return new self('حالة الشحنة غير مناسبة لإنشائها لدى الناقل.', 'ERR_INVALID_STATE_FOR_CARRIER', 422);
    }
}
