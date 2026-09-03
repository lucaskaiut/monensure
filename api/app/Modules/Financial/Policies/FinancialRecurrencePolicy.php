<?php

namespace App\Modules\Financial\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class FinancialRecurrencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::RECURRENCE_READ);
    }

    public function view(User $user, FinancialRecurrence $recurrence): bool
    {
        return $this->sameTenant($recurrence)
            && $user->hasPermission(Permission::RECURRENCE_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::RECURRENCE_CREATE);
    }

    public function update(User $user, FinancialRecurrence $recurrence): bool
    {
        return $this->sameTenant($recurrence)
            && $user->hasPermission(Permission::RECURRENCE_UPDATE);
    }

    public function delete(User $user, FinancialRecurrence $recurrence): bool
    {
        return $this->sameTenant($recurrence)
            && $user->hasPermission(Permission::RECURRENCE_DELETE);
    }

    private function sameTenant(FinancialRecurrence $recurrence): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $recurrence->tenant_id);
    }
}
