<?php

namespace Database\Factories;

use App\Modules\Financial\Models\Supplier;
use App\Modules\Shared\Support\Document;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'document' => Document::fakeCnpj(),
            'phone' => fake()->numerify('419########'),
            'email' => fake()->unique()->companyEmail(),
            'observations' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }
}
