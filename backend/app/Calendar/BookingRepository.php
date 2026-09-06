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
        $sql = 'SELECT bookings.id, bookings.band_id, bookings.start_time, bookings.end_time,
                       bookings.booked_by_user_id, users.name AS booked_by_user_name,
                       bands.name AS band_name
                FROM bookings
                LEFT JOIN users ON users.id = bookings.booked_by_user_id
                LEFT JOIN bands ON bands.id = bookings.band_id
                WHERE bookings.start_time <= :week_end AND bookings.end_time >= :week_start';

        if ($bandId !== null) {
            $sql .= ' AND bookings.band_id = :band_id';
        }

        $sql .= ' ORDER BY bookings.start_time';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':week_start', $weekStart->format('Y-m-d H:i:s'));
        $statement->bindValue(':week_end', $weekEnd->format('Y-m-d H:i:s'));

        if ($bandId !== null) {
            $statement->bindValue(':band_id', $bandId, PDO::PARAM_INT);
        }

        $statement->execute();

        $bookings = [];

        /** @var array{id: string, band_id: string, start_time: string, end_time: string, booked_by_user_id: string, booked_by_user_name: ?string, band_name: ?string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bookings[] = self::fromRow($row);
        }

        return $bookings;
    }

    /**
     * Ad-hoc bookings (any band) whose time range strictly overlaps
     * [$rangeStart, $rangeEnd) — touching endpoints are back-to-back, not a
     * conflict.
     *
     * @return list<Booking>
     */
    public function findOverlappingRange(DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $sql = 'SELECT bookings.id, bookings.band_id, bookings.start_time, bookings.end_time,
                       bookings.booked_by_user_id, users.name AS booked_by_user_name,
                       bands.name AS band_name
                FROM bookings
                LEFT JOIN users ON users.id = bookings.booked_by_user_id
                LEFT JOIN bands ON bands.id = bookings.band_id
                WHERE bookings.start_time < :range_end AND bookings.end_time > :range_start
                ORDER BY bookings.start_time';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':range_start', $rangeStart->format('Y-m-d H:i:s'));
        $statement->bindValue(':range_end', $rangeEnd->format('Y-m-d H:i:s'));
        $statement->execute();

        $bookings = [];

        /** @var array{id: string, band_id: string, start_time: string, end_time: string, booked_by_user_id: string, booked_by_user_name: ?string, band_name: ?string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bookings[] = self::fromRow($row);
        }

        return $bookings;
    }

    /**
     * @param array{id: string, band_id: string, start_time: string, end_time: string, booked_by_user_id: string, booked_by_user_name: ?string, band_name: ?string} $row
     */
    private static function fromRow(array $row): Booking
    {
        return new Booking(
            id: (int) $row['id'],
            bandId: (int) $row['band_id'],
            startTime: new DateTimeImmutable($row['start_time']),
            endTime: new DateTimeImmutable($row['end_time']),
            bookedByUserId: (int) $row['booked_by_user_id'],
            bookedByUserName: $row['booked_by_user_name'],
            bandName: $row['band_name'],
        );
    }
}
