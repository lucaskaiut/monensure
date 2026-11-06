<?php

namespace App\Modules\Payout\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Billing\Gateways\Asaas\AsaasException;
use App\Modules\Financial\DTOs\PayPayableDTO;
use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\FinancialAuditService;
use App\Modules\Financial\Services\PayableService;
use App\Modules\Payout\DTOs\PayoutPreview;
use App\Modules\Payout\DTOs\SchedulePayoutDTO;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Exceptions\PayoutException;
use App\Modules\Payout\Gateways\AsaasPayoutClient;
use App\Modules\Payout\Gateways\AsaasPayoutStatus;
use App\Modules\Payout\Jobs\ExecutePayoutOrderJob;
use App\Modules\Payout\Models\PayoutOrder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ciclo de vida das ordens de pagamento via Asaas.
 *
 * Regras de segurança:
 * - Só uma ordem ativa por conta (índice único em active_lock).
 * - A execução nunca é repetida automaticamente: erro ambíguo vira needs_review.
 * - Antes de enviar, o instrumento é validado de novo; valor acima do aprovado
 *   ou beneficiário diferente cancelam a execução.
 * - A baixa da conta só acontece com o status final consultado no Asaas.
 */
class PayoutOrderService
{
    public function __construct(
        private readonly AsaasPayoutClient $asaas,
        private readonly PayoutPreviewService $previews,
        private readonly PayoutLimits $limits,
        private readonly PayableService $payables,
        private readonly FinancialAuditService $audit,
        private readonly CurrentTenant $tenantContext,
    ) {}

    /**
     * @param  array{status?: ?string, payable_id?: ?int}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return PayoutOrder::query()
            ->with('payable.supplier')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payable_id'] ?? null, fn ($query, $id) => $query->where('payable_id', $id))
            ->latest('scheduled_for')
            ->latest('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $balance = null;

        if ($this->limits->enabled() && filled(config('asaas.payouts.api_key'))) {
            try {
                $balance = $this->asaas->balance();
            } catch (Throwable $e) {
                Log::warning('payouts.balance_failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'enabled' => $this->limits->enabled(),
            'max_per_payment' => $this->limits->maxPerPayment(),
            'max_per_day' => $this->limits->maxPerDay(),
            'committed_today' => $this->limits->committedOn(now()),
            'balance' => $balance,
            'environment' => str_contains((string) config('asaas.base_url'), 'sandbox') ? 'sandbox' : 'production',
        ];
    }

    public function preview(SchedulePayoutDTO $dto): PayoutPreview
    {
        return $this->previews->preview($dto->method, $dto->paymentCode, $dto->pixKeyType);
    }

    public function schedule(Payable $payable, SchedulePayoutDTO $dto, User $actor): PayoutOrder
    {
        $this->limits->ensureEnabled();

        if ($payable->status !== PayableStatus::Pending) {
            throw new PayoutException('Somente contas pendentes podem ser pagas via Asaas.');
        }

        if ($dto->method === PayoutMethod::PixKey && $dto->pixKeyType === null) {
            throw new PayoutException('Informe o tipo da chave PIX.');
        }

        $scheduledFor = $dto->scheduledFor ?? CarbonImmutable::now();

        if ($scheduledFor->lt(now()->subMinute())) {
            throw new PayoutException('A data e hora do agendamento já passaram.');
        }

        if ($scheduledFor->gt(now()->addYear())) {
            throw new PayoutException('O agendamento pode ser feito com até um ano de antecedência.');
        }

        $preview = $this->previews->preview($dto->method, $dto->paymentCode, $dto->pixKeyType);

        if (! $preview->canPay) {
            throw new PayoutException($preview->cannotPayReason ?: 'O Asaas informou que este pagamento não pode ser realizado.');
        }

        $value = $this->approvedValue($preview, $dto->value);

        $this->limits->ensureWithin($value, $scheduledFor);

        try {
            $order = DB::transaction(function () use ($payable, $dto, $preview, $value, $scheduledFor, $actor): PayoutOrder {
                $order = PayoutOrder::query()->create([
                    'tenant_id' => $payable->tenant_id,
                    'payable_id' => $payable->getKey(),
                    'created_by' => $actor->getKey(),
                    'method' => $dto->method,
                    'status' => PayoutStatus::Scheduled,
                    'value' => $value,
                    'payment_code' => $preview->paymentCode,
                    'pix_key_type' => $dto->pixKeyType,
                    'beneficiary_name' => $preview->beneficiaryName,
                    'beneficiary_document' => $preview->beneficiaryDocument,
                    'preview' => $preview->toArray(),
                    'scheduled_for' => $scheduledFor,
                    'active_lock' => $payable->getKey(),
                ]);

                $order->record('scheduled', sprintf(
                    'Pagamento de R$ %s agendado para %s por %s.',
                    number_format($value, 2, ',', '.'),
                    $scheduledFor->setTimezone((string) config('asaas.payouts.timezone'))->format('d/m/Y H:i'),
                    $actor->name,
                ));

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PayoutException('Esta conta já tem um pagamento via Asaas em andamento.');
        }

        $this->audit->record($actor, AuditAction::PayoutScheduled, 'payout_order', $order->uuid, [
            'payable' => $payable->uuid,
            'method' => $dto->method->value,
            'value' => $value,
            'scheduled_for' => $scheduledFor->toIso8601String(),
        ]);

        if ($scheduledFor->lte(now())) {
            ExecutePayoutOrderJob::dispatch($order->getKey())->afterCommit();
        }

        return $order->refresh();
    }

    /**
     * Cria a ordem de uma conta gerada por recorrência com pagamento automático
     * via PIX. A chave e o valor máximo foram aprovados com senha na recorrência.
     *
     * Se o valor passar do máximo ou dos limites, registra a ordem como falha
     * (visível em Pagamentos Asaas) e a conta fica para pagamento manual.
     */
    public function scheduleRecurring(Payable $payable, FinancialRecurrence $recurrence): ?PayoutOrder
    {
        if (! $this->limits->enabled() || $payable->status !== PayableStatus::Pending) {
            return null;
        }

        $value = round((float) $payable->value, 2);
        $timezone = (string) config('asaas.payouts.timezone');
        [$hour, $minute] = array_map('intval', explode(':', (string) ($recurrence->auto_pay_time ?: '09:00')) + [1 => 0]);

        $scheduledFor = CarbonImmutable::parse($payable->due_date->toDateString(), $timezone)
            ->setTime($hour, $minute)
            ->utc();

        if ($scheduledFor->isPast()) {
            $scheduledFor = CarbonImmutable::now();
        }

        $failure = null;

        if ($recurrence->auto_pay_max_value !== null && $value > (float) $recurrence->auto_pay_max_value) {
            $failure = sprintf(
                'O valor da conta (R$ %s) passa do máximo aprovado na recorrência (R$ %s). Pague manualmente ou ajuste o máximo.',
                number_format($value, 2, ',', '.'),
                number_format((float) $recurrence->auto_pay_max_value, 2, ',', '.'),
            );
        } else {
            try {
                $this->limits->ensureWithin($value, $scheduledFor);
            } catch (PayoutException $e) {
                $failure = $e->getMessage().' Pague manualmente.';
            }
        }

        $approver = $recurrence->autoPayApprover;

        try {
            return DB::transaction(function () use ($payable, $recurrence, $value, $scheduledFor, $failure, $approver, $timezone): PayoutOrder {
                $order = PayoutOrder::query()->create([
                    'tenant_id' => $payable->tenant_id,
                    'payable_id' => $payable->getKey(),
                    'recurrence_id' => $recurrence->getKey(),
                    'created_by' => $recurrence->auto_pay_approved_by,
                    'method' => PayoutMethod::PixKey,
                    'status' => $failure === null ? PayoutStatus::Scheduled : PayoutStatus::Failed,
                    'value' => $value,
                    'payment_code' => $recurrence->auto_pay_pix_key,
                    'pix_key_type' => $recurrence->auto_pay_pix_key_type,
                    'preview' => ['source' => 'recurrence', 'recurrence' => $recurrence->uuid],
                    'scheduled_for' => $scheduledFor,
                    'failure_reason' => $failure,
                    'settled_at' => $failure === null ? null : now(),
                    'active_lock' => $failure === null ? $payable->getKey() : null,
                ]);

                $order->record('scheduled', sprintf(
                    'Agendado automaticamente pela recorrência "%s" para %s (aprovada por %s em %s).',
                    $recurrence->description,
                    $scheduledFor->setTimezone($timezone)->format('d/m/Y H:i'),
                    $approver?->name ?? 'usuário removido',
                    $recurrence->auto_pay_approved_at?->setTimezone($timezone)->format('d/m/Y H:i') ?? '—',
                ));

                if ($failure !== null) {
                    $order->record('failed', mb_substr($failure, 0, 255));
                }

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            // Já existe uma ordem ativa para a conta (ex.: agendada manualmente).
            return null;
        }
    }

    public function cancel(PayoutOrder $order, User $actor): PayoutOrder
    {
        $cancelled = DB::transaction(function () use ($order, $actor): bool {
            $locked = PayoutOrder::query()->withoutTenancy()->lockForUpdate()->find($order->getKey());

            if ($locked === null || $locked->status !== PayoutStatus::Scheduled) {
                return false;
            }

            $locked->forceFill([
                'status' => PayoutStatus::Cancelled,
                'active_lock' => null,
            ])->save();

            $locked->record('cancelled', "Agendamento cancelado por {$actor->name}.");

            return true;
        });

        if (! $cancelled) {
            throw new PayoutException('Somente pagamentos agendados (ainda não enviados) podem ser cancelados.');
        }

        $this->audit->record($actor, AuditAction::PayoutCancelled, 'payout_order', $order->uuid);

        return $order->refresh();
    }

    /**
     * Ordens agendadas cujo horário chegou.
     */
    public function dispatchDue(): int
    {
        $ids = PayoutOrder::query()
            ->withoutTenancy()
            ->where('status', PayoutStatus::Scheduled)
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->pluck('id');

        foreach ($ids as $id) {
            ExecutePayoutOrderJob::dispatch((int) $id);
        }

        return $ids->count();
    }

    /**
     * Executa uma ordem agendada. Seguro contra execução concorrente: só uma
     * chamada consegue mover a ordem de scheduled para submitting.
     */
    public function execute(int $orderId): void
    {
        $order = DB::transaction(function () use ($orderId): ?PayoutOrder {
            $order = PayoutOrder::query()->withoutTenancy()->lockForUpdate()->find($orderId);

            if ($order === null || $order->status !== PayoutStatus::Scheduled || $order->scheduled_for->isFuture()) {
                return null;
            }

            $order->forceFill([
                'status' => PayoutStatus::Submitting,
                'submitted_at' => now(),
            ])->save();

            return $order;
        });

        if ($order === null) {
            return;
        }

        try {
            $amount = $this->prepareSubmission($order);
        } catch (PayoutException $e) {
            $this->fail($order, $e->getMessage());

            return;
        } catch (Throwable $e) {
            Log::error('payouts.prepare_failed', ['order' => $order->uuid, 'error' => $e->getMessage()]);
            $this->fail($order, 'Não foi possível validar o pagamento antes do envio: '.$e->getMessage());

            return;
        }

        // Grava o valor antes do envio: o webhook de autorização compara com ele.
        $order->forceFill(['paid_value' => $amount])->save();

        try {
            $response = $this->submit($order, $amount);
        } catch (AsaasException $e) {
            if (in_array($e->statusCode, [400, 401, 403, 404, 422], true)) {
                $this->fail($order, 'O Asaas recusou o pagamento: '.$e->getMessage(), ['errors' => $e->errors]);

                return;
            }

            $this->markForReview($order, 'Resposta inesperada do Asaas no envio (HTTP '.$e->statusCode.'): '.$e->getMessage());

            return;
        } catch (Throwable $e) {
            // Timeout ou queda de conexão: o pagamento pode ter sido criado.
            $this->markForReview($order, 'Falha de comunicação no envio; o pagamento pode ter sido criado: '.$e->getMessage());

            return;
        }

        DB::transaction(function () use ($order, $amount, $response): void {
            $order = PayoutOrder::query()->withoutTenancy()->lockForUpdate()->findOrFail($order->getKey());

            $order->forceFill([
                'status' => $order->status === PayoutStatus::Submitting ? PayoutStatus::Processing : $order->status,
                'paid_value' => $amount,
                'external_id' => $order->external_id ?? (isset($response['id']) ? (string) $response['id'] : null),
                'external_transfer_id' => isset($response['transferId']) ? (string) $response['transferId'] : $order->external_transfer_id,
                'external_status' => isset($response['status']) ? (string) $response['status'] : null,
                'receipt_url' => $response['transactionReceiptUrl'] ?? $order->receipt_url,
            ])->save();

            $order->record('submitted', sprintf(
                'Enviado ao Asaas (R$ %s). Status: %s.',
                number_format($amount, 2, ',', '.'),
                $response['status'] ?? 'desconhecido',
            ), ['id' => $response['id'] ?? null]);
        });

        $this->applyExternalStatus($order->refresh(), $response);
    }

    /**
     * Consulta o Asaas e aplica o status atual da ordem.
     */
    public function sync(PayoutOrder $order): PayoutOrder
    {
        if (blank($order->external_id) || ! in_array($order->status, [PayoutStatus::Processing, PayoutStatus::NeedsReview, PayoutStatus::Paid], true)) {
            return $order;
        }

        $response = match ($order->method) {
            PayoutMethod::Boleto => $this->asaas->getBill((string) $order->external_id),
            PayoutMethod::PixQrCode => $this->asaas->getPixTransaction((string) $order->external_id),
            PayoutMethod::PixKey => $this->asaas->getTransfer((string) $order->external_id),
        };

        $this->applyExternalStatus($order, $response);

        return $order->refresh();
    }

    /**
     * Conciliação periódica: consulta ordens em processamento e sinaliza
     * envios que ficaram presos.
     *
     * @return array{checked: int, stuck: int}
     */
    public function reconcile(): array
    {
        $checked = 0;

        PayoutOrder::query()
            ->withoutTenancy()
            ->where('status', PayoutStatus::Processing)
            ->whereNotNull('external_id')
            ->each(function (PayoutOrder $order) use (&$checked): void {
                $checked++;

                try {
                    $this->sync($order);
                } catch (Throwable $e) {
                    Log::warning('payouts.reconcile_failed', ['order' => $order->uuid, 'error' => $e->getMessage()]);
                }
            });

        $stuck = 0;

        PayoutOrder::query()
            ->withoutTenancy()
            ->where('status', PayoutStatus::Submitting)
            ->where('submitted_at', '<', now()->subMinutes(15))
            ->each(function (PayoutOrder $order) use (&$stuck): void {
                $stuck++;
                $this->markForReview($order, 'O envio não terminou em 15 minutos; confira no painel do Asaas.');
            });

        return ['checked' => $checked, 'stuck' => $stuck];
    }

    /**
     * Resolução manual de uma ordem com resultado incerto, após conferência no Asaas.
     */
    public function resolve(PayoutOrder $order, string $outcome, User $actor): PayoutOrder
    {
        if ($order->status !== PayoutStatus::NeedsReview) {
            throw new PayoutException('Somente pagamentos em conferência podem ser resolvidos manualmente.');
        }

        if ($outcome === 'paid') {
            $this->settle($order, (float) ($order->paid_value ?? $order->value), now(), "Marcado como pago por {$actor->name} após conferência.");
        } else {
            $this->fail($order, "Marcado como não pago por {$actor->name} após conferência.", allowFrom: [PayoutStatus::NeedsReview]);
        }

        $this->audit->record($actor, AuditAction::PayoutResolved, 'payout_order', $order->uuid, ['outcome' => $outcome]);

        return $order->refresh();
    }

    /**
     * Aplica um retorno do Asaas (resposta de criação ou consulta).
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyExternalStatus(PayoutOrder $order, array $payload): void
    {
        $status = isset($payload['status']) ? (string) $payload['status'] : null;
        $outcome = AsaasPayoutStatus::outcome($order->method, $status);

        $order->forceFill([
            'external_status' => $status ?? $order->external_status,
            'external_transfer_id' => $order->external_transfer_id ?? (isset($payload['transferId']) ? (string) $payload['transferId'] : null),
            'receipt_url' => $payload['transactionReceiptUrl'] ?? $order->receipt_url,
        ])->save();

        match ($outcome) {
            AsaasPayoutStatus::PAID => $this->settle(
                $order,
                (float) ($payload['value'] ?? $order->paid_value ?? $order->value),
                $this->effectiveDate($payload),
                'Pagamento confirmado pelo Asaas.',
            ),
            AsaasPayoutStatus::FAILED => $this->fail(
                $order,
                $this->failureReason($payload),
                $payload,
                [PayoutStatus::Submitting, PayoutStatus::Processing, PayoutStatus::NeedsReview],
            ),
            AsaasPayoutStatus::REVERSED => $this->flagReversal($order, $status),
            default => null,
        };
    }

    private function approvedValue(PayoutPreview $preview, ?float $requested): float
    {
        if ($preview->value !== null && ! $preview->allowsCustomValue) {
            return $preview->value;
        }

        $value = $requested ?? $preview->value;

        if ($value === null || $value <= 0) {
            throw new PayoutException('Informe o valor a pagar.');
        }

        return round($value, 2);
    }

    /**
     * Revalida o instrumento e os limites imediatamente antes do envio.
     */
    private function prepareSubmission(PayoutOrder $order): float
    {
        $this->limits->ensureEnabled();

        $payable = $order->payable;

        if ($payable === null || $payable->trashed() || $payable->status !== PayableStatus::Pending) {
            throw new PayoutException('A conta não está mais pendente.');
        }

        $preview = $this->previews->preview($order->method, $order->payment_code, $order->pix_key_type);

        if (! $preview->canPay) {
            throw new PayoutException($preview->cannotPayReason ?: 'O Asaas informou que o pagamento não pode mais ser realizado.');
        }

        if ($order->beneficiary_document !== null && $preview->beneficiaryDocument !== $order->beneficiary_document) {
            throw new PayoutException(sprintf(
                'O beneficiário mudou desde o agendamento (%s → %s). Pagamento não enviado.',
                $order->beneficiary_document,
                $preview->beneficiaryDocument ?? 'desconhecido',
            ));
        }

        $amount = $preview->value !== null && ! $preview->allowsCustomValue
            ? $preview->value
            : (float) $order->value;

        if ($amount > (float) $order->value) {
            throw new PayoutException(sprintf(
                'O valor atual (R$ %s) é maior que o aprovado (R$ %s). Pagamento não enviado.',
                number_format($amount, 2, ',', '.'),
                number_format((float) $order->value, 2, ',', '.'),
            ));
        }

        $this->limits->ensureWithin($amount, now(), $order);

        $balance = $this->asaas->balance();

        if ($balance < $amount) {
            throw new PayoutException(sprintf(
                'Saldo insuficiente no Asaas (R$ %s disponível).',
                number_format($balance, 2, ',', '.'),
            ));
        }

        return round($amount, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function submit(PayoutOrder $order, float $amount): array
    {
        $description = mb_substr('Monensure: '.($order->payable?->description ?? 'conta'), 0, 140);

        return match ($order->method) {
            PayoutMethod::Boleto => $this->asaas->payBill(array_filter([
                'identificationField' => $order->payment_code,
                'description' => $description,
                'externalReference' => $order->uuid,
                // Sem scheduleDate o Asaas agenda boletos a vencer para o dia do
                // vencimento. O agendamento é feito aqui, então a ordem é paga hoje.
                'scheduleDate' => CarbonImmutable::now((string) config('asaas.payouts.timezone'))->toDateString(),
                // Boletos sem valor na linha digitável exigem o valor explícito.
                'value' => ($order->preview['value'] ?? null) === null ? $amount : null,
            ], static fn (mixed $value): bool => $value !== null)),
            PayoutMethod::PixQrCode => $this->asaas->payPixQrCode([
                'qrCode' => ['payload' => $order->payment_code],
                'value' => $amount,
                'description' => $description,
            ]),
            PayoutMethod::PixKey => $this->asaas->createTransfer([
                'value' => $amount,
                'operationType' => 'PIX',
                'pixAddressKey' => $order->payment_code,
                'pixAddressKeyType' => $order->pix_key_type?->value,
                'description' => $description,
                'externalReference' => $order->uuid,
            ]),
        };
    }

    private function settle(PayoutOrder $order, float $paidValue, CarbonImmutable $paidAt, string $message): void
    {
        $settled = DB::transaction(function () use ($order, $paidValue, $paidAt, $message): bool {
            $locked = PayoutOrder::query()->withoutTenancy()->lockForUpdate()->findOrFail($order->getKey());

            if (! in_array($locked->status, [PayoutStatus::Submitting, PayoutStatus::Processing, PayoutStatus::NeedsReview], true)) {
                return false;
            }

            $locked->forceFill([
                'status' => PayoutStatus::Paid,
                'paid_value' => $paidValue,
                'settled_at' => now(),
                'failure_reason' => null,
                'active_lock' => null,
            ])->save();

            $locked->record('paid', $message);

            $this->withTenant($locked, function () use ($locked, $paidValue, $paidAt): void {
                $payable = Payable::query()->withoutTenancy()->find($locked->payable_id);

                if ($payable === null || $payable->status !== PayableStatus::Pending) {
                    $locked->record('payable_skipped', 'A conta não estava pendente; baixa não registrada.');

                    return;
                }

                $this->payables->pay($payable, new PayPayableDTO(
                    paidAt: $paidAt,
                    paidValue: number_format($paidValue, 2, '.', ''),
                    notes: 'Pago via Asaas (ordem '.$locked->uuid.')',
                ));
            });

            return true;
        });

        if ($settled) {
            Log::info('payouts.paid', ['order' => $order->uuid]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  list<PayoutStatus>  $allowFrom
     */
    private function fail(PayoutOrder $order, string $reason, ?array $data = null, array $allowFrom = [PayoutStatus::Submitting]): void
    {
        DB::transaction(function () use ($order, $reason, $data, $allowFrom): void {
            $locked = PayoutOrder::query()->withoutTenancy()->lockForUpdate()->findOrFail($order->getKey());

            if (! in_array($locked->status, $allowFrom, true)) {
                return;
            }

            $locked->forceFill([
                'status' => PayoutStatus::Failed,
                'failure_reason' => mb_substr($reason, 0, 2000),
                'settled_at' => now(),
                'active_lock' => null,
            ])->save();

            $locked->record('failed', mb_substr($reason, 0, 255), $data);
        });

        Log::warning('payouts.failed', ['order' => $order->uuid, 'reason' => $reason]);
    }

    private function markForReview(PayoutOrder $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason): void {
            $locked = PayoutOrder::query()->withoutTenancy()->lockForUpdate()->findOrFail($order->getKey());

            if (! in_array($locked->status, [PayoutStatus::Submitting, PayoutStatus::Processing], true)) {
                return;
            }

            $locked->forceFill([
                'status' => PayoutStatus::NeedsReview,
                'failure_reason' => mb_substr($reason, 0, 2000),
            ])->save();

            $locked->record('needs_review', mb_substr($reason, 0, 255));
        });

        Log::error('payouts.needs_review', ['order' => $order->uuid, 'reason' => $reason]);
    }

    private function flagReversal(PayoutOrder $order, ?string $status): void
    {
        $order->record('reversed', "O Asaas informou estorno do pagamento ({$status}). Confira a conta.");

        Log::error('payouts.reversed', ['order' => $order->uuid, 'status' => $status]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function failureReason(array $payload): string
    {
        $reasons = $payload['failReasons'] ?? $payload['failReason'] ?? $payload['refusalReason'] ?? null;

        if (is_array($reasons)) {
            $reasons = implode(' ', array_filter(array_map('strval', $reasons)));
        }

        return filled($reasons)
            ? 'Pagamento não realizado pelo Asaas: '.$reasons
            : 'Pagamento não realizado pelo Asaas (status '.($payload['status'] ?? 'desconhecido').').';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function effectiveDate(array $payload): CarbonImmutable
    {
        $date = $payload['paymentDate'] ?? $payload['effectiveDate'] ?? null;

        try {
            return filled($date) ? CarbonImmutable::parse((string) $date) : CarbonImmutable::now();
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    /**
     * Webhooks e jobs não têm tenant resolvido: o registro da baixa precisa dele.
     */
    private function withTenant(PayoutOrder $order, callable $callback): void
    {
        $previous = $this->tenantContext->tenant();
        $tenant = Tenant::query()->find($order->tenant_id);

        if ($tenant !== null) {
            $this->tenantContext->set($tenant);
        }

        try {
            $callback();
        } finally {
            $previous !== null ? $this->tenantContext->set($previous) : $this->tenantContext->forget();
        }
    }
}
