<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base Web Controller
 *
 * Portal detection is handled by DetectPortal middleware.
 * This class provides helpers for portal-aware queries.
 */
class WebController extends Controller
{
    /**
     * Get current portal type
     */
    protected function portalType(): string
    {
        return request()->attributes->get('portalType', 'b2b');
    }

    /**
     * Is current user in admin portal?
     */
    protected function isAdmin(): bool
    {
        return $this->portalType() === 'admin';
    }

    /**
     * Scope a query by account_id — Admin sees ALL, others see only own account
     */
    protected function scopeByAccount(Builder $query, ?int $accountId = null): Builder
    {
        if ($this->isAdmin()) {
            return $query; // Admin: NO filter — see all data
        }

        $id = $accountId ?? auth()->user()->account_id;
        return $query->where('account_id', $id);
    }

    /**
     * Get the account_id (or null for admin)
     */
    protected function accountIdOrNull(): ?int
    {
        return $this->isAdmin() ? null : auth()->user()->account_id;
    }

    /**
     * Helper: Convert status to Arabic badge HTML
     */
    protected function statusBadge(string $status): string
    {
        $map = [
            'pending'          => ['قيد الانتظار', 'st-pending'],
            'processing'       => ['قيد المعالجة', 'st-processing'],
            'ready'            => ['جاهز', 'badge-ac'],
            'shipped'          => ['تم الشحن', 'st-shipped'],
            'in_transit'       => ['قيد الشحن', 'st-intransit'],
            'out_for_delivery' => ['خرج للتوصيل', 'st-shipped'],
            'delivered'        => ['تم التسليم', 'st-delivered'],
            'cancelled'        => ['ملغي', 'st-cancelled'],
            'returned'         => ['مرتجع', 'st-cancelled'],
            'draft'            => ['مسودة', 'badge-td'],
            'active'           => ['نشط', 'st-active'],
            'open'             => ['مفتوحة', 'st-open'],
            'closed'           => ['مغلقة', 'badge-td'],
            'resolved'         => ['تم الحل', 'st-resolved'],
            'connected'        => ['متصل', 'st-connected'],
            'disconnected'     => ['غير متصل', 'st-cancelled'],
            'accepted'         => ['مقبولة', 'st-accepted'],
            'expired'          => ['منتهية', 'st-expired'],
            'failed'           => ['فشل', 'badge-dg'],
        ];

        $s = $map[$status] ?? [$status, 'badge-td'];
        return '<span class="badge ' . $s[1] . '">' . e($s[0]) . '</span>';
    }
}
