<?php

namespace App\Modules\Financial\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayableRequest extends FormRequest
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
            'due_date' => ['required', 'date'],
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
