<?php

namespace App\Modules\Payout\Enums;

enum PixKeyType: string
{
    case Cpf = 'CPF';
    case Cnpj = 'CNPJ';
    case Email = 'EMAIL';
    case Phone = 'PHONE';
    case Evp = 'EVP';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
