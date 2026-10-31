<?php

namespace App\Modules\Financial\Mcp\Tools;

use App\Modules\ACL\Enums\Permission;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Base das ferramentas MCP do domínio financeiro.
 *
 * Autenticação e tenant já foram resolvidos pelos middlewares da rota
 * (auth.multi, tenant, subscription.active, tenant.child). Aqui aplicamos o
 * ACL por ferramenta, com a mesma regra do middleware EnsurePermission:
 *
 * - API token (api_*): checa os escopos do token (null = acesso total);
 * - usuário (Sanctum): checa as permissões dos papéis do usuário.
 *
 * Ferramentas sem permissão nem aparecem em tools/list (shouldRegister) e,
 * por defesa em profundidade, a permissão é verificada de novo na execução.
 */
abstract class FinancialTool extends Tool
{
    /**
     * Permissão exigida para executar a ferramenta.
     */
    abstract protected function permission(): Permission;

    /**
     * Executa a ferramenta (já autorizada).
     */
    abstract protected function execute(Request $request): Response|ResponseFactory;

    final public function handle(Request $request): Response|ResponseFactory
    {
        if (! $this->allows($this->permission())) {
            throw new AuthorizationException($this->deniedMessage());
        }

        return $this->execute($request);
    }

    public function shouldRegister(): bool
    {
        return $this->allows($this->permission());
    }

    protected function allows(Permission $permission): bool
    {
        $apiToken = $this->apiToken();

        if ($apiToken !== null) {
            return $apiToken->can($permission->value);
        }

        return $this->actor()?->hasPermission($permission) ?? false;
    }

    /**
     * Usuário autenticado (null quando a chamada vem de um API token).
     */
    protected function actor(): ?User
    {
        if ($this->apiToken() !== null) {
            return null;
        }

        $user = request()->user();

        return $user instanceof User ? $user : null;
    }

    protected function apiToken(): ?ApiToken
    {
        $token = request()->attributes->get('api_token');

        return $token instanceof ApiToken ? $token : null;
    }

    private function deniedMessage(): string
    {
        return $this->apiToken() !== null
            ? 'Este token não possui escopo para executar esta ação.'
            : 'Você não possui permissão para executar esta ação.';
    }

    /**
     * Serializa um JsonResource (ou coleção) para array puro.
     *
     * @return array<string, mixed>|list<mixed>
     */
    protected function resource(JsonResource $resource): array
    {
        return $resource->toResponse(request())->getData(true)['data'] ?? [];
    }

    /**
     * Resposta estruturada de uma listagem paginada.
     *
     * @param  array<string, mixed>  $extraMeta
     */
    protected function paginated(ResourceCollection $collection, LengthAwarePaginator $paginator, array $extraMeta = []): ResponseFactory
    {
        return Response::structured([
            'data' => $this->resource($collection),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                ...$extraMeta,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function success(string $message, array $data = []): ResponseFactory
    {
        return Response::structured(['message' => $message, ...$data]);
    }

    protected function notFound(string $entity): Response
    {
        return Response::error("{$entity} não encontrado(a) neste tenant.");
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->get('per_page', 15), 1), 100);
    }

    protected function page(Request $request): int
    {
        return max((int) $request->get('page', 1), 1);
    }
}
