<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;
use App\Policies\Concerns\AuthorizesTenantResource;

class WalletPolicy
{
    use AuthorizesTenantResource;

    public function view(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'wallet.balance', $wallet);
    }

    public function viewLedger(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'wallet.ledger', $wallet);
    }

    public function topup(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'wallet.topup', $wallet);
    }

    public function configure(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'wallet.configure', $wallet);
    }

    public function viewPaymentMethods(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'billing.view', $wallet);
    }

    public function managePaymentMethods(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'billing.manage', $wallet);
    }

    public function manageTransactions(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'wallet.manage', $wallet);
    }

    public function manageBilling(User $user, ?Wallet $wallet = null): bool
    {
        return $this->allowsWalletAbility($user, 'billing.manage', $wallet);
    }

    private function allowsWalletAbility(User $user, string $permission, ?Wallet $wallet): bool
    {
        if ($wallet === null) {
            return $this->allowsTenantAction($user, $permission);
        }

        return $this->allowsTenantResourceAction($user, $permission, $wallet->account_id);
    }
}
