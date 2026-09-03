<?php

namespace App\Modules\Assistant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{setting: ?\App\Modules\Assistant\Models\AssistantSetting, defaults: array<string, mixed>, effective: array<string, mixed>}
 */
class AssistantSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{setting: ?\App\Modules\Assistant\Models\AssistantSetting, defaults: array<string, mixed>, effective: array<string, mixed>} $payload */
        $payload = $this->resource;
        $setting = $payload['setting'];
        $defaults = $payload['defaults'];
        $effective = $payload['effective'];

        return [
            'enabled' => $setting?->enabled ?? true,
            'endpoint' => $setting?->endpoint,
            'model' => $setting?->model,
            'temperature' => $setting?->temperature,
            'max_tokens' => $setting?->max_tokens,
            'additional_prompt' => $setting?->additional_prompt,
            'has_api_key' => (bool) ($effective['has_tenant_api_key'] ?? false),
            'effective' => [
                'endpoint' => $effective['endpoint'],
                'model' => $effective['model'],
                'temperature' => $effective['temperature'],
                'max_tokens' => $effective['max_tokens'],
                'has_api_key' => filled($effective['api_key']),
            ],
            'defaults' => [
                'endpoint' => $defaults['endpoint'],
                'model' => $defaults['model'],
                'temperature' => $defaults['temperature'],
                'max_tokens' => $defaults['max_tokens'],
                'has_api_key' => filled($defaults['api_key']),
            ],
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }
}
