<?php

namespace App\Modules\Payout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolvePayoutRequest extends FormRequest
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
            'outcome' => ['required', 'string', 'in:paid,failed'],
            'password' => ['required', 'string'],
        ];
    }
}
