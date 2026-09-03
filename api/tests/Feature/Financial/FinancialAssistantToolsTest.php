<?php

namespace Tests\Feature\Financial;

use App\Modules\Financial\Agents\FinancialAssistantAgent;
use App\Modules\Financial\Models\Payable;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FinancialAssistantToolsTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_agent_registers_all_financial_tools(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);

        $registry = app(FinancialAssistantAgent::class)->tools($admin);

        $names = collect($registry->all())->map->name->all();

        $this->assertSame([
            'create_payable',
            'create_installment_plan',
            'create_recurrence',
            'list_payables',
            'mark_payable_paid',
            'get_cashflow_projection',
            'get_financial_summary',
        ], $names);
    }

    public function test_create_payable_tool(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);
        TenantContext::set($child);

        $registry = app(FinancialAssistantAgent::class)->tools($admin);

        $registry->get('create_payable')->run([
            'description' => 'Internet Vivo',
            'value' => 120,
            'due_date' => '2026-09-10',
        ]);

        $this->assertDatabaseHas('payables', [
            'description' => 'Internet Vivo',
            'tenant_id' => $child->getKey(),
        ]);
    }

    public function test_mark_payable_paid_tool(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);
        TenantContext::set($child);

        $payable = Payable::factory()->forTenant($child)->create(['value' => 100]);

        $registry = app(FinancialAssistantAgent::class)->tools($admin);
        $registry->get('mark_payable_paid')->run([
            'payable_id' => $payable->uuid,
            'paid_value' => 95,
        ]);

        $this->assertSame('pago', $payable->fresh()->status->value);
        $this->assertSame('95.00', $payable->fresh()->paid_value);
    }

    public function test_get_financial_summary_tool(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);
        TenantContext::set($child);

        Payable::factory()->forTenant($child)->create(['value' => 300]);

        $registry = app(FinancialAssistantAgent::class)->tools($admin);
        $summary = $registry->get('get_financial_summary')->run([]);

        $this->assertArrayHasKey('open_total', $summary);
        $this->assertSame(300.0, $summary['open_total']);
    }

    public function test_tool_without_permission_is_rejected(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $member = $this->createMember($child);
        TenantContext::set($child);

        $registry = app(FinancialAssistantAgent::class)->tools($member);

        $this->expectException(\RuntimeException::class);

        $registry->get('create_payable')->run([
            'description' => 'X',
            'value' => 1,
            'due_date' => '2026-09-10',
        ]);
    }
}
