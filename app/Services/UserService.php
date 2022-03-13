<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\User;
use App\Traits\CoreService;

class UserService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = User::class;
    }
}