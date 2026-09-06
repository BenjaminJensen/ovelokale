<?php

declare(strict_types=1);

namespace App\Calendar;

final readonly class RecurringSlot
{
    /**
     * @param int $dayOfWeek ISO-8601: 1 (Monday) .. 7 (Sunday)
     * @param string $startTime "H:i:s"
     * @param string $endTime "H:i:s"
     */
    public function __construct(
        public int $id,
        public int $bandId,
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public WeekParity $weekParity,
        public ?string $bandName,
    ) {
    }
}
