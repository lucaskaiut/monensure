<?php

namespace App\Modules\Payout\DTOs;

use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PixKeyType;
use Carbon\CarbonImmutable;

final readonly class SchedulePayoutDTO
{
    public function __construct(
        public PayoutMethod $method,
        public string $paymentCode,
        public ?PixKeyType $pixKeyType,
        public ?float $value,
        public ?CarbonImmutable $scheduledFor,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: PayoutMethod::from((string) $data['method']),
            paymentCode: (string) $data['payment_code'],
            pixKeyType: filled($data['pix_key_type'] ?? null) ? PixKeyType::from((string) $data['pix_key_type']) : null,
            value: isset($data['value']) && $data['value'] !== '' ? round((float) $data['value'], 2) : null,
            scheduledFor: filled($data['scheduled_for'] ?? null)
                ? CarbonImmutable::parse((string) $data['scheduled_for'])->utc()
                : null,
        );
    }
}
