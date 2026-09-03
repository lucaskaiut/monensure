<?php

namespace App\Modules\Financial\Http\Requests;

use App\Modules\Financial\Enums\PayableEditScope;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayableRequest extends FormRequest
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
            'scope' => ['sometimes', 'string', Rule::enum(PayableEditScope::class)],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'due_date' => ['sometimes', 'required', 'date'],
            'issue_date' => ['sometimes', 'nullable', 'date'],
            'supplier_id' => ['sometimes', 'nullable', 'string', $this->supplierExists()],
            'category_id' => ['sometimes', 'nullable', 'string', $this->categoryExists()],
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
