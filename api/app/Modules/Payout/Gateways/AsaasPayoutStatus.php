<?php

namespace App\Modules\Payout\Gateways;

use App\Modules\Payout\Enums\PayoutMethod;

/**
 * Traduz o status do Asaas para o desfecho da ordem.
 */
final class AsaasPayoutStatus
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REVERSED = 'reversed';

    public const PENDING = 'pending';

    public static function outcome(PayoutMethod $method, ?string $status): string
    {
        $status = strtoupper((string) $status);

        return match ($method) {
            PayoutMethod::Boleto => match ($status) {
                'PAID' => self::PAID,
                'FAILED', 'CANCELLED' => self::FAILED,
                'REFUNDED' => self::REVERSED,
                default => self::PENDING,
            },
            PayoutMethod::PixQrCode => match ($status) {
                'DONE' => self::PAID,
                'REFUSED', 'CANCELLED' => self::FAILED,
                default => self::PENDING,
            },
            PayoutMethod::PixKey => match ($status) {
                'DONE' => self::PAID,
                'FAILED', 'CANCELLED' => self::FAILED,
                default => self::PENDING,
            },
        };
    }
}
