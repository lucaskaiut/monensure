<?php

namespace Tests\Feature\Financial;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\Financial\Models\Category;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinancialMcpServerTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private const ENDPOINT = '/api/mcp/financial';

    public function test_requests_without_token_are_rejected(): void
    {
        $this->rpc('tools/list')->assertUnauthorized();
    }

    public function test_invalid_api_token_is_rejected(): void
    {
        $this->rpc('tools/list', [], ApiToken::PREFIX.Str::random(48))->assertUnauthorized();
    }

    public function test_initialize_handshake_works_with_api_token(): void
    {
        [, $child] = $this->createOperationalChild();

        $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ], $this->issueToken($child))
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Monensure Financeiro');
    }

    public function test_full_access_token_lists_every_tool(): void
    {
        [, $child] = $this->createOperationalChild();

        $names = $this->toolNames($this->rpc('tools/list', [], $this->issueToken($child)));

        $this->assertCount(27, $names);
        $this->assertContains('create_payable', $names);
        $this->assertContains('generate_recurring_payables', $names);
        $this->assertContains('delete_category', $names);
    }

    public function test_scoped_token_only_sees_and_runs_permitted_tools(): void
    {
        [, $child] = $this->createOperationalChild();
        $token = $this->issueToken($child, [Permission::PAYABLE_READ->value]);

        $names = $this->toolNames($this->rpc('tools/list', [], $token));

        sort($names);
        $this->assertSame(['get_financial_summary', 'get_payable', 'list_overdue_payables', 'list_payables'], $names);

        $this->callTool('create_payable', [
            'description' => 'Proibida',
            'value' => 10,
            'due_date' => '2026-12-01',
        ], $token)->assertJsonPath('error.code', -32602);

        $this->assertDatabaseMissing('payables', ['description' => 'Proibida']);
    }

    public function test_user_permissions_are_enforced_via_sanctum(): void
    {
        [, $child] = $this->createOperationalChild();

        Sanctum::actingAs($this->createMember($child));

        $this->assertSame([], $this->toolNames($this->rpc('tools/list')));
    }

    public function test_admin_user_can_operate_and_actions_are_audited(): void
    {
        [, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);
        $payable = Payable::factory()->forTenant($child)->pending()->create(['value' => 100]);

        Sanctum::actingAs($admin);

        $this->callTool('pay_payable', ['id' => $payable->uuid, 'paid_value' => 105.5])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.payable.status', 'pago');

        $this->assertDatabaseHas('payable_payments', ['payable_id' => $payable->id, 'paid_value' => 105.5, 'difference' => 5.5]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payable.paid', 'user_id' => $admin->id]);
    }

    public function test_token_can_manage_suppliers_categories_and_payables(): void
    {
        [, $child] = $this->createOperationalChild();
        $token = $this->issueToken($child);

        $supplier = $this->callTool('create_supplier', [
            'name' => 'Vivo',
            'document' => '11.222.333/0001-81',
        ], $token)->assertJsonPath('result.isError', false)->json('result.structuredContent.supplier');

        $this->assertSame('11222333000181', $supplier['document']);

        $parent = $this->callTool('create_category', ['name' => 'Utilidades'], $token)
            ->json('result.structuredContent.category');
        $category = $this->callTool('create_category', ['name' => 'Internet', 'parent_id' => $parent['id']], $token)
            ->json('result.structuredContent.category');

        $this->assertSame($parent['id'], $category['parent_id']);

        $this->callTool('create_payable', [
            'description' => 'Internet outubro',
            'value' => 120,
            'due_date' => '2026-11-10',
            'supplier_id' => $supplier['id'],
            'category_id' => $category['id'],
        ], $token)->assertJsonPath('result.structuredContent.payable.supplier.name', 'Vivo');

        Payable::factory()->forTenant($child)->create(['value' => 30]);

        $list = $this->callTool('list_payables', ['supplier_id' => $supplier['id']], $token);

        $list->assertJsonPath('result.structuredContent.meta.total', 1)
            ->assertJsonPath('result.structuredContent.data.0.description', 'Internet outubro');
        $this->assertEquals(120, $list->json('result.structuredContent.meta.value_total'));

        $tree = $this->callTool('list_categories', ['format' => 'tree'], $token)
            ->json('result.structuredContent.data');

        $this->assertSame('Internet', $tree[0]['children'][0]['name']);
    }

    public function test_installment_plan_and_scoped_cancel(): void
    {
        [, $child] = $this->createOperationalChild();
        $token = $this->issueToken($child);

        $payables = $this->callTool('create_installment_plan', [
            'description' => 'Notebook',
            'value' => 500,
            'first_due_date' => '2026-11-05',
            'first_installment_number' => 1,
            'total_installments' => 4,
        ], $token)->json('result.structuredContent.payables');

        $this->assertCount(4, $payables);

        $this->callTool('cancel_payable', ['id' => $payables[1]['id'], 'scope' => 'this_and_next'], $token)
            ->assertJsonPath('result.structuredContent.cancelled', 3);
    }

    public function test_recurrence_lifecycle(): void
    {
        [, $child] = $this->createOperationalChild();
        $token = $this->issueToken($child);
        $supplier = Supplier::factory()->forTenant($child)->create();
        $category = Category::factory()->forTenant($child)->create();

        $created = $this->callTool('create_recurrence', [
            'description' => 'Aluguel',
            'default_value' => 2000,
            'due_day' => 5,
            'frequency' => 'mensal',
        ], $token)->assertJsonPath('result.isError', false);

        $this->assertGreaterThanOrEqual(12, $created->json('result.structuredContent.generated_payables'));

        $id = $created->json('result.structuredContent.recurrence.id');

        $this->callTool('update_recurrence', [
            'id' => $id,
            'supplier_id' => $supplier->uuid,
            'category_id' => $category->uuid,
            'active' => false,
        ], $token)
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.recurrence.supplier.id', $supplier->uuid)
            ->assertJsonPath('result.structuredContent.recurrence.active', false);

        $this->assertDatabaseHas('financial_recurrences', [
            'uuid' => $id,
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
        ]);

        $this->callTool('generate_recurring_payables', [], $token)
            ->assertJsonPath('result.structuredContent.generated', 0);

        $this->callTool('delete_recurrence', ['id' => $id], $token)->assertJsonPath('result.isError', false);
        $this->assertSoftDeleted(FinancialRecurrence::withoutGlobalScopes()->where('uuid', $id)->first());
    }

    public function test_overview_tools_return_aggregates(): void
    {
        [, $child] = $this->createOperationalChild();
        $token = $this->issueToken($child);

        Payable::factory()->forTenant($child)->pending()->dueOn(now()->subDays(3)->toDateString())->create(['value' => 50]);
        Payable::factory()->forTenant($child)->pending()->dueOn(now()->addDays(3)->toDateString())->create(['value' => 70]);

        $this->callTool('get_financial_summary', [], $token)
            ->assertJsonPath('result.structuredContent.overdue.count', 1)
            ->assertJsonPath('result.structuredContent.next_7_days.count', 1);

        $this->callTool('get_cashflow_projection', [], $token)
            ->assertJsonPath('result.structuredContent.windows.0.label', 'Hoje');

        $this->callTool('list_overdue_payables', [], $token)
            ->assertJsonPath('result.structuredContent.meta.total', 1);
    }

    public function test_resources_of_other_tenants_are_not_accessible(): void
    {
        [, $childA] = $this->createOperationalChild();
        [, $childB] = $this->createOperationalChild();

        $payable = Payable::factory()->forTenant($childA)->pending()->create();

        $tokenB = $this->issueToken($childB);

        $this->callTool('get_payable', ['id' => $payable->uuid], $tokenB)
            ->assertJsonPath('result.isError', true);

        $this->callTool('delete_payable', ['id' => $payable->uuid], $tokenB)
            ->assertJsonPath('result.isError', true);

        $this->assertNotSoftDeleted($payable);
    }

    public function test_validation_errors_are_reported_as_tool_errors(): void
    {
        [, $child] = $this->createOperationalChild();

        $response = $this->callTool('create_payable', ['description' => 'Sem valor'], $this->issueToken($child))
            ->assertJsonPath('result.isError', true);

        $this->assertStringContainsString('value', $response->json('result.content.0.text'));
    }

    public function test_umbrella_tenant_token_is_blocked(): void
    {
        $umbrella = $this->createTenantWithRoles();

        $this->rpc('tools/list', [], $this->issueToken($umbrella))->assertForbidden();
    }

    /**
     * @param  list<string>|null  $permissions
     */
    private function issueToken(Tenant $tenant, ?array $permissions = null): string
    {
        $plain = ApiToken::PREFIX.Str::random(48);

        ApiToken::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'token_hash' => ApiToken::hash($plain),
            'permissions' => $permissions,
        ]);

        $this->forgetTenantContext();

        return $plain;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function rpc(string $method, array $params = [], ?string $token = null): TestResponse
    {
        $this->forgetTenantContext();

        $headers = $token !== null ? ['Authorization' => "Bearer {$token}"] : [];

        return $this->postJson(self::ENDPOINT, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => (object) $params,
        ], $headers);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(string $name, array $arguments = [], ?string $token = null): TestResponse
    {
        return $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $token);
    }

    /**
     * @return list<string>
     */
    private function toolNames(TestResponse $response): array
    {
        $response->assertOk();

        return array_column($response->json('result.tools') ?? [], 'name');
    }
}
