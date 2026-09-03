<?php

namespace App\Modules\Financial\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Financial\DTOs\CreateInstallmentPlanDTO;
use App\Modules\Financial\DTOs\CreatePayableDTO;
use App\Modules\Financial\DTOs\PayPayableDTO;
use App\Modules\Financial\DTOs\UpdatePayableDTO;
use App\Modules\Financial\Enums\PayableEditScope;
use App\Modules\Financial\Enums\PayableStatus;
use App\Modules\Financial\Models\Payable;
use App\Modules\Financial\Models\PayableInstallmentGroup;
use App\Modules\Financial\Models\PayablePayment;
use App\Modules\Financial\Support\ResolvesRelations;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PayableService
{
    use ResolvesRelations;

    public function __construct(private readonly FinancialAuditService $audit) {}

    /**
     * @param  array{status?: ?string, supplier_id?: ?int, category_id?: ?int, from?: ?string, to?: ?string, search?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Payable::query()
            ->with(['supplier:id,uuid,name', 'category:id,uuid,name'])
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['supplier_id'] ?? null), fn ($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(filled($filters['category_id'] ?? null), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->whereDate('due_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->whereDate('due_date', '<=', $filters['to']))
            ->when(filled($filters['search'] ?? null), fn ($q) => $q->where('description', 'like', "%{$filters['search']}%"))
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(CreatePayableDTO $dto, ?User $actor = null): Payable
    {
        $payable = Payable::query()->create([
            'description' => $dto->description,
            'value' => $dto->value,
            'due_date' => $dto->dueDate->toDateString(),
            'issue_date' => $dto->issueDate?->toDateString(),
            'supplier_id' => $this->resolveSupplierId($dto->supplierId),
            'category_id' => $this->resolveCategoryId($dto->categoryId),
            'status' => PayableStatus::Pending,
        ]);

        $this->audit->record($actor, AuditAction::PayableCreated, 'payable', $payable->uuid);

        return $payable;
    }

    public function createInstallmentPlan(CreateInstallmentPlanDTO $dto, ?User $actor = null): PayableInstallmentGroup
    {
        $lastNumber = $dto->firstInstallmentNumber + $dto->totalInstallments - 1;

        $group = PayableInstallmentGroup::query()->create([
            'description' => $dto->description,
            'total_value' => $dto->value,
            'total_installments' => $dto->totalInstallments,
            'first_installment_number' => $dto->firstInstallmentNumber,
            'first_due_date' => $dto->firstDueDate->toDateString(),
            'supplier_id' => $this->resolveSupplierId($dto->supplierId),
            'category_id' => $this->resolveCategoryId($dto->categoryId),
        ]);

        $supplierId = $this->resolveSupplierId($dto->supplierId);
        $categoryId = $this->resolveCategoryId($dto->categoryId);

        for ($i = 0; $i < $dto->totalInstallments; $i++) {
            Payable::query()->create([
                'installment_group_uuid' => $group->uuid,
                'installment_number' => $dto->firstInstallmentNumber + $i,
                'installment_total' => $lastNumber,
                'description' => $dto->description,
                'value' => $dto->value,
                'due_date' => $dto->firstDueDate->copy()->addMonthsNoOverflow($i)->toDateString(),
                'issue_date' => $dto->issueDate?->toDateString(),
                'supplier_id' => $supplierId,
                'category_id' => $categoryId,
                'status' => PayableStatus::Pending,
            ]);
        }

        $this->audit->record($actor, AuditAction::PayableCreated, 'payable_installment_group', $group->uuid, [
            'installments' => $dto->totalInstallments,
            'from' => $dto->firstInstallmentNumber,
            'to' => $lastNumber,
        ]);

        return $group->load('payables');
    }

    /**
     * @return Collection<int, Payable>
     */
    public function update(Payable $payable, UpdatePayableDTO $dto, ?User $actor = null): Collection
    {
        $targets = $this->resolveTargets($payable, $dto->scope);

        $changes = array_filter([
            'description' => $dto->description,
            'value' => $dto->value,
            'supplier_id' => $dto->supplierId !== null ? $this->resolveSupplierId($dto->supplierId) : null,
            'category_id' => $dto->categoryId !== null ? $this->resolveCategoryId($dto->categoryId) : null,
            'issue_date' => $dto->issueDate?->toDateString(),
        ], fn ($value) => $value !== null);

        foreach ($targets as $target) {
            $target->fill($changes);

            if ($dto->dueDate !== null && $dto->scope === PayableEditScope::This) {
                $target->due_date = $dto->dueDate->toDateString();
            }

            $target->save();
        }

        $this->audit->record($actor, AuditAction::PayableUpdated, 'payable', $payable->uuid, [
            'scope' => $dto->scope->value,
            'affected' => $targets->count(),
        ]);

        return $targets;
    }

    public function pay(Payable $payable, PayPayableDTO $dto, ?User $actor = null): Payable
    {
        if ($payable->status === PayableStatus::Cancelled) {
            throw ValidationException::withMessages([
                'payable' => 'Não é possível pagar uma conta cancelada.',
            ]);
        }

        $difference = bcsub($dto->paidValue, $payable->value, 2);

        PayablePayment::query()->create([
            'payable_id' => $payable->getKey(),
            'paid_at' => $dto->paidAt->toDateString(),
            'paid_value' => $dto->paidValue,
            'difference' => $difference,
            'notes' => $dto->notes,
        ]);

        $payable->status = PayableStatus::Paid;
        $payable->paid_at = $dto->paidAt->toDateString();
        $payable->paid_value = $dto->paidValue;
        $payable->save();

        $this->audit->record($actor, AuditAction::PayablePaid, 'payable', $payable->uuid, [
            'paid_value' => $dto->paidValue,
            'difference' => $difference,
        ]);

        return $payable->refresh()->load('payments');
    }

    public function cancel(Payable $payable, ?PayableEditScope $scope = null, ?User $actor = null): int
    {
        $targets = $scope !== null
            ? $this->resolveTargets($payable, $scope)
            : collect([$payable]);

        $affected = 0;

        foreach ($targets as $target) {
            if ($target->status === PayableStatus::Pending) {
                $target->status = PayableStatus::Cancelled;
                $target->save();
                $affected++;
            }
        }

        $this->audit->record($actor, AuditAction::PayableCancelled, 'payable', $payable->uuid, [
            'scope' => $scope?->value,
            'affected' => $affected,
        ]);

        return $affected;
    }

    public function destroy(Payable $payable, ?User $actor = null): void
    {
        $payable->delete();

        $this->audit->record($actor, AuditAction::PayableDeleted, 'payable', $payable->uuid);
    }

    /**
     * @return Collection<int, Payable>
     */
    private function resolveTargets(Payable $payable, PayableEditScope $scope): Collection
    {
        if (! $payable->isInstallment() || $scope === PayableEditScope::This) {
            return collect([$payable]);
        }

        $query = Payable::query()->where('installment_group_uuid', $payable->installment_group_uuid);

        if ($scope === PayableEditScope::ThisAndNext) {
            $query->where('installment_number', '>=', $payable->installment_number);
        }

        return $query->orderBy('installment_number')->get();
    }
}
