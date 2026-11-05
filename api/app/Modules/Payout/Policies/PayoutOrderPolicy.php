<?php

namespace App\Modules\Payout\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class PayoutOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PAYOUT_READ);
    }

    public function view(User $user, PayoutOrder $order): bool
    {
        return $this->sameTenant($order) && $user->hasPermission(Permission::PAYOUT_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::PAYOUT_EXECUTE);
    }

    public function manage(User $user, PayoutOrder $order): bool
    {
        return $this->sameTenant($order) && $user->hasPermission(Permission::PAYOUT_EXECUTE);
    }

    private function sameTenant(PayoutOrder $order): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $order->tenant_id);
    }
}
