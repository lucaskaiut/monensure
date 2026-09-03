<?php

namespace App\Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssistantSettingsRequest extends FormRequest
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
            'enabled' => ['sometimes', 'boolean'],
            'endpoint' => ['nullable', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['nullable', 'string', 'max:120'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:128000'],
            'additional_prompt' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
