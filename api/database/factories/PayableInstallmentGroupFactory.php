<?php

namespace Database\Factories;

use App\Modules\Financial\Models\PayableInstallmentGroup;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayableInstallmentGroup>
 */
class PayableInstallmentGroupFactory extends Factory
{
    protected $model = PayableInstallmentGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'description' => 'Grupo de parcelas '.fake()->unique()->word(),
            'total_value' => fake()->randomFloat(2, 100, 10000),
            'total_installments' => 3,
            'first_installment_number' => 1,
            'first_due_date' => now()->addMonth()->toDateString(),
            'supplier_id' => null,
            'category_id' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }
}
