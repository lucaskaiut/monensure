<?php

namespace App\Modules\Payout\Services;

use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Models\PayoutOrder;
use Illuminate\Support\Facades\Log;

/**
 * Eventos do webhook do Asaas (TRANSFER_*, BILL_*, PIX_*).
 *
 * O corpo só identifica a operação: o status aplicado é sempre o retornado
 * por uma consulta à API, nunca o do corpo da notificação.
 */
class PayoutEventService
{
    private const OBJECT_KEYS = ['bill', 'transfer', 'pixTransaction', 'pixQrCode'];

    private const METHOD_KEYS = [
        'bill' => PayoutMethod::Boleto,
        'transfer' => PayoutMethod::PixKey,
        'pixTransaction' => PayoutMethod::PixQrCode,
        'pixQrCode' => PayoutMethod::PixQrCode,
    ];

    public function __construct(private readonly PayoutOrderService $orders) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): bool
    {
        $event = (string) ($payload['event'] ?? '');

        foreach (self::OBJECT_KEYS as $key) {
            $object = $payload[$key] ?? null;

            if (! is_array($object)) {
                continue;
            }

            $order = $this->findOrder($object);

            if ($order === null) {
                return false;
            }

            // Envio com resultado incerto: o evento revela o id criado no Asaas.
            if (blank($order->external_id) && filled($object['id'] ?? null) && self::METHOD_KEYS[$key] === $order->method) {
                $order->forceFill(['external_id' => (string) $object['id']])->save();
            }

            $order->record('webhook', "Evento {$event} recebido do Asaas.", ['event_id' => $payload['id'] ?? null]);

            $this->orders->sync($order);

            return true;
        }

        Log::info('payouts.event_ignored', ['event' => $event]);

        return false;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findOrder(array $object): ?PayoutOrder
    {
        $id = isset($object['id']) ? (string) $object['id'] : null;
        $reference = $object['externalReference'] ?? null;

        return PayoutOrder::query()
            ->withoutTenancy()
            ->where(function ($query) use ($id, $reference): void {
                if (filled($id)) {
                    $query->where('external_id', $id)->orWhere('external_transfer_id', $id);
                }

                if (filled($reference)) {
                    $query->orWhere('uuid', (string) $reference);
                }
            })
            ->when(blank($id) && blank($reference), fn ($query) => $query->whereRaw('1 = 0'))
            ->first();
    }
}
