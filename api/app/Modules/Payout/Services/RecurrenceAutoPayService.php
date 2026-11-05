<?php

namespace App\Modules\Payout\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\FinancialAuditService;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Enums\PixKeyType;
use App\Modules\Payout\Exceptions\PayoutException;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pagamento automático via PIX das contas geradas por uma recorrência.
 *
 * A ordem de cada conta é criada DAYS_AHEAD dias antes do vencimento e
 * enviada no dia do vencimento, no horário configurado. Depois de criada,
 * segue o fluxo normal (nova conferência, limites, saldo e webhook de
 * autorização). Cancelar a ordem de um mês não faz o sistema recriá-la.
 */
class RecurrenceAutoPayService
{
    public const DAYS_AHEAD = 3;

    public function __construct(
        private readonly PayoutOrderService $orders,
        private readonly PayoutLimits $limits,
        private readonly FinancialAuditService $audit,
    ) {}

    /**
     * @param  array{pix_key?: ?string, pix_key_type?: ?string, time: string, max_value: float|string}  $data
     */
    public function enable(FinancialRecurrence $recurrence, array $data, User $actor): FinancialRecurrence
    {
        if (! $recurrence->active) {
            throw new PayoutException('Ative a recorrência antes de ligar o pagamento automático.');
        }

        $type = filled($data['pix_key_type'] ?? null) ? PixKeyType::from((string) $data['pix_key_type']) : null;
        $key = filled($data['pix_key'] ?? null) ? (string) $data['pix_key'] : null;

        // Sem chave informada, usa a do fornecedor (copiada como snapshot).
        if ($key === null) {
            $supplier = $recurrence->supplier;
            $key = $supplier?->pix_key;
            $type = filled($supplier?->pix_key_type) ? PixKeyType::from((string) $supplier->pix_key_type) : null;
        }

        if ($key === null || $type === null) {
            throw new PayoutException('Informe a chave PIX ou cadastre uma chave no fornecedor da recorrência.');
        }

        $key = PayoutPreviewService::normalizeCode(PayoutMethod::PixKey, $key, $type);
        $maxValue = round((float) $data['max_value'], 2);

        if ($maxValue <= 0) {
            throw new PayoutException('Informe o valor máximo por pagamento.');
        }

        if ($maxValue > $this->limits->maxPerPayment()) {
            throw new PayoutException(sprintf(
                'O valor máximo não pode passar do limite por pagamento (R$ %s).',
                number_format($this->limits->maxPerPayment(), 2, ',', '.'),
            ));
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $data['time']) !== 1) {
            throw new PayoutException('Horário inválido.');
        }

        DB::transaction(function () use ($recurrence, $key, $type, $maxValue, $data, $actor): void {
            $recurrence->forceFill([
                'auto_pay_enabled' => true,
                'auto_pay_pix_key' => $key,
                'auto_pay_pix_key_type' => $type->value,
                'auto_pay_time' => $data['time'],
                'auto_pay_max_value' => $maxValue,
                'auto_pay_approved_by' => $actor->getKey(),
                'auto_pay_approved_at' => now(),
            ])->save();

            $this->refreshScheduledOrders($recurrence->refresh(), $actor);
        });

        $this->audit->record($actor, AuditAction::PayoutAutoPayEnabled, 'financial_recurrence', $recurrence->uuid, [
            'pix_key_type' => $type->value,
            'max_value' => $maxValue,
            'time' => $data['time'],
        ]);

        // Contas que já estão dentro da janela ganham a ordem imediatamente.
        $this->scheduleForRecurrence($recurrence);

        return $recurrence->refresh();
    }

    public function disable(FinancialRecurrence $recurrence, ?User $actor, string $reason = 'Pagamento automático desligado'): FinancialRecurrence
    {
        DB::transaction(function () use ($recurrence, $actor, $reason): void {
            $recurrence->forceFill(['auto_pay_enabled' => false])->save();

            $this->cancelScheduled($recurrence, $reason.($actor ? " por {$actor->name}" : '').'.');
        });

        if ($actor !== null) {
            $this->audit->record($actor, AuditAction::PayoutAutoPayDisabled, 'financial_recurrence', $recurrence->uuid);
        }

        return $recurrence->refresh();
    }

    /**
     * Cria as ordens das contas que vencem nos próximos DAYS_AHEAD dias.
     */
    public function scheduleDue(): int
    {
        $created = 0;

        FinancialRecurrence::query()
            ->withoutTenancy()
            ->where('active', true)
            ->where('auto_pay_enabled', true)
            ->with('autoPayApprover')
            ->each(function (FinancialRecurrence $recurrence) use (&$created): void {
                try {
                    $created += $this->scheduleForRecurrence($recurrence);
                } catch (Throwable $e) {
                    report($e);
                    Log::error('payouts.recurring_failed', ['recurrence' => $recurrence->uuid, 'error' => $e->getMessage()]);
                }
            });

        return $created;
    }

    public function scheduleForRecurrence(FinancialRecurrence $recurrence): int
    {
        if (! $recurrence->active || ! $recurrence->auto_pay_enabled || blank($recurrence->auto_pay_pix_key)) {
            return 0;
        }

        $today = CarbonImmutable::now((string) config('asaas.payouts.timezone'))->startOfDay();

        $payables = Payable::query()
            ->withoutTenancy()
            ->where('tenant_id', $recurrence->tenant_id)
            ->where('recurrence_id', $recurrence->getKey())
            ->where('status', PayableStatus::Pending)
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $today->addDays(self::DAYS_AHEAD)->toDateString())
            // Uma única tentativa automática por conta: se a ordem do mês foi
            // cancelada ou falhou, a conta fica para decisão manual.
            ->whereDoesntHave('payoutOrders', fn ($query) => $query->withoutTenancy()->where('recurrence_id', $recurrence->getKey()))
            ->whereDoesntHave('activePayoutOrder', fn ($query) => $query->withoutTenancy())
            ->orderBy('due_date')
            ->get();

        $created = 0;

        foreach ($payables as $payable) {
            if ($this->orders->scheduleRecurring($payable, $recurrence) !== null) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Ordens ainda não enviadas passam a usar a configuração recém-aprovada.
     */
    private function refreshScheduledOrders(FinancialRecurrence $recurrence, User $actor): void
    {
        $timezone = (string) config('asaas.payouts.timezone');
        [$hour, $minute] = array_map('intval', explode(':', (string) $recurrence->auto_pay_time));

        PayoutOrder::query()
            ->withoutTenancy()
            ->where('recurrence_id', $recurrence->getKey())
            ->where('status', PayoutStatus::Scheduled)
            ->with('payable')
            ->lockForUpdate()
            ->get()
            ->each(function (PayoutOrder $order) use ($recurrence, $actor, $timezone, $hour, $minute): void {
                if ((float) $order->value > (float) $recurrence->auto_pay_max_value) {
                    $order->forceFill(['status' => PayoutStatus::Cancelled, 'active_lock' => null])->save();
                    $order->record('cancelled', 'Cancelado: o novo valor máximo da recorrência é menor que o desta conta.');

                    return;
                }

                $scheduledFor = CarbonImmutable::parse($order->payable->due_date->toDateString(), $timezone)
                    ->setTime($hour, $minute)
                    ->utc();

                $order->forceFill([
                    'payment_code' => $recurrence->auto_pay_pix_key,
                    'pix_key_type' => $recurrence->auto_pay_pix_key_type,
                    'scheduled_for' => $scheduledFor->isPast() ? now() : $scheduledFor,
                ])->save();

                $order->record('updated', "Atualizado com a nova configuração da recorrência aprovada por {$actor->name}.");
            });
    }

    private function cancelScheduled(FinancialRecurrence $recurrence, string $message): void
    {
        PayoutOrder::query()
            ->withoutTenancy()
            ->where('recurrence_id', $recurrence->getKey())
            ->where('status', PayoutStatus::Scheduled)
            ->lockForUpdate()
            ->get()
            ->each(function (PayoutOrder $order) use ($message): void {
                $order->forceFill(['status' => PayoutStatus::Cancelled, 'active_lock' => null])->save();
                $order->record('cancelled', $message);
            });
    }
}
