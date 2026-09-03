<?php

namespace App\Modules\Financial\DTOs;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dados para criação de uma conta a pagar avulsa (não parcelada).
 */
final readonly class CreatePayableDTO
{
    public function __construct(
        public string $description,
        public string $value,
        public CarbonInterface $dueDate,
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
            dueDate: $data['due_date'] instanceof CarbonInterface
                ? $data['due_date']
                : CarbonImmutable::parse($data['due_date']),
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
