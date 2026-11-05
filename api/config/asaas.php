<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asaas API (credenciais globais)
    |--------------------------------------------------------------------------
    |
    | Credenciais compartilhadas por todos os métodos Asaas (PIX, cartão, etc.).
    | Sandbox: https://api-sandbox.asaas.com/v3
    | Produção: https://api.asaas.com/v3
    |
    */

    'api_key' => env('ASAAS_API_KEY'),

    'base_url' => env('ASAAS_BASE_URL', 'https://api-sandbox.asaas.com/v3'),

    'user_agent' => env('ASAAS_USER_AGENT', 'NoxConnect/1.0'),

    /*
    | Timeout padrão (segundos). Cobranças com cartão usam no mínimo 60s.
    */
    'timeout' => (int) env('ASAAS_TIMEOUT', 30),

    'credit_card_timeout' => (int) env('ASAAS_CREDIT_CARD_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Pagamento de contas (saídas de dinheiro)
    |--------------------------------------------------------------------------
    |
    | Usa uma chave de API própria, separada da cobrança (Billing). Todas as
    | saídas passam pelo webhook de autorização do Asaas, que só aprova
    | operações criadas por este sistema (ver PayoutAuthorizationService).
    |
    */
    'payouts' => [
        // Kill switch: desligado, nenhuma ordem é agendada ou executada.
        'enabled' => (bool) env('ASAAS_PAYOUTS_ENABLED', false),

        'api_key' => env('ASAAS_PAYOUTS_API_KEY'),

        // Limites de segurança (em reais).
        'max_per_payment' => (float) env('ASAAS_PAYOUTS_MAX_PER_PAYMENT', 2000),
        'max_per_day' => (float) env('ASAAS_PAYOUTS_MAX_PER_DAY', 5000),

        // Token do webhook de eventos (TRANSFER_*, BILL_*), header "asaas-access-token".
        'webhook_token' => env('ASAAS_PAYOUTS_WEBHOOK_TOKEN'),

        // Token do mecanismo de validação de saques via webhook.
        'authorization_token' => env('ASAAS_PAYOUTS_AUTHORIZATION_TOKEN'),

        // Janela (minutos) após o envio em que a autorização é aceita.
        'authorization_window_minutes' => (int) env('ASAAS_PAYOUTS_AUTHORIZATION_WINDOW', 30),

        // Fuso usado para o limite diário.
        'timezone' => env('ASAAS_PAYOUTS_TIMEZONE', 'America/Sao_Paulo'),
    ],

];
