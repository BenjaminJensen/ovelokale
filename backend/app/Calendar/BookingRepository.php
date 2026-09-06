<?php

declare(strict_types=1);

namespace App\Calendar;

use DateTimeImmutable;
use PDO;

final readonly class BookingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Ad-hoc bookings that overlap the given [weekStart, weekEnd] range.
     *
     * @return list<Booking>
     */
    public function findOverlapping(DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, ?int $bandId): array
    {
        $sql = 'SELECT id, band_id, start_time, end_time, booked_by_user_id
                FROM bookings
                WHERE start_time <= :week_end AND end_time >= :week_start';

        if ($bandId !== null) {
            $sql .= ' AND band_id = :band_id';
        }

        $sql .= ' ORDER BY start_time';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':week_start', $weekStart->format('Y-m-d H:i:s'));
        $statement->bindValue(':week_end', $weekEnd->format('Y-m-d H:i:s'));

        if ($bandId !== null) {
            $statement->bindValue(':band_id', $bandId, PDO::PARAM_INT);
        }

        $statement->execute();

        $bookings = [];

        /** @var array{id: string, band_id: string, start_time: string, end_time: string, booked_by_user_id: string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bookings[] = new Booking(
                id: (int) $row['id'],
                bandId: (int) $row['band_id'],
                startTime: new DateTimeImmutable($row['start_time']),
                endTime: new DateTimeImmutable($row['end_time']),
                bookedByUserId: (int) $row['booked_by_user_id'],
            );
        }

        return $bookings;
    }
}
