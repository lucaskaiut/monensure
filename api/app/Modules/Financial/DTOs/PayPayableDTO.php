<?php

namespace App\Modules\Financial\DTOs;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dados para o pagamento de uma conta a pagar (permite divergência de valor).
 */
final readonly class PayPayableDTO
{
    public function __construct(
        public CarbonInterface $paidAt,
        public string $paidValue,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paidAt: $data['paid_at'] instanceof CarbonInterface
                ? $data['paid_at']
                : CarbonImmutable::parse($data['paid_at']),
            paidValue: number_format((float) $data['paid_value'], 2, '.', ''),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
