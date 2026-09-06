<?php

declare(strict_types=1);

namespace App\Calendar;

use DateTimeImmutable;

final readonly class Booking
{
    public function __construct(
        public int $id,
        public int $bandId,
        public DateTimeImmutable $startTime,
        public DateTimeImmutable $endTime,
        public int $bookedByUserId,
        public ?string $bookedByUserName,
    ) {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'source' => 'ad_hoc',
            'id' => $this->id,
            'band_id' => $this->bandId,
            'start_time' => $this->startTime->format('Y-m-d H:i:s'),
            'end_time' => $this->endTime->format('Y-m-d H:i:s'),
            'booked_by_user_id' => $this->bookedByUserId,
            'booked_by_user_name' => $this->bookedByUserName,
        ];
    }
}
