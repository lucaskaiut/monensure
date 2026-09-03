<?php

namespace App\Modules\Financial\Services;

use App\Modules\Financial\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SupplierService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Supplier::query()
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Lista completa (para selects).
     *
     * @return Collection<int, Supplier>
     */
    public function all(): Collection
    {
        return Supplier::query()->orderBy('name')->get();
    }

    /**
     * @param  array{name: string, document?: ?string, phone?: ?string, email?: ?string, observations?: ?string}  $data
     */
    public function create(array $data): Supplier
    {
        return Supplier::query()->create($data);
    }

    /**
     * @param  array{name: string, document?: ?string, phone?: ?string, email?: ?string, observations?: ?string}  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->fill($data);
        $supplier->save();

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }
}
