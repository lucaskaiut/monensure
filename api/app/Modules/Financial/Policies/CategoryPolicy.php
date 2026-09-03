<?php

namespace App\Modules\Financial\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Financial\Models\Category;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CATEGORY_READ);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->sameTenant($category)
            && $user->hasPermission(Permission::CATEGORY_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CATEGORY_CREATE);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->sameTenant($category)
            && $user->hasPermission(Permission::CATEGORY_UPDATE);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->sameTenant($category)
            && $user->hasPermission(Permission::CATEGORY_DELETE);
    }

    private function sameTenant(Category $category): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $category->tenant_id);
    }
}
