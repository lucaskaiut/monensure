<?php

namespace App\Modules\Financial\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Financial\DTOs\CreateRecurrenceDTO;
use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Financial\Models\FinancialRecurrence;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Support\ResolvesRelations;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RecurrenceService
{
    use ResolvesRelations;

    public function __construct(private readonly FinancialAuditService $audit) {}

    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return FinancialRecurrence::query()
            ->with(['supplier:id,uuid,name', 'category:id,uuid,name'])
            ->when(filled($search), fn ($q) => $q->where('description', 'like', "%{$search}%"))
            ->orderBy('description')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return Collection<int, FinancialRecurrence>
     */
    public function all(): Collection
    {
        return FinancialRecurrence::query()->orderBy('description')->get();
    }

    public function create(CreateRecurrenceDTO $dto, ?User $actor = null): FinancialRecurrence
    {
        $recurrence = FinancialRecurrence::query()->create([
            ...$dto->toArray(),
            'supplier_id' => $this->resolveSupplierId($dto->supplierId),
            'category_id' => $this->resolveCategoryId($dto->categoryId),
        ]);

        $this->audit->record($actor, AuditAction::RecurrenceCreated, 'financial_recurrence', $recurrence->uuid);

        return $recurrence;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialRecurrence $recurrence, array $data, ?User $actor = null): FinancialRecurrence
    {
        $recurrence->fill($data);
        $recurrence->save();

        $this->audit->record($actor, AuditAction::RecurrenceUpdated, 'financial_recurrence', $recurrence->uuid);

        return $recurrence->refresh();
    }

    public function delete(FinancialRecurrence $recurrence, ?User $actor = null): void
    {
        $recurrence->delete();

        $this->audit->record($actor, AuditAction::RecurrenceDeleted, 'financial_recurrence', $recurrence->uuid);
    }

    /**
     * Gera os lançamentos futuros (3 meses) de todas as recorrências ativas.
     *
     * Executado diariamente pelo job. Opera em todos os tenants — por isso
     * desativa o escopo global e vincula o tenant_id explicitamente.
     */
    public function generateUpcomingPayables(?Carbon $now = null): int
    {
        $now = ($now ?? now())->copy()->startOfDay();
        $horizon = $now->copy()->addMonthsNoOverflow(3);

        $recurrences = FinancialRecurrence::query()
            ->withoutTenancy()
            ->where('active', true)
            ->where('generate_automatically', true)
            ->get();

        $generated = 0;

        foreach ($recurrences as $recurrence) {
            $due = $this->nextDueDate($recurrence, $now);

            while ($due->lte($horizon)) {
                if ($this->createIfMissing($recurrence, $due)) {
                    $generated++;
                }

                $due = $this->dueDateForMonth(
                    $recurrence,
                    $due->copy()->addMonthsNoOverflow($recurrence->frequency->months())->startOfMonth(),
                );
            }

            $recurrence->last_generated_at = $horizon->toDateString();
            $recurrence->save();
        }

        return $generated;
    }

    private function createIfMissing(FinancialRecurrence $recurrence, CarbonImmutable $due): bool
    {
        $exists = Payable::query()
            ->withoutTenancy()
            ->where('tenant_id', $recurrence->tenant_id)
            ->where('recurrence_id', $recurrence->getKey())
            ->whereDate('due_date', $due->toDateString())
            ->exists();

        if ($exists) {
            return false;
        }

        Payable::query()->create([
            'tenant_id' => $recurrence->tenant_id,
            'recurrence_id' => $recurrence->getKey(),
            'description' => $recurrence->description,
            'value' => $recurrence->default_value,
            'due_date' => $due->toDateString(),
            'issue_date' => $due->toDateString(),
            'supplier_id' => $recurrence->supplier_id,
            'category_id' => $recurrence->category_id,
            'status' => PayableStatus::Pending,
        ]);

        return true;
    }

    private function nextDueDate(FinancialRecurrence $recurrence, CarbonInterface $ref): CarbonImmutable
    {
        $candidate = $this->dueDateForMonth($recurrence, $ref->copy()->startOfMonth());

        if ($candidate->lt($ref)) {
            $candidate = $this->dueDateForMonth(
                $recurrence,
                $ref->copy()->addMonthNoOverflow()->startOfMonth(),
            );
        }

        return $candidate;
    }

    private function dueDateForMonth(FinancialRecurrence $recurrence, CarbonInterface $month): CarbonImmutable
    {
        $day = min($recurrence->due_day, $month->daysInMonth);

        return CarbonImmutable::parse($month->copy()->day($day)->toDateString());
    }
}
