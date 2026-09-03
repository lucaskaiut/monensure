<?php

namespace App\Modules\Assistant\Services;

use App\Modules\Assistant\Models\AssistantSetting;
use RuntimeException;

final class AssistantSettingsService
{
    public function resolve(): array
    {
        $setting = $this->current();
        $defaults = $this->defaultConnection();

        return [
            'setting' => $setting,
            'defaults' => $defaults,
            'effective' => $this->effectiveValues($setting, $defaults),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): AssistantSetting
    {
        $setting = $this->current() ?? new AssistantSetting(['enabled' => true]);

        if (array_key_exists('api_key', $data)) {
            $apiKey = $data['api_key'];
            unset($data['api_key']);

            if (filled($apiKey)) {
                $setting->api_key = (string) $apiKey;
            }
        }

        $setting->fill($data);
        $setting->save();

        return $setting->refresh();
    }

    public function isEnabled(): bool
    {
        if (! (bool) config('assistant.enabled', true)) {
            return false;
        }

        $setting = $this->current();

        return $setting === null || $setting->enabled;
    }

    public function additionalPrompt(): ?string
    {
        $prompt = $this->current()?->additional_prompt;

        return filled($prompt) ? trim((string) $prompt) : null;
    }

    /**
     * @return array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}
     */
    public function connectionConfig(): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('O assistente de IA não está habilitado para esta empresa.');
        }

        $setting = $this->current();
        $defaults = $this->defaultConnection();
        $effective = $this->effectiveValues($setting, $defaults);

        $endpoint = rtrim((string) $effective['endpoint'], '/');
        $apiKey = (string) $effective['api_key'];
        $model = (string) $effective['model'];

        if ($endpoint === '') {
            throw new RuntimeException('O endpoint da API de IA não foi configurado.');
        }

        if ($apiKey === '') {
            throw new RuntimeException('A chave da API de IA não foi configurada.');
        }

        if ($model === '') {
            throw new RuntimeException('O modelo de IA não foi configurado.');
        }

        return [
            'endpoint' => $endpoint,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => (float) $effective['temperature'],
            'max_tokens' => $effective['max_tokens'] !== null ? (int) $effective['max_tokens'] : null,
        ];
    }

    /**
     * @param  array{endpoint?: ?string, model?: ?string, api_key?: ?string, temperature?: ?float, max_tokens?: ?int}  $input
     * @return array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}
     */
    public function connectionConfigFromInput(array $input): array
    {
        $setting = $this->current();
        $defaults = $this->defaultConnection();
        $effective = $this->effectiveValues($setting, $defaults);

        $endpoint = rtrim((string) ($input['endpoint'] ?? $effective['endpoint']), '/');
        $model = (string) ($input['model'] ?? $effective['model']);
        $apiKey = filled($input['api_key'] ?? null)
            ? (string) $input['api_key']
            : (string) $effective['api_key'];

        $temperature = array_key_exists('temperature', $input) && $input['temperature'] !== null
            ? (float) $input['temperature']
            : (float) $effective['temperature'];

        $maxTokens = array_key_exists('max_tokens', $input)
            ? ($input['max_tokens'] !== null ? (int) $input['max_tokens'] : null)
            : ($effective['max_tokens'] !== null ? (int) $effective['max_tokens'] : null);

        if ($endpoint === '' || $apiKey === '' || $model === '') {
            throw new RuntimeException('Informe endpoint, modelo e chave da API para testar a conexão.');
        }

        return [
            'endpoint' => $endpoint,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
    }

    private function current(): ?AssistantSetting
    {
        return AssistantSetting::query()->first();
    }

    /**
     * @return array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}
     */
    private function defaultConnection(): array
    {
        $connection = config('assistant.connection', []);

        return [
            'endpoint' => (string) ($connection['endpoint'] ?? ''),
            'api_key' => (string) ($connection['api_key'] ?? ''),
            'model' => (string) ($connection['model'] ?? ''),
            'temperature' => (float) ($connection['temperature'] ?? 0.2),
            'max_tokens' => isset($connection['max_tokens']) && $connection['max_tokens'] !== null
                ? (int) $connection['max_tokens']
                : null,
        ];
    }

    /**
     * @return array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int, has_tenant_api_key: bool}
     */
    private function effectiveValues(?AssistantSetting $setting, array $defaults): array
    {
        return [
            'endpoint' => filled($setting?->endpoint) ? (string) $setting->endpoint : $defaults['endpoint'],
            'api_key' => filled($setting?->api_key) ? (string) $setting->api_key : $defaults['api_key'],
            'model' => filled($setting?->model) ? (string) $setting->model : $defaults['model'],
            'temperature' => $setting?->temperature !== null ? (float) $setting->temperature : $defaults['temperature'],
            'max_tokens' => $setting?->max_tokens !== null ? (int) $setting->max_tokens : $defaults['max_tokens'],
            'has_tenant_api_key' => filled($setting?->api_key),
        ];
    }
}
