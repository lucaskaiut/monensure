<?php

namespace App\Modules\Financial\Support;

use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Models\Supplier;

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
}
