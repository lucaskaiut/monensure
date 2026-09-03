<?php

namespace Database\Factories;

use App\Modules\Financial\Enums\RecurrenceFrequency;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialRecurrence>
 */
class FinancialRecurrenceFactory extends Factory
{
    protected $model = FinancialRecurrence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'description' => 'Recorrência '.fake()->unique()->word(),
            'default_value' => fake()->randomFloat(2, 20, 1000),
            'due_day' => 10,
            'frequency' => RecurrenceFrequency::Monthly,
            'active' => true,
            'generate_automatically' => true,
            'supplier_id' => null,
            'category_id' => null,
            'last_generated_at' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
