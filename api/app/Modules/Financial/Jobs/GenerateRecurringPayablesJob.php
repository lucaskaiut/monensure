<?php

namespace App\Modules\Financial\Jobs;

use App\Modules\Financial\Services\RecurrenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateRecurringPayablesJob implements ShouldQueue
{
    use Queueable;

    public function handle(RecurrenceService $recurrences): int
    {
        return $recurrences->generateUpcomingPayables();
    }
}
