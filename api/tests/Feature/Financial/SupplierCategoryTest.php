<?php

namespace Tests\Feature\Financial;

use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SupplierCategoryTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_supplier_crud_and_tenant_isolation(): void
    {
        [$umbrellaA, $childA] = $this->createOperationalChild();
        [, $childB] = $this->createOperationalChild();

        $admin = $this->createAdmin($childA);
        $foreign = Supplier::factory()->forTenant($childB)->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/financial/suppliers', [
            'name' => 'Vivo',
            'document' => '04.252.011/0001-10',
            'email' => 'vivo@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Vivo')
            ->assertJsonPath('data.document', '04252011000110');

        $this->assertDatabaseHas('suppliers', ['name' => 'Vivo', 'tenant_id' => $childA->getKey()]);

        $this->getJson("/api/financial/suppliers/{$foreign->uuid}")->assertNotFound();
    }

    public function test_category_hierarchy_and_delete_guard(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $moradia = Category::factory()->forTenant($child)->create(['name' => 'Moradia']);
        $agua = Category::factory()->forTenant($child)->childOf($moradia)->create(['name' => 'Água']);

        $this->getJson('/api/financial/categories/tree')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Moradia')
            ->assertJsonPath('data.0.children.0.name', 'Água');

        // Excluir pai com filho deve falhar
        $this->deleteJson("/api/financial/categories/{$moradia->uuid}")->assertUnprocessable();

        // Filho pode ser excluído
        $this->deleteJson("/api/financial/categories/{$agua->uuid}")->assertOk();
    }

    public function test_supplier_validation_requires_valid_document(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $this->postJson('/api/financial/suppliers', [
            'name' => 'Fornecedor X',
            'document' => '123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['document']);
    }
}
