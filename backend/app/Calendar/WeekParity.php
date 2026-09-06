<?php

declare(strict_types=1);

namespace App\Calendar;

enum WeekParity: string
{
    case Odd = 'odd';
    case Even = 'even';
    case All = 'all';

    public static function forIsoWeekNumber(int $isoWeekNumber): self
    {
        return $isoWeekNumber % 2 === 1 ? self::Odd : self::Even;
    }
}
