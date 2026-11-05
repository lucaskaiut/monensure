<?php

namespace App\Modules\Financial\Http\Requests;

use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PixKeyType;
use App\Modules\Payout\Services\PayoutPreviewService;
use App\Modules\Shared\Rules\CpfOrCnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'pix_key_type' => ['sometimes', 'nullable', 'required_with:pix_key', 'string', Rule::enum(PixKeyType::class)],
            'pix_key' => ['sometimes', 'nullable', 'required_with:pix_key_type', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliza a chave PIX no mesmo formato usado no pagamento.
        if ($this->filled('pix_key') && $this->filled('pix_key_type') && PixKeyType::tryFrom((string) $this->input('pix_key_type')) !== null) {
            $this->merge([
                'pix_key' => PayoutPreviewService::normalizeCode(
                    PayoutMethod::PixKey,
                    (string) $this->input('pix_key'),
                    PixKeyType::from((string) $this->input('pix_key_type')),
                ),
            ]);
        }

        if ($this->filled('document')) {
            $this->merge([
                'document' => (string) preg_replace('/\D+/', '', (string) $this->input('document')),
            ]);
        }
    }
}
