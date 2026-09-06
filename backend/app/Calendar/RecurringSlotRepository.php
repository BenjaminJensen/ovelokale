<?php

declare(strict_types=1);

namespace App\Calendar;

use PDO;

final readonly class RecurringSlotRepository
{
    public function __construct(private PDO $pdo)
    {
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

        /** @var array{id: string, band_id: string, day_of_week: string, start_time: string, end_time: string, week_parity: string, band_name: ?string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $slots[] = new RecurringSlot(
                id: (int) $row['id'],
                bandId: (int) $row['band_id'],
                dayOfWeek: (int) $row['day_of_week'],
                startTime: $row['start_time'],
                endTime: $row['end_time'],
                weekParity: WeekParity::from($row['week_parity']),
                bandName: $row['band_name'],
            );
        }

        return $slots;
    }
}
