<?php

namespace Tests\Feature\Financial;

use App\Modules\Financial\Models\Payable;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CashflowTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summary_widgets(): void
    {
        Carbon::setTestNow('2026-09-02');

        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        Payable::factory()->forTenant($child)->dueOn('2026-08-30')->create(['value' => 100]);
        Payable::factory()->forTenant($child)->dueOn('2026-09-02')->create(['value' => 200]);
        Payable::factory()->forTenant($child)->dueOn('2026-09-05')->create(['value' => 50]);
        Payable::factory()->forTenant($child)->dueOn('2026-09-25')->create(['value' => 300]);
        Payable::factory()->forTenant($child)->dueOn('2026-10-30')->create(['value' => 1000]);
        Payable::factory()->forTenant($child)->paid()->create(['value' => 999]);

        $summary = $this->getJson('/api/financial/summary')->assertOk()->json('data');

        $this->assertSame(1, $summary['overdue']['count']);
        $this->assertEquals(100.0, $summary['overdue']['total']);
        $this->assertSame(1, $summary['due_today']['count']);
        $this->assertEquals(200.0, $summary['due_today']['total']);
        $this->assertSame(1, $summary['next_7_days']['count']);
        $this->assertEquals(50.0, $summary['next_7_days']['total']);
        $this->assertSame(2, $summary['next_30_days']['count']);
        $this->assertEquals(350.0, $summary['next_30_days']['total']);
        $this->assertSame(3, $summary['this_month']['count']);
        $this->assertEquals(550.0, $summary['this_month']['total']);
        $this->assertEquals(1650.0, $summary['open_total']);
    }

    public function test_projection_windows(): void
    {
        Carbon::setTestNow('2026-09-02');

        [$umbrella, $child] = $this->createOperationalChild();
        Sanctum::actingAs($this->createAdmin($child));

        Payable::factory()->forTenant($child)->dueOn('2026-08-30')->create(['value' => 100]);
        Payable::factory()->forTenant($child)->dueOn('2026-09-02')->create(['value' => 200]);
        Payable::factory()->forTenant($child)->dueOn('2026-09-05')->create(['value' => 50]);
        Payable::factory()->forTenant($child)->dueOn('2026-09-12')->create(['value' => 300]);
        Payable::factory()->forTenant($child)->dueOn('2026-09-22')->create(['value' => 1000]);

        $windows = $this->getJson('/api/financial/cashflow')
            ->assertOk()
            ->json('data.windows');

        $totals = collect($windows)->mapWithKeys(fn ($w) => [$w['horizon_days'] => $w['total']]);

        $this->assertEquals(200.0, $totals[0]);
        $this->assertEquals(250.0, $totals[7]);
        $this->assertEquals(550.0, $totals[15]);
        $this->assertEquals(1550.0, $totals[30]);

        $this->assertEquals(100.0, $this->getJson('/api/financial/cashflow')->json('data.overdue.total'));
    }
}
