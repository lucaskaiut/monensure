<?php

namespace App\Modules\Financial\DTOs;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dados para criação de um parcelamento (grupo de parcelas).
 */
final readonly class CreateInstallmentPlanDTO
{
    public function __construct(
        public string $description,
        public string $value,
        public CarbonInterface $firstDueDate,
        public int $firstInstallmentNumber,
        public int $totalInstallments,
        public ?string $supplierId = null,
        public ?string $categoryId = null,
        public ?CarbonInterface $issueDate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) $data['description'],
            value: number_format((float) $data['value'], 2, '.', ''),
            firstDueDate: $data['first_due_date'] instanceof CarbonInterface
                ? $data['first_due_date']
                : CarbonImmutable::parse($data['first_due_date']),
            firstInstallmentNumber: (int) $data['first_installment_number'],
            totalInstallments: (int) $data['total_installments'],
            supplierId: isset($data['supplier_id']) ? (string) $data['supplier_id'] : null,
            categoryId: isset($data['category_id']) ? (string) $data['category_id'] : null,
            issueDate: isset($data['issue_date']) && $data['issue_date'] !== null
                ? ($data['issue_date'] instanceof CarbonInterface
                    ? $data['issue_date']
                    : CarbonImmutable::parse($data['issue_date']))
                : null,
        );
    }
}
