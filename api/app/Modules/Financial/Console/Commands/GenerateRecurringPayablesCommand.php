<?php

namespace App\Modules\Financial\Console\Commands;

use App\Modules\Financial\Services\RecurrenceService;
use Illuminate\Console\Command;

class GenerateRecurringPayablesCommand extends Command
{
    protected $signature = 'financial:generate-recurring-payables';

    protected $description = 'Gera os lançamentos futuros (1 ano) das recorrências financeiras ativas';

    public function handle(RecurrenceService $recurrences): int
    {
        $generated = $recurrences->generateUpcomingPayables();

        $this->info("Lançamentos de recorrências gerados: {$generated}.");

        return self::SUCCESS;
    }
}
