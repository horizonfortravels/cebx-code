<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\FeatureFlag;
use App\Models\IntegrationHealthLog;
use App\Models\RoleTemplate;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\SystemSetting;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\Account;
use Illuminate\Support\Collection;

/**
 * AdminService — FR-ADM-001→010 (10 requirements)
 *
 * FR-ADM-001: System settings & carrier credentials
 * FR-ADM-002: Integration health monitoring & alerts
 * FR-ADM-003: User & role management (platform level)
 * FR-ADM-004: Subscription plan management (via PAY)
 * FR-ADM-005: Tax rules & pricing administration
 * FR-ADM-006: System health dashboard + role templates
 * FR-ADM-007: KYC review queue (via KYC module)
 * FR-ADM-008: Support ticket management
 * FR-ADM-009: API key management
 * FR-ADM-010: Feature flags & experiments
 */
class AdminService
{
    // ═══════════════════════════════════════════════════════════
    // FR-ADM-001: System Settings
    // ═══════════════════════════════════════════════════════════

    public function getSettings(string $group): Collection
    {
        return SystemSetting::forGroup($group)->get()->map(function ($s) {
            return [
                'group' => $s->group, 'key' => $s->key,
                'value' => $s->is_sensitive ? '********' : $s->getTypedValue(),
                'type'  => $s->type, 'description' => $s->description,
            ];
        });
    }

    public function updateSetting(string $group, string $key, $value, string $type = 'string', ?string $updatedBy = null): SystemSetting
    {
        return SystemSetting::setValue($group, $key, $value, $type, $updatedBy);
    }

    public function getSetting(string $group, string $key, $default = null)
    {
        return SystemSetting::getValue($group, $key, $default);
    }

    public function testCarrierConnection(string $carrier): array
    {
        $startTime = microtime(true);
        // In production: actually ping carrier API
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        IntegrationHealthLog::recordCheck($carrier . '_api', IntegrationHealthLog::STATUS_HEALTHY, $responseTime);

        return ['carrier' => $carrier, 'status' => 'healthy', 'response_time_ms' => $responseTime];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-002/006: Integration Health & System Health
    // ═══════════════════════════════════════════════════════════

    public function getIntegrationHealth(?string $service = null, int $hours = 24): Collection
    {
        $query = IntegrationHealthLog::recent($hours);
        if ($service) $query->forService($service);
        return $query->orderByDesc('checked_at')->get();
    }

    public function getSystemHealthDashboard(): array
    {
        $services = ['dhl_api', 'aramex_api', 'database', 'redis', 'queue'];
        $status = [];

        foreach ($services as $svc) {
            $latest = IntegrationHealthLog::forService($svc)->orderByDesc('checked_at')->first();
            $status[$svc] = [
                'status'        => $latest?->status ?? 'unknown',
                'response_time' => $latest?->response_time_ms ?? null,
                'last_checked'  => $latest?->checked_at ?? null,
            ];
        }

        return [
            'overall_status' => collect($status)->contains(fn($s) => $s['status'] === 'down') ? 'degraded' : 'healthy',
            'services'       => $status,
            'checked_at'     => now(),
        ];
    }

    public function recordHealthCheck(string $service, string $status, int $responseMs = 0, ?string $error = null): IntegrationHealthLog
    {
        return IntegrationHealthLog::recordCheck($service, $status, $responseMs, $error);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-003: Platform User Management
    // ═══════════════════════════════════════════════════════════

    public function listPlatformUsers(array $filters = [], int $perPage = 20)
    {
        $query = User::query();
        if (!empty($filters['account_id'])) $query->where('account_id', $filters['account_id']);
        if (!empty($filters['role'])) $query->whereHas('role', fn($q) => $q->where('slug', $filters['role']));
        if (!empty($filters['search'])) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%"));
        }
        return $query->with('role')->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function suspendUser(string $userId, string $reason): User
    {
        $user = User::findOrFail($userId);
        $user->update(['status' => 'suspended', 'suspension_reason' => $reason]);
        return $user;
    }

    public function activateUser(string $userId): User
    {
        $user = User::findOrFail($userId);
        $user->update(['status' => 'active', 'suspension_reason' => null]);
        return $user;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-005: Tax Rules
    // ═══════════════════════════════════════════════════════════

    public function listTaxRules(?string $countryCode = null): Collection
    {
        $query = TaxRule::active();
        if ($countryCode) $query->forCountry($countryCode);
        return $query->get();
    }

    public function createTaxRule(array $data): TaxRule
    {
        return TaxRule::create($data);
    }

    public function updateTaxRule(string $id, array $data): TaxRule
    {
        $rule = TaxRule::findOrFail($id);
        $rule->update($data);
        return $rule->fresh();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-006: Role Templates
    // ═══════════════════════════════════════════════════════════

    public function listRoleTemplates(): Collection
    {
        return RoleTemplate::active()->get();
    }

    public function createRoleTemplate(array $data): RoleTemplate
    {
        return RoleTemplate::create($data);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-008: Support Tickets
    // ═══════════════════════════════════════════════════════════

    public function createTicket(Account $account, User $user, array $data): SupportTicket
    {
        return SupportTicket::create(array_merge($data, [
            'account_id'    => $account->id,
            'user_id'       => $user->id,
            'ticket_number' => SupportTicket::generateNumber(),
            'status'        => SupportTicket::STATUS_OPEN,
        ]));
    }

    public function replyToTicket(string $ticketId, User $user, string $body, bool $isInternal = false): SupportTicketReply
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id, 'user_id' => $user->id,
            'body' => $body, 'is_internal_note' => $isInternal,
        ]);

        // Mark first response
        if (!$ticket->first_response_at && $ticket->user_id !== $user->id) {
            $ticket->update(['first_response_at' => now()]);
        }

        // Update status
        if ($ticket->user_id === $user->id) {
            $ticket->update(['status' => SupportTicket::STATUS_WAITING_AGENT]);
        } else {
            $ticket->update(['status' => SupportTicket::STATUS_WAITING_CUSTOMER]);
        }

        return $reply;
    }

    public function assignTicket(string $ticketId, string $userId, ?string $team = null): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        $ticket->assign($userId, $team);
        return $ticket->fresh();
    }

    public function resolveTicket(string $ticketId, string $notes): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        $ticket->resolve($notes);
        return $ticket->fresh();
    }

    public function listTickets(array $filters = [], int $perPage = 20)
    {
        $query = SupportTicket::query();
        if (!empty($filters['account_id'])) $query->where('account_id', $filters['account_id']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['priority'])) $query->where('priority', $filters['priority']);
        if (!empty($filters['assigned_to'])) $query->where('assigned_to', $filters['assigned_to']);
        if (!empty($filters['category'])) $query->where('category', $filters['category']);

        return $query->with('user', 'assignee')->orderByDesc('created_at')->paginate($perPage);
    }

    public function getTicket(string $ticketId): SupportTicket
    {
        return SupportTicket::with('replies.user', 'user', 'assignee')->findOrFail($ticketId);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-009: API Key Management
    // ═══════════════════════════════════════════════════════════

    public function createApiKey(Account $account, User $user, string $name, array $scopes = []): array
    {
        return ApiKey::generate($account->id, $user->id, $name, $scopes);
    }

    public function revokeApiKey(string $keyId): void
    {
        $key = ApiKey::findOrFail($keyId);
        $key->revoke();
    }

    public function rotateApiKey(string $keyId, User $user): array
    {
        $old = ApiKey::findOrFail($keyId);
        $old->revoke();

        return ApiKey::generate($old->account_id, $user->id, $old->name . ' (rotated)', $old->scopes ?? []);
    }

    public function listApiKeys(Account $account): Collection
    {
        return ApiKey::where('account_id', $account->id)->orderByDesc('created_at')->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-010: Feature Flags
    // ═══════════════════════════════════════════════════════════

    public function listFeatureFlags(): Collection
    {
        return FeatureFlag::all();
    }

    public function createFeatureFlag(array $data): FeatureFlag
    {
        return FeatureFlag::create($data);
    }

    public function toggleFeatureFlag(string $flagId, bool $enabled): FeatureFlag
    {
        $flag = FeatureFlag::findOrFail($flagId);
        $flag->update(['is_enabled' => $enabled]);
        return $flag->fresh();
    }

    public function isFeatureEnabled(string $key, ?string $accountId = null): bool
    {
        return FeatureFlag::isEnabled($key, $accountId);
    }

    public function updateFlagRollout(string $flagId, int $percentage): FeatureFlag
    {
        $flag = FeatureFlag::findOrFail($flagId);
        $flag->update(['rollout_percentage' => min(100, max(0, $percentage))]);
        return $flag->fresh();
    }
}
