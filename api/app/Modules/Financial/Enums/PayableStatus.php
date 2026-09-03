<?php

namespace App\Modules\Financial\Enums;

enum PayableStatus: string
{
    case Pending = 'pendente';
    case Paid = 'pago';
    case Cancelled = 'cancelado';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
