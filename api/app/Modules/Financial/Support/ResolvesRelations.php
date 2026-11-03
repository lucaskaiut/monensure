<?php

namespace App\Modules\Financial\Support;

use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Models\Supplier;
use Illuminate\Http\Request;

/**
 * Resolve referências (uuid) de fornecedor e categoria para os ids numéricos
 * usados nas chaves estrangeiras das tabelas financeiras.
 */
trait ResolvesRelations
{
    protected function resolveSupplierId(?string $uuid): ?int
    {
        return filled($uuid)
            ? Supplier::query()->where('uuid', $uuid)->value('id')
            : null;
    }

    protected function resolveCategoryId(?string $uuid): ?int
    {
        return filled($uuid)
            ? Category::query()->where('uuid', $uuid)->value('id')
            : null;
    }

    /**
     * Converte o filtro `supplier_id` (uuid) da requisição no id numérico.
     * Um uuid desconhecido vira 0 para que o filtro continue restringindo
     * (sem resultados) em vez de ser ignorado.
     */
    protected function supplierFilter(Request $request): ?int
    {
        return $request->filled('supplier_id')
            ? ($this->resolveSupplierId($request->string('supplier_id')->toString()) ?? 0)
            : null;
    }

    /**
     * Converte o filtro `category_id` (uuid) da requisição no id numérico.
     */
    protected function categoryFilter(Request $request): ?int
    {
        return $request->filled('category_id')
            ? ($this->resolveCategoryId($request->string('category_id')->toString()) ?? 0)
            : null;
    }
}
