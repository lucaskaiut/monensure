<?php

namespace App\Modules\Payout\Enums;

enum PayoutMethod: string
{
    case Boleto = 'boleto';
    case PixQrCode = 'pix_qr_code';
    case PixKey = 'pix_key';

    public function label(): string
    {
        return match ($this) {
            self::Boleto => 'Boleto',
            self::PixQrCode => 'PIX copia e cola',
            self::PixKey => 'PIX por chave',
        };
    }

    /**
     * Tipo informado pelo Asaas no webhook de autorização de saques.
     */
    public function authorizationType(): string
    {
        return match ($this) {
            self::Boleto => 'BILL',
            self::PixQrCode => 'PIX_QR_CODE',
            self::PixKey => 'TRANSFER',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
