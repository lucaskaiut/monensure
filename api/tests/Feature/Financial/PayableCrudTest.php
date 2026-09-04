<?php

namespace Tests\Feature\Financial;

use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Models\PayableInstallmentGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PayableCrudTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_index_lists_only_payables_of_the_current_tenant(): void
    {
        [$umbrellaA, $childA] = $this->createOperationalChild();
        [, $childB] = $this->createOperationalChild();

        Payable::factory()->forTenant($childA)->count(2)->create();
        Payable::factory()->forTenant($childB)->count(3)->create();

        Sanctum::actingAs($this->createAdmin($childA));

        $response = $this->getJson('/api/financial/payables')->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_index_returns_value_total_for_filtered_payables(): void
    {
        [, $child] = $this->createOperationalChild();

        Payable::factory()->forTenant($child)->create(['value' => 100]);
        Payable::factory()->forTenant($child)->create(['value' => 250.5]);

        Sanctum::actingAs($this->createAdmin($child));

        $response = $this->getJson('/api/financial/payables')->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
        $this->assertEquals(350.5, $response->json('meta.value_total'));
    }

    public function test_store_creates_payable_bound_to_tenant_and_audits(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);

        Sanctum::actingAs($admin);

        $this->postJson('/api/financial/payables', [
            'description' => 'Internet Vivo',
            'value' => 120,
            'due_date' => '2026-09-10',
        ])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Internet Vivo')
            ->assertJsonPath('data.status', 'pendente');

        $this->assertDatabaseHas('payables', [
            'description' => 'Internet Vivo',
            'tenant_id' => $child->getKey(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payable.created',
            'entity_type' => 'payable',
        ]);
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        [$umbrellaA, $childA] = $this->createOperationalChild();
        [, $childB] = $this->createOperationalChild();

        $mine = Payable::factory()->forTenant($childA)->create();
        $foreign = Payable::factory()->forTenant($childB)->create();

        Sanctum::actingAs($this->createAdmin($childA));

        $this->getJson("/api/financial/payables/{$mine->uuid}")->assertOk();
        $this->getJson("/api/financial/payables/{$foreign->uuid}")->assertNotFound();
    }

    public function test_store_links_supplier_and_category_by_uuid(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $supplier = \App\Modules\Financial\Models\Supplier::factory()->forTenant($child)->create();
        $category = \App\Modules\Financial\Models\Category::factory()->forTenant($child)->create();

        $this->postJson('/api/financial/payables', [
            'description' => 'Conta vinculada',
            'value' => 300,
            'due_date' => '2026-09-20',
            'supplier_id' => $supplier->uuid,
            'category_id' => $category->uuid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.supplier.id', $supplier->uuid)
            ->assertJsonPath('data.category.id', $category->uuid);

        $this->assertDatabaseHas('payables', [
            'supplier_id' => $supplier->getKey(),
            'category_id' => $category->getKey(),
        ]);
    }

    public function test_member_without_permission_cannot_manage_payables(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();

        Sanctum::actingAs($this->createMember($child));

        $this->getJson('/api/financial/payables')->assertForbidden();
        $this->postJson('/api/financial/payables', [
            'description' => 'X',
            'value' => 1,
            'due_date' => '2026-09-10',
        ])->assertForbidden();
    }

    public function test_update_single_payable_returns_loaded_relations(): void
    {
        [, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $payable = Payable::factory()->forTenant($child)->create([
            'description' => 'Aluguel',
            'value' => 1500,
        ]);

        $this->putJson("/api/financial/payables/{$payable->uuid}", [
            'description' => 'Aluguel atualizado',
            'value' => 1600,
            'due_date' => $payable->due_date->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.payables.0.description', 'Aluguel atualizado')
            ->assertJsonPath('data.payables.0.value', '1600.00');

        $this->assertDatabaseHas('payables', [
            'uuid' => $payable->uuid,
            'description' => 'Aluguel atualizado',
        ]);
    }

    public function test_batch_edit_this_and_next(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        $group = PayableInstallmentGroup::factory()->forTenant($child)->create();

        for ($i = 1; $i <= 3; $i++) {
            Payable::factory()->forTenant($child)->create([
                'installment_group_uuid' => $group->uuid,
                'installment_number' => $i,
                'installment_total' => 3,
            ]);
        }

        $second = Payable::query()
            ->where('installment_group_uuid', $group->uuid)
            ->where('installment_number', 2)
            ->first();

        $this->putJson("/api/financial/payables/{$second->uuid}", [
            'scope' => 'this_and_next',
            'value' => 99,
        ])->assertOk()->assertJsonPath('data.updated', 2);

        $numbers = Payable::query()
            ->where('installment_group_uuid', $group->uuid)
            ->orderBy('installment_number')
            ->pluck('value', 'installment_number');

        // parcela 1 permanece original
        $this->assertNotSame('99.00', $numbers[1]);
        $this->assertSame('99.00', $numbers[2]);
        $this->assertSame('99.00', $numbers[3]);
    }
}
