<?php

use App\Modules\Billing\Console\Commands\CheckInvoiceStatusCommand;
use App\Modules\Billing\Console\Commands\GenerateInvoicesCommand;
use App\Modules\Billing\Console\Commands\SuspendExpiredSubscriptionsCommand;
use App\Modules\Financial\Console\Commands\GenerateRecurringPayablesCommand;
use App\Modules\Payout\Console\Commands\DispatchDuePayoutsCommand;
use App\Modules\Payout\Console\Commands\ReconcilePayoutsCommand;
use App\Modules\Payout\Console\Commands\ScheduleRecurringPayoutsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateInvoicesCommand::class)->hourly();
Schedule::command(CheckInvoiceStatusCommand::class)->everyFifteenMinutes();
Schedule::command(SuspendExpiredSubscriptionsCommand::class)->daily();
Schedule::command(GenerateRecurringPayablesCommand::class)->daily();
Schedule::command(DispatchDuePayoutsCommand::class)->everyMinute()->withoutOverlapping();
Schedule::command(ReconcilePayoutsCommand::class)->everyTenMinutes()->withoutOverlapping();
// Roda de hora em hora (idempotente): cobre contas geradas ou ajustadas durante o dia.
Schedule::command(ScheduleRecurringPayoutsCommand::class)->hourly()->withoutOverlapping();
