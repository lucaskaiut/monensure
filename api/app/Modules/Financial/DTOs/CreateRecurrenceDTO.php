<?php

namespace App\Modules\Financial\DTOs;

use App\Modules\Financial\Enums\RecurrenceFrequency;

/**
 * Dados para criação de uma recorrência financeira.
 */
final readonly class CreateRecurrenceDTO
{
    public function __construct(
        public string $description,
        public string $defaultValue,
        public int $dueDay,
        public RecurrenceFrequency $frequency,
        public ?string $supplierId = null,
        public ?string $categoryId = null,
        public bool $active = true,
        public bool $generateAutomatically = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) $data['description'],
            defaultValue: number_format((float) $data['default_value'], 2, '.', ''),
            dueDay: (int) $data['due_day'],
            frequency: RecurrenceFrequency::from($data['frequency']),
            supplierId: isset($data['supplier_id']) ? (string) $data['supplier_id'] : null,
            categoryId: isset($data['category_id']) ? (string) $data['category_id'] : null,
            active: (bool) ($data['active'] ?? true),
            generateAutomatically: (bool) ($data['generate_automatically'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'default_value' => $this->defaultValue,
            'due_day' => $this->dueDay,
            'frequency' => $this->frequency->value,
            'active' => $this->active,
            'generate_automatically' => $this->generateAutomatically,
        ];
    }
}
