<?php

namespace Tests\Feature\Financial;

use App\Modules\Financial\Enums\RecurrenceFrequency;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_generate_upcoming_payables_creates_twelve_months(): void
    {
        Carbon::setTestNow('2026-09-02');

        $tenant = $this->createTenantWithRoles();

        FinancialRecurrence::factory()->forTenant($tenant)->create([
            'description' => 'Internet Vivo',
            'default_value' => 120,
            'due_day' => 10,
            'frequency' => RecurrenceFrequency::Monthly,
        ]);

        $generated = app(RecurrenceService::class)->generateUpcomingPayables();

        $this->assertSame(12, $generated);

        $dueDates = Payable::query()
            ->withoutTenancy()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('due_date')
            ->pluck('due_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $this->assertSame([
            '2026-09-10',
            '2026-10-10',
            '2026-11-10',
            '2026-12-10',
            '2027-01-10',
            '2027-02-10',
            '2027-03-10',
            '2027-04-10',
            '2027-05-10',
            '2027-06-10',
            '2027-07-10',
            '2027-08-10',
        ], $dueDates);
    }

    public function test_generate_is_idempotent(): void
    {
        Carbon::setTestNow('2026-09-02');

        $tenant = $this->createTenantWithRoles();

        FinancialRecurrence::factory()->forTenant($tenant)->create([
            'due_day' => 5,
            'frequency' => RecurrenceFrequency::Monthly,
        ]);

        $service = app(RecurrenceService::class);

        $service->generateUpcomingPayables();
        $secondRun = $service->generateUpcomingPayables();

        $this->assertSame(0, $secondRun);
        $this->assertSame(12, Payable::query()->withoutTenancy()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_generate_respects_tenant_isolation_and_inactive_recurrences(): void
    {
        Carbon::setTestNow('2026-09-02');

        $tenantA = $this->createTenantWithRoles();
        $tenantB = $this->createTenantWithRoles(['domain' => 'outro.com.br']);

        FinancialRecurrence::factory()->forTenant($tenantA)->create(['due_day' => 10]);
        FinancialRecurrence::factory()->forTenant($tenantB)->inactive()->create(['due_day' => 10]);

        app(RecurrenceService::class)->generateUpcomingPayables();

        $this->assertSame(12, Payable::query()->withoutTenancy()->where('tenant_id', $tenantA->getKey())->count());
        $this->assertSame(0, Payable::query()->withoutTenancy()->where('tenant_id', $tenantB->getKey())->count());
    }
}
