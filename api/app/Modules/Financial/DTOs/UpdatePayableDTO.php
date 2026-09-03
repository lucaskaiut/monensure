<?php

namespace App\Modules\Financial\DTOs;

use App\Modules\Financial\Enums\PayableEditScope;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dados para edição de conta(s) a pagar, com escopo de aplicação em parcelas.
 */
final readonly class UpdatePayableDTO
{
    /**
     * @param  array<string, mixed>  $present  Campos que devem ser alterados.
     */
    public function __construct(
        public PayableEditScope $scope,
        public ?string $description = null,
        public ?string $value = null,
        public ?CarbonInterface $dueDate = null,
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
            scope: PayableEditScope::from((string) ($data['scope'] ?? PayableEditScope::This->value)),
            description: isset($data['description']) ? (string) $data['description'] : null,
            value: isset($data['value']) ? number_format((float) $data['value'], 2, '.', '') : null,
            dueDate: isset($data['due_date']) && $data['due_date'] !== null
                ? ($data['due_date'] instanceof CarbonInterface
                    ? $data['due_date']
                    : CarbonImmutable::parse($data['due_date']))
                : null,
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
