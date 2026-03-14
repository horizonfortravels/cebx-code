<?php
namespace App\Policies;

use App\Models\{User, Store};

class StorePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('stores.view'); }
    public function view(User $user, Store $store): bool { return $user->account_id === $store->account_id; }
    public function create(User $user): bool { return $user->hasPermission('stores.create'); }
    public function sync(User $user, Store $store): bool { return $user->account_id === $store->account_id && $user->hasPermission('stores.sync'); }
    public function delete(User $user, Store $store): bool { return $user->account_id === $store->account_id && $user->hasPermission('stores.delete'); }
}
