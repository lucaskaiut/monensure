<?php

namespace App\Modules\Financial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayPayableRequest extends FormRequest
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
            'paid_at' => ['required', 'date'],
            'paid_value' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
