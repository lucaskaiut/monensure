<?php

namespace App\Modules\Financial\Http\Controllers;

use App\Modules\Financial\DTOs\CreateInstallmentPlanDTO;
use App\Modules\Financial\DTOs\CreatePayableDTO;
use App\Modules\Financial\DTOs\PayPayableDTO;
use App\Modules\Financial\DTOs\UpdatePayableDTO;
use App\Modules\Financial\Enums\PayableEditScope;
use App\Modules\Financial\Http\Requests\PayPayableRequest;
use App\Modules\Financial\Http\Requests\StoreInstallmentPlanRequest;
use App\Modules\Financial\Http\Requests\StorePayableRequest;
use App\Modules\Financial\Http\Requests\UpdatePayableRequest;
use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\PayableService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayableController extends ApiController
{
    public function __construct(private readonly PayableService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payable::class);

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'supplier_id' => $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null,
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
        ];

        $payables = $this->service->paginate($filters, (int) $request->integer('per_page', 15));
        $valueTotal = $this->service->sumValues($filters);

        $collection = PayableResource::collection($payables);
        $payload = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $payload['data'] ?? [],
            'meta' => array_merge($payload['meta'] ?? [], ['value_total' => $valueTotal]),
            'links' => $payload['links'] ?? null,
        ]);
    }

    public function show(Payable $payable): JsonResponse
    {
        $this->authorize('view', $payable);

        return $this->success(PayableResource::make($payable->load('supplier', 'category', 'payments')));
    }

    public function store(StorePayableRequest $request): JsonResponse
    {
        $this->authorize('create', Payable::class);

        $payable = $this->service->create(
            CreatePayableDTO::fromArray($request->validated()),
            $request->user(),
        );

        return $this->created(PayableResource::make($payable->load('supplier', 'category')), 'Conta a pagar criada com sucesso.');
    }

    public function storeInstallments(StoreInstallmentPlanRequest $request): JsonResponse
    {
        $this->authorize('create', Payable::class);

        $group = $this->service->createInstallmentPlan(
            CreateInstallmentPlanDTO::fromArray($request->validated()),
            $request->user(),
        );

        return $this->created([
            'installment_group_uuid' => $group->uuid,
            'description' => $group->description,
            'total_installments' => $group->total_installments,
            'first_installment_number' => $group->first_installment_number,
            'payables' => PayableResource::collection($group->payables),
        ], 'Parcelamento criado com sucesso.');
    }

    public function update(UpdatePayableRequest $request, Payable $payable): JsonResponse
    {
        $this->authorize('update', $payable);

        $targets = $this->service->update(
            $payable,
            UpdatePayableDTO::fromArray($request->validated()),
            $request->user(),
        );

        $targets->each(fn (Payable $payable) => $payable->load('supplier', 'category'));

        return $this->success([
            'updated' => $targets->count(),
            'payables' => PayableResource::collection($targets),
        ], 'Conta(s) atualizada(s) com sucesso.');
    }

    public function pay(PayPayableRequest $request, Payable $payable): JsonResponse
    {
        $this->authorize('pay', $payable);

        $payable = $this->service->pay(
            $payable,
            PayPayableDTO::fromArray($request->validated()),
            $request->user(),
        );

        return $this->success(PayableResource::make($payable), 'Pagamento registrado com sucesso.');
    }

    public function cancel(Request $request, Payable $payable): JsonResponse
    {
        $this->authorize('cancel', $payable);

        $scope = filled($request->string('scope')->toString())
            ? PayableEditScope::from($request->string('scope')->toString())
            : null;

        $affected = $this->service->cancel($payable, $scope, $request->user());

        return $this->success(['cancelled' => $affected], 'Conta(s) cancelada(s) com sucesso.');
    }

    public function destroy(Payable $payable): JsonResponse
    {
        $this->authorize('delete', $payable);

        $this->service->destroy($payable, request()->user());

        return $this->success(null, 'Conta a pagar removida com sucesso.');
    }
}
