<?php

namespace App\Modules\Payout\Services;

use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PayoutStatus;
use App\Modules\Payout\Models\PayoutOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decide os pedidos do mecanismo de validação de saques via webhook do Asaas.
 *
 * Toda saída de dinheiro da conta passa por aqui antes de ser executada. Só é
 * aprovada uma operação que corresponda a uma ordem criada por este sistema,
 * enviada há pouco, ainda não autorizada, com o mesmo tipo e sem valor maior
 * que o aprovado. Qualquer outra coisa (inclusive operações feitas com a chave
 * de API fora deste sistema) é recusada.
 */
class PayoutAuthorizationService
{
    private const OBJECT_KEYS = [
        'TRANSFER' => 'transfer',
        'BILL' => 'bill',
        'PIX_QR_CODE' => 'pixQrCode',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, refuseReason?: string}
     */
    public function decide(array $payload): array
    {
        $type = strtoupper((string) ($payload['type'] ?? ''));
        $key = self::OBJECT_KEYS[$type] ?? null;
        $object = $key !== null && is_array($payload[$key] ?? null) ? $payload[$key] : null;

        if ($object === null) {
            return $this->refuse(null, "Tipo de operação não permitido: {$type}.", $payload);
        }

        return DB::transaction(function () use ($type, $object, $payload): array {
            $order = $this->findOrder($type, $object);

            if ($order === null) {
                return $this->refuse(null, 'Operação não encontrada nas ordens do sistema.', $payload);
            }

            if ($order->method->authorizationType() !== $type) {
                return $this->refuse($order, "Tipo divergente ({$type}).", $payload);
            }

            if (! in_array($order->status, [PayoutStatus::Submitting, PayoutStatus::Processing], true)) {
                return $this->refuse($order, "Ordem em estado {$order->status->value}.", $payload);
            }

            if ($order->authorized_at !== null) {
                return $this->refuse($order, 'Ordem já autorizada anteriormente.', $payload);
            }

            $window = (int) config('asaas.payouts.authorization_window_minutes', 30);

            if ($order->submitted_at === null || $order->submitted_at->lt(now()->subMinutes($window))) {
                return $this->refuse($order, 'Fora da janela de autorização.', $payload);
            }

            $value = round((float) ($object['value'] ?? 0), 2);

            if ($value <= 0 || $value > (float) $order->value) {
                return $this->refuse($order, "Valor divergente (R$ {$value}).", $payload);
            }

            if ($order->method === PayoutMethod::PixKey) {
                $pixKey = $object['bankAccount']['pixAddressKey'] ?? null;

                if (filled($pixKey) && ! $this->sameKey((string) $pixKey, $order->payment_code)) {
                    return $this->refuse($order, 'Chave PIX divergente.', $payload);
                }
            }

            if ($order->method === PayoutMethod::Boleto) {
                // A linha digitável é obrigatória e precisa ser exatamente a aprovada:
                // impede pagar outro boleto reaproveitando o id de uma ordem válida.
                $line = $this->digits((string) ($object['identificationField'] ?? ''));

                if ($line === '') {
                    return $this->refuse($order, 'Linha digitável ausente no pedido de autorização.', $payload);
                }

                if ($line !== $this->digits($order->payment_code)) {
                    return $this->refuse($order, 'Linha digitável divergente da aprovada.', $payload);
                }
            }

            $externalId = isset($object['id']) ? (string) $object['id'] : null;

            $order->forceFill([
                'authorized_at' => now(),
                'external_id' => $order->external_id ?? $externalId,
            ])->save();

            $order->record('authorized', 'Saída autorizada no webhook de validação do Asaas.', ['id' => $externalId, 'value' => $value]);

            return ['status' => 'APPROVED'];
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findOrder(string $type, array $object): ?PayoutOrder
    {
        $query = fn () => PayoutOrder::query()->withoutTenancy()->lockForUpdate();
        $externalId = isset($object['id']) ? (string) $object['id'] : null;

        if (filled($externalId)) {
            $order = $query()
                ->where(fn ($q) => $q->where('external_id', $externalId)->orWhere('external_transfer_id', $externalId))
                ->first();

            if ($order !== null) {
                return $order;
            }
        }

        $reference = $object['externalReference'] ?? null;

        if (filled($reference)) {
            $order = $query()->where('uuid', (string) $reference)->first();

            if ($order !== null) {
                return $order;
            }
        }

        // O pagamento de QR Code não aceita externalReference: se a autorização
        // chegar antes de o id ser gravado, aceita somente uma única ordem em
        // envio com o mesmo valor.
        if ($type === 'PIX_QR_CODE') {
            $candidates = $query()
                ->where('method', PayoutMethod::PixQrCode)
                ->where('status', PayoutStatus::Submitting)
                ->whereNull('external_id')
                ->whereNull('authorized_at')
                ->get()
                ->filter(fn (PayoutOrder $order) => round((float) ($order->paid_value ?? $order->value), 2) === round((float) ($object['value'] ?? 0), 2));

            return $candidates->count() === 1 ? $candidates->first() : null;
        }

        return null;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function sameKey(string $a, string $b): bool
    {
        $normalize = function (string $value): string {
            $value = mb_strtolower(preg_replace('/[\s().+-]/', '', $value) ?? '');

            // Telefones podem vir com ou sem o DDI 55.
            return preg_match('/^55\d{10,11}$/', $value) === 1 ? substr($value, 2) : $value;
        };

        return $normalize($a) === $normalize($b);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, refuseReason: string}
     */
    private function refuse(?PayoutOrder $order, string $reason, array $payload): array
    {
        $order?->record('authorization_refused', "Autorização recusada: {$reason}");

        Log::warning('payouts.authorization_refused', [
            'order' => $order?->uuid,
            'reason' => $reason,
            'type' => $payload['type'] ?? null,
        ]);

        return ['status' => 'REFUSED', 'refuseReason' => $reason];
    }
}
