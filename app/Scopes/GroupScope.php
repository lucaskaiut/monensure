<?php

namespace App\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class GroupScope implements Scope
{
    private $user;

    public function __construct(?User $user)
    {
        $this->user = $user;
    }

    public function apply(Builder $builder, Model $model)
    {
        if($this->user) {
            $builder->where("{$model->getTable()}.group_id", $this->user->group_id);
        }
    }
}