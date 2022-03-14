<?php

namespace App\Http\Controllers;

use App\Http\Resources\SupplierResource;
use App\Http\Validators\SupplierValidator;
use App\Interfaces\ControllerInterface;
use App\Services\SupplierService;
use App\Traits\CoreController;
use Illuminate\Http\Request;

class SupplierController extends Controller implements ControllerInterface
{
    use CoreController;

    public function __construct()
    {
        $this->resource = SupplierResource::class;
        $this->service = app(SupplierService::class);
        $this->requestValidator = new SupplierValidator();
    }
}
