<?php

namespace App\Modules\Payout\Http\Controllers;

use App\Modules\Financial\Models\Payable;
use App\Modules\Payout\DTOs\SchedulePayoutDTO;
use App\Modules\Payout\Http\Controllers\Concerns\ConfirmsHumanUser;
use App\Modules\Payout\Http\Requests\PreviewPayoutRequest;
use App\Modules\Payout\Http\Requests\ResolvePayoutRequest;
use App\Modules\Payout\Http\Requests\SchedulePayoutRequest;
use App\Modules\Payout\Http\Resources\PayoutOrderResource;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\Payout\Services\PayoutOrderService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagamento de contas via Asaas.
 *
 * Ações que movem dinheiro exigem usuário humano (não aceitam token de API)
 * e confirmação de senha.
 */
class PayoutOrderController extends ApiController
{
    use ConfirmsHumanUser;

    public function __construct(private readonly PayoutOrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PayoutOrder::class);

        $payableId = null;

        if (filled($request->string('payable_id')->toString())) {
            $payableId = Payable::query()->where('uuid', $request->string('payable_id')->toString())->value('id') ?? 0;
        }

        $orders = $this->orders->paginate([
            'status' => $request->string('status')->toString() ?: null,
            'payable_id' => $payableId,
        ], (int) $request->integer('per_page', 15));

        return $this->paginated(PayoutOrderResource::collection($orders));
    }

    public function show(PayoutOrder $payout): JsonResponse
    {
        $this->authorize('view', $payout);

        return $this->success(PayoutOrderResource::make($payout->load('payable.supplier', 'events')));
    }

    public function overview(): JsonResponse
    {
        $this->authorize('viewAny', PayoutOrder::class);

        return $this->success($this->orders->overview());
    }

    public function preview(PreviewPayoutRequest $request): JsonResponse
    {
        $user = $this->human($request);
        $this->authorizeFor($user, 'create', PayoutOrder::class);

        return $this->guard(fn () => $this->success(
            $this->orders->preview(SchedulePayoutDTO::fromArray($request->validated()))->toArray(),
        ));
    }

    public function store(SchedulePayoutRequest $request, Payable $payable): JsonResponse
    {
        $user = $this->human($request);
        $this->authorizeFor($user, 'create', PayoutOrder::class);
        $this->authorizeFor($user, 'pay', $payable);
        $this->confirmPassword($request, $user);

        return $this->guard(function () use ($request, $payable, $user): JsonResponse {
            $order = $this->orders->schedule($payable, SchedulePayoutDTO::fromArray($request->validated()), $user);

            return $this->created(
                PayoutOrderResource::make($order->load('payable.supplier', 'events')),
                $order->scheduled_for->isFuture() ? 'Pagamento agendado.' : 'Pagamento enviado para execução.',
            );
        });
    }

    public function cancel(Request $request, PayoutOrder $payout): JsonResponse
    {
        $user = $this->human($request);
        $this->authorizeFor($user, 'manage', $payout);

        return $this->guard(fn () => $this->success(
            PayoutOrderResource::make($this->orders->cancel($payout, $user)->load('payable.supplier', 'events')),
            'Agendamento cancelado.',
        ));
    }

    public function resolve(ResolvePayoutRequest $request, PayoutOrder $payout): JsonResponse
    {
        $user = $this->human($request);
        $this->authorizeFor($user, 'manage', $payout);
        $this->confirmPassword($request, $user);

        return $this->guard(fn () => $this->success(
            PayoutOrderResource::make(
                $this->orders->resolve($payout, (string) $request->validated('outcome'), $user)->load('payable.supplier', 'events'),
            ),
            'Pagamento resolvido.',
        ));
    }

    public function sync(Request $request, PayoutOrder $payout): JsonResponse
    {
        $user = $this->human($request);
        $this->authorizeFor($user, 'manage', $payout);

        return $this->guard(fn () => $this->success(
            PayoutOrderResource::make($this->orders->sync($payout)->load('payable.supplier', 'events')),
            'Status atualizado.',
        ));
    }
}
