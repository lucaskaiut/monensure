<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Bill;
use App\Traits\CoreService;
use Illuminate\Database\Eloquent\Model;

class BillService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = Bill::class;
    }

    public function create(array $data): Model
    {
        (new SupplierService())->find($data['supplier_id']);
         
        (new CategoryService())->find($data['category_id']);

        $bill = $this->model::create($data);

        return $bill->refresh();
    }
}