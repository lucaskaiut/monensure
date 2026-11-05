<?php

namespace App\Modules\Payout\Http\Controllers;

use App\Modules\Financial\Http\Resources\FinancialRecurrenceResource;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Payout\Http\Controllers\Concerns\ConfirmsHumanUser;
use App\Modules\Payout\Http\Requests\EnableAutoPayRequest;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\Payout\Services\RecurrenceAutoPayService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liga/desliga o pagamento automático via PIX de uma recorrência.
 */
class RecurrenceAutoPayController extends ApiController
{
    use ConfirmsHumanUser;

    public function __construct(private readonly RecurrenceAutoPayService $autoPay) {}

    public function enable(EnableAutoPayRequest $request, FinancialRecurrence $recurrence): JsonResponse
    {
        $user = $this->human($request);
        $this->authorizeFor($user, 'create', PayoutOrder::class);
        $this->authorizeFor($user, 'update', $recurrence);
        $this->confirmPassword($request, $user);

        return $this->guard(fn () => $this->success(
            FinancialRecurrenceResource::make(
                $this->autoPay->enable($recurrence, $request->validated(), $user)->load('supplier', 'category', 'autoPayApprover'),
            ),
            'Pagamento automático ativado.',
        ));
    }

    public function disable(Request $request, FinancialRecurrence $recurrence): JsonResponse
    {
        $user = $this->human($request);
        $this->authorizeFor($user, 'create', PayoutOrder::class);
        $this->authorizeFor($user, 'update', $recurrence);

        return $this->guard(fn () => $this->success(
            FinancialRecurrenceResource::make(
                $this->autoPay->disable($recurrence, $user)->load('supplier', 'category', 'autoPayApprover'),
            ),
            'Pagamento automático desativado.',
        ));
    }
}
