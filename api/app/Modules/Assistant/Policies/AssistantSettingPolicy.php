<?php

namespace App\Modules\Assistant\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Assistant\Models\AssistantSetting;
use App\Modules\User\Models\User;

class AssistantSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ASSISTANT_MANAGE);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(Permission::ASSISTANT_MANAGE);
    }
}
