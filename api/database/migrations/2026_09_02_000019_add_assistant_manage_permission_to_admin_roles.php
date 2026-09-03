<?php

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Enums\Permission;
use App\Modules\ACL\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Role::query()
            ->where('name', DefaultRole::ADMINISTRATOR->value)
            ->each(function (Role $role): void {
                if (! $role->hasPermission(Permission::ASSISTANT_MANAGE)) {
                    $role->grantPermissions(Permission::ASSISTANT_MANAGE);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
