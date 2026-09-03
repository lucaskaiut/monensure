<?php

namespace App\Modules\Financial\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SUPPLIER_READ);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->sameTenant($supplier)
            && $user->hasPermission(Permission::SUPPLIER_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SUPPLIER_CREATE);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->sameTenant($supplier)
            && $user->hasPermission(Permission::SUPPLIER_UPDATE);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->sameTenant($supplier)
            && $user->hasPermission(Permission::SUPPLIER_DELETE);
    }

    private function sameTenant(Supplier $supplier): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $supplier->tenant_id);
    }
}
