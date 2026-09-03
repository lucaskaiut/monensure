<?php

namespace Database\Factories;

use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Financial\Models\Payable;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payable>
 */
class PayableFactory extends Factory
{
    protected $model = Payable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'description' => 'Conta '.fake()->unique()->word(),
            'value' => fake()->randomFloat(2, 10, 5000),
            'due_date' => now()->addDays(10)->toDateString(),
            'issue_date' => now()->toDateString(),
            'status' => PayableStatus::Pending,
            'supplier_id' => null,
            'category_id' => null,
            'recurrence_id' => null,
            'installment_group_uuid' => null,
            'installment_number' => null,
            'installment_total' => null,
            'paid_at' => null,
            'paid_value' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn (): array => [
            'due_date' => $date,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => PayableStatus::Pending]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PayableStatus::Paid,
            'paid_at' => now()->toDateString(),
            'paid_value' => fn (array $attributes) => $attributes['value'] ?? 0,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => PayableStatus::Cancelled]);
    }
}
