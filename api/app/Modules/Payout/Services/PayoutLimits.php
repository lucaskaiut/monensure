<?php

namespace App\Modules\Payout\Services;

use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Exceptions\PayoutException;
use App\Modules\Payout\Models\PayoutOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Kill switch e limites de valor (por pagamento e por dia).
 */
class PayoutLimits
{
    public function enabled(): bool
    {
        return (bool) config('asaas.payouts.enabled');
    }

    public function maxPerPayment(): float
    {
        return (float) config('asaas.payouts.max_per_payment');
    }

    public function maxPerDay(): float
    {
        return (float) config('asaas.payouts.max_per_day');
    }

    public function ensureEnabled(): void
    {
        if (! $this->enabled()) {
            throw new PayoutException('Pagamentos via Asaas estão desativados (ASAAS_PAYOUTS_ENABLED).');
        }
    }

    /**
     * Valida o valor contra os limites, considerando o dia (no fuso configurado)
     * em que o pagamento será executado.
     */
    public function ensureWithin(float $value, CarbonInterface $executionAt, ?PayoutOrder $ignore = null): void
    {
        if ($value > $this->maxPerPayment()) {
            throw new PayoutException(sprintf(
                'O valor excede o limite por pagamento (R$ %s).',
                number_format($this->maxPerPayment(), 2, ',', '.'),
            ));
        }

        $used = $this->committedOn($executionAt, $ignore);

        if ($used + $value > $this->maxPerDay()) {
            throw new PayoutException(sprintf(
                'O valor excede o limite diário (R$ %s; já comprometido no dia: R$ %s).',
                number_format($this->maxPerDay(), 2, ',', '.'),
                number_format($used, 2, ',', '.'),
            ));
        }
    }

    /**
     * Soma do que já saiu (ou está agendado para sair) no dia.
     */
    public function committedOn(CarbonInterface $moment, ?PayoutOrder $ignore = null): float
    {
        [$start, $end] = $this->dayWindow($moment);

        $sent = PayoutOrder::query()
            ->withoutTenancy()
            ->whereIn('status', PayoutStatus::committed())
            ->whereBetween('submitted_at', [$start, $end])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->sum('value');

        $scheduled = PayoutOrder::query()
            ->withoutTenancy()
            ->where('status', PayoutStatus::Scheduled)
            ->whereBetween('scheduled_for', [$start, $end])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->sum('value');

        return round((float) $sent + (float) $scheduled, 2);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dayWindow(CarbonInterface $moment): array
    {
        $local = CarbonImmutable::instance($moment)->setTimezone((string) config('asaas.payouts.timezone'));

        return [$local->startOfDay()->utc(), $local->endOfDay()->utc()];
    }
}
