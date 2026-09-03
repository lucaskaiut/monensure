<?php

namespace App\Modules\Financial\Http\Controllers;

use App\Modules\Financial\Http\Resources\PayableResource;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\FinancialOverviewService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialOverviewController extends ApiController
{
    public function __construct(private readonly FinancialOverviewService $service) {}

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payable::class);

        return $this->success($this->service->summary());
    }

    public function cashflow(Request $request): JsonResponse
    {
        return $this->success($this->service->projection());
    }

    public function overdue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payable::class);

        $payables = $this->service->overdue([
            'supplier_id' => $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null,
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
        ], (int) $request->integer('per_page', 15));

        return $this->paginated(PayableResource::collection($payables));
    }
}
