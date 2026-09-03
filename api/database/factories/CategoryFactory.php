<?php

namespace Database\Factories;

use App\Modules\Financial\Models\Category;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
            'parent_id' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->getKey(),
        ]);
    }
}
