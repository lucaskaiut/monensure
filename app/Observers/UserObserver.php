<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class UserObserver
{
    public function creating(Model $model)
    {
        $user = auth('sanctum')->user();

        if($user)
            $model->user_id = $user->id; 
    }
}
