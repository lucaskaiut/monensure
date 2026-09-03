<?php

namespace App\Modules\Financial\Http\Controllers;

use App\Modules\Financial\Http\Requests\StoreSupplierRequest;
use App\Modules\Financial\Http\Requests\UpdateSupplierRequest;
use App\Modules\Financial\Http\Resources\SupplierResource;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Financial\Services\SupplierService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends ApiController
{
    public function __construct(private readonly SupplierService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(SupplierResource::collection($suppliers));
    }

    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        return $this->success(SupplierResource::collection($this->service->all()));
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return $this->success(SupplierResource::make($supplier));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->service->create($request->validated());

        return $this->created(SupplierResource::make($supplier), 'Fornecedor criado com sucesso.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $supplier = $this->service->update($supplier, $request->validated());

        return $this->success(SupplierResource::make($supplier), 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $this->service->delete($supplier);

        return $this->success(null, 'Fornecedor removido com sucesso.');
    }
}
