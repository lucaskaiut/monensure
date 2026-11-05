<?php

namespace App\Modules\Payout\Console\Commands;

use App\Modules\Payout\Services\PayoutOrderService;
use Illuminate\Console\Command;

class DispatchDuePayoutsCommand extends Command
{
    protected $signature = 'payouts:dispatch-due';

    protected $description = 'Envia para execução os pagamentos via Asaas cujo horário agendado chegou';

    public function handle(PayoutOrderService $orders): int
    {
        $count = $orders->dispatchDue();

        if ($count > 0) {
            $this->info("Pagamentos enviados para execução: {$count}.");
        }

        return self::SUCCESS;
    }
}
