<?php
namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class OrderPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'orders.read');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->allowsTenantResourceAction($user, 'orders.read', $order->account_id);
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'orders.manage');
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $this->allowsTenantResourceAction($user, 'orders.manage', $order->account_id);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->allowsTenantResourceAction($user, 'orders.manage', $order->account_id);
    }
}
