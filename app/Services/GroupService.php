<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Group;
use App\Traits\CoreService;

class GroupService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = Group::class;
    }
}