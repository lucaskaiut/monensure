<?php

namespace App\Models\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class SupplierNameSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property)
    {
        $query
            ->leftJoin('suppliers', 'suppliers.id', '=', 'supplier_id')
            ->orderBy($property);
    }
}