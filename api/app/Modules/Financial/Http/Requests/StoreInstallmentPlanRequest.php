<?php

namespace App\Modules\Financial\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstallmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'first_due_date' => ['required', 'date'],
            'first_installment_number' => ['required', 'integer', 'min:1'],
            'total_installments' => ['required', 'integer', 'min:1', 'max:600'],
            'issue_date' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'string', $this->supplierExists()],
            'category_id' => ['nullable', 'string', $this->categoryExists()],
        ];
    }

    private function supplierExists()
    {
        return Rule::exists('suppliers', 'uuid')
            ->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId()));
    }

    private function categoryExists()
    {
        return Rule::exists('financial_categories', 'uuid')
            ->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId()));
    }
}
