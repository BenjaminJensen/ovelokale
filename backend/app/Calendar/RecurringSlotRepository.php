<?php

declare(strict_types=1);

namespace App\Calendar;

use PDO;
use RuntimeException;

final readonly class RecurringSlotRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(
        int $bandId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        WeekParity $weekParity,
        string $startDate,
        ?string $endDate,
    ): RecurringSlot {
        $statement = $this->pdo->prepare(
            'INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date, end_date)
             VALUES (:band_id, :day_of_week, :start_time, :end_time, :week_parity, :start_date, :end_date)'
        );
        $statement->bindValue(':band_id', $bandId, PDO::PARAM_INT);
        $statement->bindValue(':day_of_week', $dayOfWeek, PDO::PARAM_INT);
        $statement->bindValue(':start_time', $startTime);
        $statement->bindValue(':end_time', $endTime);
        $statement->bindValue(':week_parity', $weekParity->value);
        $statement->bindValue(':start_date', $startDate);
        $statement->bindValue(':end_date', $endDate, $endDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();

        $slot = $this->findById((int) $this->pdo->lastInsertId());

        if ($slot === null) {
            throw new RuntimeException('Recurring slot not found immediately after insert.');
        }

        return $slot;
    }

    public function findById(int $id): ?RecurringSlot
    {
        $sql = 'SELECT recurring_slots.id, recurring_slots.band_id, recurring_slots.day_of_week,
                       recurring_slots.start_time, recurring_slots.end_time, recurring_slots.week_parity,
                       recurring_slots.start_date, recurring_slots.end_date,
                       bands.name AS band_name
                FROM recurring_slots
                LEFT JOIN bands ON bands.id = recurring_slots.band_id
                WHERE recurring_slots.id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        /** @var array{id: string, band_id: string, day_of_week: string, start_time: string, end_time: string, week_parity: string, start_date: string, end_date: ?string, band_name: ?string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new RecurringSlot(
            id: (int) $row['id'],
            bandId: (int) $row['band_id'],
            dayOfWeek: (int) $row['day_of_week'],
            startTime: $row['start_time'],
            endTime: $row['end_time'],
            weekParity: WeekParity::from($row['week_parity']),
            bandName: $row['band_name'],
            startDate: $row['start_date'],
            endDate: $row['end_date'],
        );
    }

    /**
     * Existing recurring slots (any band) whose day-of-week, time range, and
     * date range overlap the given candidate pattern. Week-parity
     * compatibility (e.g. "odd" never overlaps "even") is checked by the
     * caller, since it isn't expressible as a simple equality here.
     *
     * @return list<RecurringSlot>
     */
    public function findOverlappingPattern(
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        string $startDate,
        ?string $endDate,
    ): array {
        $sql = 'SELECT recurring_slots.id, recurring_slots.band_id, recurring_slots.day_of_week,
                       recurring_slots.start_time, recurring_slots.end_time, recurring_slots.week_parity,
                       recurring_slots.start_date, recurring_slots.end_date,
                       bands.name AS band_name
                FROM recurring_slots
                LEFT JOIN bands ON bands.id = recurring_slots.band_id
                WHERE recurring_slots.day_of_week = :day_of_week
                  AND recurring_slots.start_time < :pattern_end_time
                  AND recurring_slots.end_time > :pattern_start_time
                  AND (:candidate_end_date IS NULL OR recurring_slots.start_date <= :candidate_end_date)
                  AND (recurring_slots.end_date IS NULL OR recurring_slots.end_date >= :candidate_start_date)
                ORDER BY recurring_slots.start_time';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':day_of_week', $dayOfWeek, PDO::PARAM_INT);
        $statement->bindValue(':pattern_start_time', $startTime);
        $statement->bindValue(':pattern_end_time', $endTime);
        $statement->bindValue(':candidate_start_date', $startDate);
        $statement->bindValue(':candidate_end_date', $endDate, $endDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();

        $slots = [];

        /** @var array{id: string, band_id: string, day_of_week: string, start_time: string, end_time: string, week_parity: string, start_date: string, end_date: ?string, band_name: ?string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $slots[] = new RecurringSlot(
                id: (int) $row['id'],
                bandId: (int) $row['band_id'],
                dayOfWeek: (int) $row['day_of_week'],
                startTime: $row['start_time'],
                endTime: $row['end_time'],
                weekParity: WeekParity::from($row['week_parity']),
                bandName: $row['band_name'],
                startDate: $row['start_date'],
                endDate: $row['end_date'],
            );
        }

        return $slots;
    }

    /**
     * Recurring slots whose parity matches the given week (or are set to "all").
     *
     * @return list<RecurringSlot>
     */
    public function findMatchingParity(WeekParity $weekParity, ?int $bandId): array
    {
        $sql = "SELECT recurring_slots.id, recurring_slots.band_id, recurring_slots.day_of_week,
                       recurring_slots.start_time, recurring_slots.end_time, recurring_slots.week_parity,
                       recurring_slots.start_date, recurring_slots.end_date,
                       bands.name AS band_name
                FROM recurring_slots
                LEFT JOIN bands ON bands.id = recurring_slots.band_id
                WHERE week_parity IN ('all', :week_parity)";

        if ($bandId !== null) {
            $sql .= ' AND band_id = :band_id';
        }

        $sql .= ' ORDER BY day_of_week, start_time';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':week_parity', $weekParity->value);

        if ($bandId !== null) {
            $statement->bindValue(':band_id', $bandId, PDO::PARAM_INT);
        }

        $statement->execute();

        $slots = [];

        /** @var array{id: string, band_id: string, day_of_week: string, start_time: string, end_time: string, week_parity: string, start_date: string, end_date: ?string, band_name: ?string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $slots[] = new RecurringSlot(
                id: (int) $row['id'],
                bandId: (int) $row['band_id'],
                dayOfWeek: (int) $row['day_of_week'],
                startTime: $row['start_time'],
                endTime: $row['end_time'],
                weekParity: WeekParity::from($row['week_parity']),
                bandName: $row['band_name'],
                startDate: $row['start_date'],
                endDate: $row['end_date'],
            );
        }

        return $slots;
    }
}
