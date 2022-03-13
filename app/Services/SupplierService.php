<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Supplier;
use App\Traits\CoreService;

class SupplierService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = Supplier::class;
    }
}