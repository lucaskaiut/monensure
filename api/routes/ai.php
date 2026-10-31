<?php

use App\Modules\Financial\Mcp\FinancialServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * Servidores MCP (Model Context Protocol).
 *
 * Autenticação: header "Authorization: Bearer <token>", aceitando API tokens
 * da plataforma (api_*, com escopos) ou tokens Sanctum de usuário (ACL por
 * papéis). Mesma cadeia de middlewares das rotas financeiras da API REST.
 */
Mcp::web('/api/mcp/financial', FinancialServer::class)
    ->middleware(['auth.multi:sanctum', 'tenant', 'subscription.active', 'tenant.child', 'throttle:api'])
    ->name('mcp.financial');
