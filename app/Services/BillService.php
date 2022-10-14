<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Bill;
use App\Models\Sorts\SupplierNameSort;
use App\Traits\CoreService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class BillService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = Bill::class;
    }

    public function create(array $data)
    {
        if (!empty($data['installments'])) {
            $this->createManyBills($data);
        } else {
            $this->model::create($data);
        }
    }

    private function createManyBills(array $data)
    {
        $installments = $data['installments'];

        unset($data['installments']);

        $totals = $this->installmentValues($data['amount'], $installments);

        $description = $data['description'];

        $due_at = $data['due_at'];

        $original_due_at = $data['original_due_at'];
 
        for ($i = 0; $i < $installments; $i++) {
            $currentInstallment = $i + 1;

            $data['amount'] = $totals['installmentAmount'];

            if ($currentInstallment == $installments) {
                $data['amount'] = $totals['lastInstallmentAmount'];
            }

            $data['description'] = $this->installmentDescription($description, $installments, $currentInstallment);

            $data['due_at'] = $this->installmentDue($due_at, $installments, $currentInstallment);

            $data['original_due_at'] = $this->installmentDue($original_due_at, $installments, $currentInstallment);

            $this->model::create($data);
        }
    }

    private function installmentDue(string $due_at, int $installments, int $installment): string
    {
        return Carbon::createFromFormat('Y-m-d', $due_at)->addMonth($installment - 1)->format('Y-m-d');
    }

    private function installmentDescription(string $description, int $installments, int $installment): string
    {
        return "{$description} {$installment}/{$installments}";
    }

    private function installmentValues(float $total, int $installments): array
    {
        $installmentAmount = $total / $installments;

        $totalInstallments = $installmentAmount * $installments;

        $lastInstallmentAmount = $installmentAmount;

        if ($totalInstallments < $total) {
            $lastInstallmentAmount = $totalInstallments + ($total - $totalInstallments);
        }

        return [
            'installmentAmount' => $installmentAmount,
            'lastInstallmentAmount' => $lastInstallmentAmount,
        ];
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
            'category_id',
            'is_paid',
            'is_credit_card'
        ];

        return array_merge($scopes, $filters);
    }

    public function paginate(?int $items_per_page)
    {
        $filters = $this->filters();

        $bills = QueryBuilder::for(Bill::class)
            ->allowedFilters($filters)
            ->allowedSorts([
                AllowedSort::custom('supplier', new SupplierNameSort(), 'suppliers.name'),
                'due_at',
                '-due_at'
            ])
            ->paginate($items_per_page);

        $totalPay = QueryBuilder::for(Bill::class)
            ->allowedFilters($filters)
            ->where('type', 'pay')
            ->sum('amount');

        $totalReceive = QueryBuilder::for(Bill::class)
            ->allowedFilters($filters)
            ->where('type', 'receive')
            ->sum('amount');

        return ['bills' => $bills, 'totalPay' => $totalPay, 'totalReceive' => $totalReceive];
    }

    public function pay($id)
    {
        $bill = $this->find($id);

        throw_if(auth('sanctum')->user()->cannot('pay', $bill), new UnauthorizedException("Não foi possível executar essa ação"));

        throw_if($bill->is_paid, new UnauthorizedException("Essa conta já foi paga"));

        $bill->update(['is_paid' => true]);

        return $bill;
    }

    public function payBills(array $bills)
    {
        $bills = Bill::whereIn('id', $bills)->update(['is_paid' => 1]);
    }
}
