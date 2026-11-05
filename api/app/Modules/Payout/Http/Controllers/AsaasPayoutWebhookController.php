<?php

namespace App\Modules\Payout\Http\Controllers;

use App\Modules\Payout\Services\PayoutAuthorizationService;
use App\Modules\Payout\Services\PayoutEventService;
use App\Modules\Shared\Http\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Endpoints públicos chamados pelo Asaas, autenticados pelo header
 * "asaas-access-token" (um token para cada finalidade).
 */
class AsaasPayoutWebhookController extends Controller
{
    /**
     * Mecanismo de validação de saques: responde APPROVED ou REFUSED.
     */
    public function authorization(Request $request, PayoutAuthorizationService $authorizations): JsonResponse
    {
        if (! $this->validToken($request, (string) config('asaas.payouts.authorization_token'))) {
            return ApiError::response('Não autorizado.', 401);
        }

        try {
            return response()->json($authorizations->decide($request->all()));
        } catch (Throwable $e) {
            report($e);

            // Na dúvida, recusa: nenhuma saída é aprovada por falha interna.
            return response()->json(['status' => 'REFUSED', 'refuseReason' => 'Erro interno ao validar a operação.']);
        }
    }

    /**
     * Eventos de transferências e pagamentos de contas.
     */
    public function events(Request $request, PayoutEventService $events): JsonResponse
    {
        if (! $this->validToken($request, (string) config('asaas.payouts.webhook_token'))) {
            return ApiError::response('Não autorizado.', 401);
        }

        try {
            $applied = $events->handle($request->all());
        } catch (Throwable $e) {
            // Responde 200 mesmo assim para não pausar a fila do Asaas:
            // a conciliação periódica corrige o status depois.
            report($e);
            Log::error('payouts.event_failed', ['error' => $e->getMessage()]);
            $applied = false;
        }

        return response()->json(['received' => true, 'applied' => $applied]);
    }

    private function validToken(Request $request, string $expected): bool
    {
        $expected = trim($expected);
        $received = (string) $request->header('asaas-access-token');

        return $expected !== '' && $received !== '' && hash_equals($expected, $received);
    }
}
