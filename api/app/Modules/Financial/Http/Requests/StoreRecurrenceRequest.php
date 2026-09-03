<?php

namespace App\Modules\Financial\Http\Requests;

use App\Modules\Financial\Enums\RecurrenceFrequency;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurrenceRequest extends FormRequest
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
            'default_value' => ['required', 'numeric', 'min:0.01'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'frequency' => ['required', 'string', Rule::enum(RecurrenceFrequency::class)],
            'supplier_id' => ['nullable', 'string', $this->supplierExists()],
            'category_id' => ['nullable', 'string', $this->categoryExists()],
            'active' => ['sometimes', 'boolean'],
            'generate_automatically' => ['sometimes', 'boolean'],
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
