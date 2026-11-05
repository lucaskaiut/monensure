<?php

namespace App\Modules\Payout\Console\Commands;

use App\Modules\Payout\Services\RecurrenceAutoPayService;
use Illuminate\Console\Command;

class ScheduleRecurringPayoutsCommand extends Command
{
    protected $signature = 'payouts:schedule-recurring';

    protected $description = 'Agenda o pagamento via PIX das contas de recorrências com pagamento automático (3 dias antes do vencimento)';

    public function handle(RecurrenceAutoPayService $autoPay): int
    {
        $created = $autoPay->scheduleDue();

        $this->info("Pagamentos automáticos agendados: {$created}.");

        return self::SUCCESS;
    }
}
