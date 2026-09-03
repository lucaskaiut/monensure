<?php

namespace App\Modules\Financial\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('financial_categories', 'uuid')
                    ->where(fn ($query) => $query->where('tenant_id', TenantContext::tenantId())),
            ],
        ];
    }
}
