<?php

use App\Modules\Financial\Mcp\FinancialServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * Servidores MCP (Model Context Protocol).
 *
 * Autenticação: header "Authorization: Bearer <token>", aceitando:
 * - API tokens da plataforma (api_*, com escopos);
 * - tokens Sanctum de usuário (ACL por papéis);
 * - access tokens OAuth 2.1 do Passport (guard "api", ACL por papéis), usados
 *   por conectores remotos como o claude.ai. A descoberta segue a spec MCP:
 *   401 com WWW-Authenticate -> /.well-known/oauth-protected-resource ->
 *   /.well-known/oauth-authorization-server -> registro dinâmico de cliente
 *   (/oauth/register) -> /oauth/authorize (login + consentimento) -> /oauth/token.
 */
Mcp::oauthRoutes();

Mcp::web('/api/mcp/financial', FinancialServer::class)
    ->middleware(['auth.multi:sanctum,api', 'tenant', 'subscription.active', 'tenant.child', 'throttle:api'])
    ->name('mcp.financial');
