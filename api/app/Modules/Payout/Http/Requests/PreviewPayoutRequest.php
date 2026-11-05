<?php

namespace App\Modules\Payout\Http\Requests;

use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PixKeyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewPayoutRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::enum(PayoutMethod::class)],
            'payment_code' => ['required', 'string', 'max:1000'],
            'pix_key_type' => ['nullable', 'required_if:method,pix_key', 'string', Rule::enum(PixKeyType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'method' => 'forma de pagamento',
            'payment_code' => 'código de pagamento',
            'pix_key_type' => 'tipo da chave PIX',
        ];
    }
}
