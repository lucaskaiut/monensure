<?php

namespace App\Modules\Payout\Gateways;

use App\Modules\Billing\Gateways\Asaas\AsaasException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da API Asaas para saídas de dinheiro (pagamento de contas e PIX).
 *
 * Usa a chave ASAAS_PAYOUTS_API_KEY, separada da chave de cobrança.
 */
class AsaasPayoutClient
{
    /** @return array<string, mixed> */
    public function simulateBill(string $identificationField): array
    {
        return $this->request('post', '/bill/simulate', ['identificationField' => $identificationField]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function payBill(array $payload): array
    {
        return $this->request('post', '/bill', $payload, timeout: 60);
    }

    /** @return array<string, mixed> */
    public function getBill(string $id): array
    {
        return $this->request('get', '/bill/'.$id);
    }

    /** @return array<string, mixed> */
    public function decodePixQrCode(string $payload): array
    {
        return $this->request('post', '/pix/qrCodes/decode', ['payload' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function payPixQrCode(array $payload): array
    {
        return $this->request('post', '/pix/qrCodes/pay', $payload, timeout: 60);
    }

    /** @return array<string, mixed> */
    public function getPixTransaction(string $id): array
    {
        return $this->request('get', '/pix/transactions/'.$id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTransfer(array $payload): array
    {
        return $this->request('post', '/transfers', $payload, timeout: 60);
    }

    /** @return array<string, mixed> */
    public function getTransfer(string $id): array
    {
        return $this->request('get', '/transfers/'.$id);
    }

    public function balance(): float
    {
        $response = $this->request('get', '/finance/balance');

        return round((float) ($response['balance'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, ?int $timeout = null): array
    {
        $response = $this->http($timeout)
            ->{$method}('/'.ltrim($path, '/'), $method === 'get' ? [] : ($payload ?? []));

        if ($response->failed()) {
            throw AsaasException::fromResponse($response);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function http(?int $timeout = null): PendingRequest
    {
        $apiKey = (string) config('asaas.payouts.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('ASAAS_PAYOUTS_API_KEY não configurada.');
        }

        return Http::baseUrl(rtrim((string) config('asaas.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $apiKey,
                'User-Agent' => (string) config('asaas.user_agent', 'NoxConnect/1.0'),
            ])
            ->timeout(max(1, $timeout ?? (int) config('asaas.timeout', 30)))
            ->connectTimeout(10);
    }
}
