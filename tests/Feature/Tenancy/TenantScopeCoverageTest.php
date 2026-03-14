<?php

namespace Tests\Feature\Tenancy;

use App\Models\Address;
use App\Models\ApiKey;
use App\Models\Claim;
use App\Models\CustomerApiKey;
use App\Models\Invitation;
use App\Models\KycRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scopes\AccountScope;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\Traits\BelongsToAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WebhookEvent;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TenantScopeCoverageTest extends TestCase
{
    #[Test]
    public function test_tenant_bound_models_have_account_scoping_contract(): void
    {
        foreach ($this->tenantBoundModels() as $modelClass) {
            $this->assertTrue(class_exists($modelClass), "Model {$modelClass} must exist.");

            $modelClass::query();
            $model = new $modelClass();

            $usesTrait = in_array(BelongsToAccount::class, class_uses_recursive($modelClass), true);
            $hasAccountScope = $model->hasGlobalScope(AccountScope::class);

            $this->assertTrue(
                $usesTrait || $hasAccountScope,
                "{$modelClass} must use BelongsToAccount or register AccountScope."
            );
        }
    }

    #[Test]
    public function test_excluded_models_are_not_directly_tenant_scoped(): void
    {
        $excluded = [
            User::class,
            TicketReply::class,
            Permission::class,
        ];

        foreach ($excluded as $modelClass) {
            $this->assertTrue(class_exists($modelClass), "Model {$modelClass} must exist.");
            $usesTrait = in_array(BelongsToAccount::class, class_uses_recursive($modelClass), true);
            $this->assertFalse($usesTrait, "{$modelClass} must not use BelongsToAccount.");
        }
    }

    #[Test]
    public function test_internal_rbac_models_are_not_tenant_models(): void
    {
        $this->assertFalse(class_exists(\App\Models\InternalRole::class));
        $this->assertFalse(class_exists(\App\Models\InternalUserRole::class));
        $this->assertFalse(class_exists(\App\Models\InternalRolePermission::class));
    }

    /**
     * @return array<int, class-string>
     */
    private function tenantBoundModels(): array
    {
        return [
            Shipment::class,
            Order::class,
            Store::class,
            Wallet::class,
            WalletTransaction::class,
            Address::class,
            SupportTicket::class,
            Invitation::class,
            Claim::class,
            KycRequest::class,
            Notification::class,
            WebhookEvent::class,
            ApiKey::class,
            CustomerApiKey::class,
            Role::class,
        ];
    }
}
