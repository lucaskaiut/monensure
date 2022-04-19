<?php

namespace App\Http\Controllers;

use App\Http\Resources\BillResource;
use App\Http\Resources\CategoryResource;
use App\Services\BillService;
use App\Traits\CoreController;

class BillController extends Controller
{
    use CoreController;

    public function __construct()
    {
        $this->service = app(BillService::class);
        $this->resource = BillResource::class;
    }
}
