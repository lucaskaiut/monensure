<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Bill;
use App\Traits\CoreService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

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

    private function filters(): array
    {
        $scopes = [
            AllowedFilter::scope('created_after'),
            AllowedFilter::scope('created_before'),
            AllowedFilter::scope('reference_after'),
            AllowedFilter::scope('reference_before'),
            AllowedFilter::scope('due_after'),
            AllowedFilter::scope('due_before'),
            AllowedFilter::scope('original_due_after'),
            AllowedFilter::scope('original_due_before'),
        ];

        $filters = [
            'supplier_id',
            'category_id'
        ];

        return array_merge($scopes, $filters);
    }

    public function paginate(?int $items_per_page)
    {
        $filters = $this->filters();

        $bills = QueryBuilder::for(Bill::class)
            ->allowedFilters($filters)
            ->paginate($items_per_page);

        $total = QueryBuilder::for(Bill::class)
            ->allowedFilters($filters)
            ->sum('amount');

        return ['bills' => $bills, 'total' => $total];
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
