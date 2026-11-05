<?php

namespace App\Modules\Payout\Http\Requests;

use App\Modules\Payout\Enums\PixKeyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnableAutoPayRequest extends FormRequest
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
            // Sem chave, usa a chave PIX cadastrada no fornecedor.
            'pix_key' => ['nullable', 'string', 'max:255'],
            'pix_key_type' => ['nullable', 'required_with:pix_key', 'string', Rule::enum(PixKeyType::class)],
            'time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'max_value' => ['required', 'numeric', 'min:0.01'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pix_key' => 'chave PIX',
            'pix_key_type' => 'tipo da chave PIX',
            'time' => 'horário',
            'max_value' => 'valor máximo',
            'password' => 'senha',
        ];
    }
}
