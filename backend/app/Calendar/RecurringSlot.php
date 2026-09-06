<?php

declare(strict_types=1);

namespace App\Calendar;

final readonly class RecurringSlot
{
    /**
     * @param int $dayOfWeek ISO-8601: 1 (Monday) .. 7 (Sunday)
     * @param string $startTime "H:i:s"
     * @param string $endTime "H:i:s"
     * @param string $startDate "Y-m-d"
     * @param ?string $endDate "Y-m-d"
     */
    public function __construct(
        public int $id,
        public int $bandId,
        public int $dayOfWeek,
        public string $startTime,
        public string $endTime,
        public WeekParity $weekParity,
        public ?string $bandName,
        public string $startDate,
        public ?string $endDate,
    ) {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'band_id' => $this->bandId,
            'band_name' => $this->bandName,
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'week_parity' => $this->weekParity->value,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
