<?php

namespace App\Modules\Financial\Http\Controllers;

use App\Modules\Financial\DTOs\CreateRecurrenceDTO;
use App\Modules\Financial\Http\Requests\StoreRecurrenceRequest;
use App\Modules\Financial\Http\Requests\UpdateRecurrenceRequest;
use App\Modules\Financial\Http\Resources\FinancialRecurrenceResource;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Services\RecurrenceService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurrenceController extends ApiController
{
    public function __construct(private readonly RecurrenceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialRecurrence::class);

        $recurrences = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
        );

        return $this->paginated(FinancialRecurrenceResource::collection($recurrences));
    }

    public function show(FinancialRecurrence $recurrence): JsonResponse
    {
        $this->authorize('view', $recurrence);

        return $this->success(FinancialRecurrenceResource::make($recurrence->load('supplier', 'category')));
    }

    public function store(StoreRecurrenceRequest $request): JsonResponse
    {
        $this->authorize('create', FinancialRecurrence::class);

        $recurrence = $this->service->create(
            CreateRecurrenceDTO::fromArray($request->validated()),
            $request->user(),
        );

        return $this->created(FinancialRecurrenceResource::make($recurrence), 'Recorrência criada com sucesso.');
    }

    public function generate(): JsonResponse
    {
        $this->authorize('generate', FinancialRecurrence::class);

        $generated = $this->service->generateForTenant();

        $message = $generated > 0
            ? "{$generated} conta(s) gerada(s) com sucesso."
            : 'Nenhuma conta nova para gerar — as recorrências ativas já estão atualizadas.';

        return $this->success(['generated' => $generated], $message);
    }

    public function update(UpdateRecurrenceRequest $request, FinancialRecurrence $recurrence): JsonResponse
    {
        $this->authorize('update', $recurrence);

        $recurrence = $this->service->update(
            $recurrence,
            $request->validated(),
            $request->user(),
        );

        return $this->success(FinancialRecurrenceResource::make($recurrence), 'Recorrência atualizada com sucesso.');
    }

    public function destroy(FinancialRecurrence $recurrence): JsonResponse
    {
        $this->authorize('delete', $recurrence);

        $this->service->delete($recurrence, request()->user());

        return $this->success(null, 'Recorrência removida com sucesso.');
    }
}
