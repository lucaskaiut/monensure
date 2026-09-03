<?php

namespace App\Modules\Financial\Enums;

enum PayableEditScope: string
{
    case This = 'this';
    case ThisAndNext = 'this_and_next';
    case All = 'all';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
