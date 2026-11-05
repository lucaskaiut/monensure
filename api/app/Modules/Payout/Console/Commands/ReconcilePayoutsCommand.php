<?php

namespace App\Modules\Payout\Console\Commands;

use App\Modules\Payout\Services\PayoutOrderService;
use Illuminate\Console\Command;

class ReconcilePayoutsCommand extends Command
{
    protected $signature = 'payouts:reconcile';

    protected $description = 'Consulta no Asaas os pagamentos em processamento e sinaliza envios presos';

    public function handle(PayoutOrderService $orders): int
    {
        $result = $orders->reconcile();

        $this->info("Pagamentos consultados: {$result['checked']}. Envios presos: {$result['stuck']}.");

        return self::SUCCESS;
    }
}
