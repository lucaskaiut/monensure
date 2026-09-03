<?php

namespace App\Modules\Financial\Services;

use App\Modules\Financial\Models\Payable;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FinancialOverviewService
{
    /**
     * Widgets do dashboard financeiro.
     *
     * @return array{
     *     overdue: array{count: int, total: float},
     *     due_today: array{count: int, total: float},
     *     next_7_days: array{count: int, total: float},
     *     next_30_days: array{count: int, total: float},
     *     open_total: float
     * }
     */
    public function summary(?Carbon $today = null): array
    {
        $today = ($today ?? now())->startOfDay();

        return [
            'overdue' => $this->aggregate(fn ($q) => $q->whereDate('due_date', '<', $today)),
            'due_today' => $this->aggregate(fn ($q) => $q->whereDate('due_date', $today)),
            'next_7_days' => $this->aggregate(fn ($q) => $q
                ->whereDate('due_date', '>', $today)
                ->whereDate('due_date', '<=', $today->copy()->addDays(7))),
            'next_30_days' => $this->aggregate(fn ($q) => $q
                ->whereDate('due_date', '>', $today)
                ->whereDate('due_date', '<=', $today->copy()->addDays(30))),
            'open_total' => (float) Payable::query()->pending()->sum('value'),
        ];
    }

    /**
     * Fluxo de caixa futuro por janelas de horizonte.
     *
     * @return array{
     *     overdue: array{count: int, total: float},
     *     windows: list<array{horizon_days: int, label: string, count: int, total: float}>
     * }
     */
    public function projection(?Carbon $today = null): array
    {
        $today = ($today ?? now())->startOfDay();

        $windows = [];
        $horizons = [
            0 => 'Hoje',
            7 => '7 dias',
            15 => '15 dias',
            30 => '30 dias',
            60 => '60 dias',
            90 => '90 dias',
        ];

        foreach ($horizons as $days => $label) {
            $end = $today->copy()->addDays($days);

            $windows[] = [
                'horizon_days' => $days,
                'label' => $label,
                ...$this->aggregate(fn ($q) => $q
                    ->whereDate('due_date', '>=', $today)
                    ->whereDate('due_date', '<=', $end)),
            ];
        }

        return [
            'overdue' => $this->aggregate(fn ($q) => $q->whereDate('due_date', '<', $today)),
            'windows' => $windows,
        ];
    }

    /**
     * Contas vencidas (relatório), com filtros por fornecedor e categoria.
     *
     * @param  array{supplier_id?: ?int, category_id?: ?int}  $filters
     */
    public function overdue(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Payable::query()
            ->with(['supplier:id,uuid,name', 'category:id,uuid,name'])
            ->pending()
            ->whereDate('due_date', '<', now()->toDateString())
            ->when(filled($filters['supplier_id'] ?? null), fn ($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(filled($filters['category_id'] ?? null), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->orderBy('due_date')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  callable(Builder): void  $constraint
     * @return array{count: int, total: float}
     */
    private function aggregate(callable $constraint): array
    {
        $query = Payable::query()->pending();

        $constraint($query);

        $rows = $query->get();

        return [
            'count' => $rows->count(),
            'total' => (float) $rows->sum('value'),
        ];
    }
}
