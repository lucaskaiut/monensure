<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class GroupObserver
{
    public function creating(Model $model)
    {
        $user = auth('sanctum')->user();

        if($user)
            $model->group_id = $user->group_id; 
    }
}
