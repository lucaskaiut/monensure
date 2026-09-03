<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Http\Requests\TestAssistantConnectionRequest;
use App\Modules\Assistant\Http\Requests\UpdateAssistantSettingsRequest;
use App\Modules\Assistant\Http\Resources\AssistantSettingsResource;
use App\Modules\Assistant\Models\AssistantSetting;
use App\Modules\Assistant\Services\AssistantSettingsService;
use App\Modules\Assistant\Services\OpenAiClient;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class AssistantSettingsController extends ApiController
{
    public function __construct(
        private readonly AssistantSettingsService $settings,
        private readonly OpenAiClient $client,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize('viewAny', AssistantSetting::class);

        return $this->success(AssistantSettingsResource::make($this->settings->resolve()));
    }

    public function update(UpdateAssistantSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', AssistantSetting::class);

        $this->settings->update($request->validated());

        return $this->success(
            AssistantSettingsResource::make($this->settings->resolve()),
            'Configurações do assistente salvas com sucesso.',
        );
    }

    public function testConnection(TestAssistantConnectionRequest $request): JsonResponse
    {
        $this->authorize('update', AssistantSetting::class);

        $config = $this->settings->connectionConfigFromInput($request->validated());
        $result = $this->client->testConnection($config);

        return $this->success($result, $result['message']);
    }
}
