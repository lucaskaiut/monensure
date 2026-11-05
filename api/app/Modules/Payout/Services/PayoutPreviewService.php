<?php

namespace App\Modules\Payout\Services;

use App\Modules\Billing\Gateways\Asaas\AsaasException;
use App\Modules\Payout\DTOs\PayoutPreview;
use App\Modules\Payout\Enums\PayoutMethod;
use App\Modules\Payout\Enums\PixKeyType;
use App\Modules\Payout\Exceptions\PayoutException;
use App\Modules\Payout\Gateways\AsaasPayoutClient;

/**
 * Valida o instrumento de pagamento no Asaas antes de agendar e novamente
 * antes de executar, para detectar mudança de valor ou de beneficiário.
 */
class PayoutPreviewService
{
    public function __construct(private readonly AsaasPayoutClient $asaas) {}

    public function preview(PayoutMethod $method, string $paymentCode, ?PixKeyType $pixKeyType = null): PayoutPreview
    {
        $code = self::normalizeCode($method, $paymentCode, $pixKeyType);

        try {
            return match ($method) {
                PayoutMethod::Boleto => $this->previewBoleto($code),
                PayoutMethod::PixQrCode => $this->previewPixQrCode($code),
                PayoutMethod::PixKey => $this->previewPixKey($code),
            };
        } catch (AsaasException $e) {
            throw new PayoutException('O Asaas não validou o pagamento: '.$e->getMessage(), previous: $e);
        }
    }

    public static function normalizeCode(PayoutMethod $method, string $code, ?PixKeyType $pixKeyType = null): string
    {
        $code = trim($code);

        return match (true) {
            $method === PayoutMethod::Boleto => self::digits($code),
            $method === PayoutMethod::PixKey && in_array($pixKeyType, [PixKeyType::Cpf, PixKeyType::Cnpj], true) => self::digits($code),
            $method === PayoutMethod::PixKey && $pixKeyType === PixKeyType::Email => mb_strtolower($code),
            default => $code,
        };
    }

    private function previewBoleto(string $code): PayoutPreview
    {
        if (! in_array(strlen($code), [44, 47, 48], true)) {
            throw new PayoutException('Linha digitável inválida: informe os 47 ou 48 dígitos do boleto.');
        }

        $response = $this->asaas->simulateBill($code);
        $info = is_array($response['bankSlipInfo'] ?? null) ? $response['bankSlipInfo'] : [];

        // Usa a linha digitável devolvida pelo Asaas: é ela que volta no
        // webhook de autorização, mesmo quando foi informado o código de barras.
        $line = self::digits((string) ($info['identificationField'] ?? ''));

        return new PayoutPreview(
            method: PayoutMethod::Boleto,
            paymentCode: $line !== '' ? $line : $code,
            beneficiaryName: self::string($info['beneficiaryName'] ?? $info['companyName'] ?? null),
            beneficiaryDocument: self::document($info['beneficiaryCpfCnpj'] ?? null),
            value: self::money($info['value'] ?? null),
            dueDate: self::string($info['dueDate'] ?? null),
            canPay: true,
            cannotPayReason: null,
            allowsCustomValue: (bool) ($info['allowChangeValue'] ?? false),
            fee: self::money($response['fee'] ?? null),
            raw: $response,
        );
    }

    private function previewPixQrCode(string $code): PayoutPreview
    {
        if ($code === '') {
            throw new PayoutException('Informe o código PIX copia e cola.');
        }

        $response = $this->asaas->decodePixQrCode($code);
        $receiver = is_array($response['receiver'] ?? null) ? $response['receiver'] : [];
        $value = self::money($response['totalValue'] ?? null) ?: self::money($response['value'] ?? null);

        return new PayoutPreview(
            method: PayoutMethod::PixQrCode,
            paymentCode: $code,
            beneficiaryName: self::string($receiver['name'] ?? $receiver['tradingName'] ?? null),
            beneficiaryDocument: self::document($receiver['cpfCnpj'] ?? null),
            value: $value ?: null,
            dueDate: self::string($response['dueDate'] ?? null),
            canPay: (bool) ($response['canBePaid'] ?? true),
            cannotPayReason: self::string($response['cannotBePaidReason'] ?? null),
            allowsCustomValue: (bool) ($response['canBePaidWithDifferentValue'] ?? false) || ! $value,
            fee: null,
            raw: $response,
        );
    }

    private function previewPixKey(string $code): PayoutPreview
    {
        if ($code === '') {
            throw new PayoutException('Informe a chave PIX.');
        }

        // O Asaas não oferece consulta do titular de uma chave antes da
        // transferência: o beneficiário só é conhecido no comprovante.
        return new PayoutPreview(
            method: PayoutMethod::PixKey,
            paymentCode: $code,
            beneficiaryName: null,
            beneficiaryDocument: null,
            value: null,
            dueDate: null,
            canPay: true,
            cannotPayReason: null,
            allowsCustomValue: true,
            fee: null,
        );
    }

    private static function money(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private static function string(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * Mantém asteriscos de documentos mascarados (o Asaas pode ocultar dígitos).
     */
    private static function document(mixed $value): ?string
    {
        $value = self::string($value);

        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9*]/', '', $value) ?? '';

        return $normalized === '' ? null : mb_substr($normalized, 0, 20);
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
