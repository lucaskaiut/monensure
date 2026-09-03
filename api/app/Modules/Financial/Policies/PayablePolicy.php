<?php

namespace App\Modules\Financial\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Models\Payable;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class PayablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PAYABLE_READ);
    }

    public function view(User $user, Payable $payable): bool
    {
        return $this->sameTenant($payable)
            && $user->hasPermission(Permission::PAYABLE_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::PAYABLE_CREATE);
    }

    public function update(User $user, Payable $payable): bool
    {
        return $this->sameTenant($payable)
            && $user->hasPermission(Permission::PAYABLE_UPDATE);
    }

    public function pay(User $user, Payable $payable): bool
    {
        return $this->sameTenant($payable)
            && $user->hasPermission(Permission::PAYABLE_PAY);
    }

    public function cancel(User $user, Payable $payable): bool
    {
        return $this->sameTenant($payable)
            && $user->hasPermission(Permission::PAYABLE_UPDATE);
    }

    public function delete(User $user, Payable $payable): bool
    {
        return $this->sameTenant($payable)
            && $user->hasPermission(Permission::PAYABLE_DELETE);
    }

    private function sameTenant(Payable $payable): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $payable->tenant_id);
    }
}
