<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Bill;
use App\Traits\CoreService;

class BillService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = Bill::class;
    }
}