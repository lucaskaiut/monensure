<?php

namespace App\Modules\Financial\Enums;

enum RecurrenceFrequency: string
{
    case Monthly = 'mensal';
    case Bimonthly = 'bimestral';
    case Quarterly = 'trimestral';
    case Semiannual = 'semestral';
    case Annual = 'anual';

    /**
     * Número de meses entre um vencimento e o próximo.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Bimonthly => 2,
            self::Quarterly => 3,
            self::Semiannual => 6,
            self::Annual => 12,
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
