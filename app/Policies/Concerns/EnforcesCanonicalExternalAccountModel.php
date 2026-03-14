<?php

namespace App\Policies\Concerns;

use App\Models\Account;
use App\Models\User;

trait EnforcesCanonicalExternalAccountModel
{
    private function allowsOrganizationTeamManagement(User $user): bool
    {
        if (($user->user_type ?? 'external') !== 'external') {
            return true;
        }

        $accountId = trim((string) $user->account_id);
        if ($accountId === '') {
            return false;
        }

        /** @var Account|null $account */
        $account = Account::withoutGlobalScopes()->find($accountId);

        return $account?->allowsTeamManagement() ?? false;
    }
}
