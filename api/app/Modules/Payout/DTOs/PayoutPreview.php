<?php

namespace App\Modules\Payout\DTOs;

use App\Modules\Payout\Enums\PayoutMethod;

/**
 * Resultado normalizado da validação de um instrumento de pagamento no Asaas
 * (simulação de boleto ou decodificação de QR Code PIX).
 */
final readonly class PayoutPreview
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public PayoutMethod $method,
        public string $paymentCode,
        public ?string $beneficiaryName,
        public ?string $beneficiaryDocument,
        public ?float $value,
        public ?string $dueDate,
        public bool $canPay,
        public ?string $cannotPayReason,
        public bool $allowsCustomValue,
        public ?float $fee,
        public array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method->value,
            'payment_code' => $this->paymentCode,
            'beneficiary_name' => $this->beneficiaryName,
            'beneficiary_document' => $this->beneficiaryDocument,
            'value' => $this->value,
            'due_date' => $this->dueDate,
            'can_pay' => $this->canPay,
            'cannot_pay_reason' => $this->cannotPayReason,
            'allows_custom_value' => $this->allowsCustomValue,
            'fee' => $this->fee,
        ];
    }
}
