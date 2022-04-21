<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Bill;
use App\Traits\CoreService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;

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

    public function paginate(?int $items_per_page)
    {
        $bills = $this->model::paginate($items_per_page ?? 10);

        return ['bills' => $bills, 'total' => $bills->sum('amount')];
    }

    public function pay($id)
    {
        $bill = $this->find($id);

        throw_if(auth('sanctum')->user()->cannot('pay', $bill), new UnauthorizedException("Não foi possível executar essa ação"));

        throw_if($bill->is_paid, new UnauthorizedException("Essa conta já foi paga"));

        $bill->update(['is_paid' => true]);
    
        return $bill;
    }
}