<?php

namespace App\Modules\Financial\Http\Requests;

use App\Modules\Shared\Rules\CpfOrCnpj;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document' => ['sometimes', 'nullable', 'string', new CpfOrCnpj],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'observations' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('document')) {
            $this->merge([
                'document' => (string) preg_replace('/\D+/', '', (string) $this->input('document')),
            ]);
        }
    }
}
