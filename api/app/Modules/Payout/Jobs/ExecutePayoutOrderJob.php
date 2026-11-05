<?php

namespace App\Modules\Payout\Jobs;

use App\Modules\Payout\Services\PayoutOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Executa uma ordem de pagamento. Nunca é reexecutado automaticamente:
 * repetir um envio de dinheiro com resultado incerto pode pagar em dobro.
 */
class ExecutePayoutOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Abaixo do retry_after da fila (90s); reexecuções são inofensivas pela trava de estado.
    public int $timeout = 85;

    public function __construct(public readonly int $orderId) {}

    public function handle(PayoutOrderService $orders): void
    {
        $orders->execute($this->orderId);
    }
}
