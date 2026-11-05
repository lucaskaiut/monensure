<?php

namespace App\Modules\Payout\Http\Controllers\Concerns;

use App\Modules\Payout\Exceptions\PayoutException;
use App\Modules\Shared\Http\ApiError;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ações que movem (ou autorizam mover) dinheiro: exigem um usuário na
 * plataforma (não token de API) e, quando indicado, confirmação de senha.
 */
trait ConfirmsHumanUser
{
    protected function human(Request $request): User
    {
        $user = $request->user();

        if ($request->attributes->get('api_token') || ! $user instanceof User) {
            abort(403, 'Pagamentos via Asaas só podem ser configurados por um usuário na plataforma.');
        }

        return $user;
    }

    protected function authorizeFor(User $user, string $ability, mixed $arguments): void
    {
        Gate::forUser($user)->authorize($ability, $arguments);
    }

    protected function confirmPassword(Request $request, User $user): void
    {
        $key = 'payout-password:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Muitas tentativas de senha. Aguarde alguns minutos.');
        }

        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['password' => 'Senha incorreta.']);
        }

        RateLimiter::clear($key);
    }

    /**
     * @param  callable(): JsonResponse  $callback
     */
    protected function guard(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (PayoutException $e) {
            return ApiError::response($e->getMessage(), 422);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return ApiError::response('Não foi possível falar com o Asaas: '.$e->getMessage(), 502);
        }
    }
}
