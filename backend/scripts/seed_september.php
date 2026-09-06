<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

/**
 * Dev-only seed script. Clears `bookings` and `recurring_slots`, then inserts
 * random test data for September of the given year (default: current year):
 * ad-hoc bookings dated across the month, plus a handful of recurring slots
 * (day-of-week + parity patterns, not date-bound, so they surface whenever
 * the calendar is queried for a September week).
 *
 * Usage: docker compose exec web php scripts/seed_september.php [year]
 */

$year = isset($argv[1]) ? (int) $argv[1] : (int) date('Y');
$month = 9;

$bandIds = [1, 2, 3, 4, 5];
$userIds = [1, 2, 3, 4, 5];
$weekParities = ['odd', 'even', 'all'];

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->exec('DELETE FROM bookings');
    $pdo->exec('DELETE FROM recurring_slots');

    $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');

    $insertBooking = $pdo->prepare(
        'INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
         VALUES (:band_id, :start_time, :end_time, :booked_by_user_id)'
    );

    $bookingTarget = 20;
    $bookingsInserted = 0;
    $bookingAttempts = 0;

    /** @var array<int, list<array{0: string, 1: string}>> $occupiedByBand */
    $occupiedByBand = [];

    while ($bookingsInserted < $bookingTarget && $bookingAttempts < $bookingTarget * 10) {
        $bookingAttempts++;

        $bandId = $bandIds[array_rand($bandIds)];
        $day = random_int(1, $daysInMonth);
        $startHour = random_int(8, 21);
        $durationHours = random_int(1, 3);

        $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:00:00', $year, $month, $day, $startHour));
        $end = $start->modify("+{$durationHours} hours");

        // Skip bookings that would roll past midnight into the next day.
        if ($end->format('Y-m-d') !== $start->format('Y-m-d')) {
            continue;
        }

        $startKey = $start->format('Y-m-d H:i:s');
        $endKey = $end->format('Y-m-d H:i:s');

        $overlaps = false;
        foreach ($occupiedByBand[$bandId] ?? [] as [$existingStart, $existingEnd]) {
            if ($startKey < $existingEnd && $endKey > $existingStart) {
                $overlaps = true;

                break;
            }
        }

        if ($overlaps) {
            continue;
        }

        $occupiedByBand[$bandId][] = [$startKey, $endKey];

        $insertBooking->execute([
            ':band_id' => $bandId,
            ':start_time' => $startKey,
            ':end_time' => $endKey,
            ':booked_by_user_id' => $userIds[array_rand($userIds)],
        ]);

        $bookingsInserted++;
    }

    $insertSlot = $pdo->prepare(
        'INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
         VALUES (:band_id, :day_of_week, :start_time, :end_time, :week_parity)'
    );

    $slotTarget = 8;
    $slotsInserted = 0;
    $slotAttempts = 0;

    /** @var array<string, true> $usedSlots */
    $usedSlots = [];

    while ($slotsInserted < $slotTarget && $slotAttempts < $slotTarget * 10) {
        $slotAttempts++;

        $bandId = $bandIds[array_rand($bandIds)];
        $dayOfWeek = random_int(1, 7);
        $startHour = random_int(8, 21);
        $endHour = min($startHour + random_int(1, 3), 23);

        $key = "{$bandId}:{$dayOfWeek}:{$startHour}";

        if (isset($usedSlots[$key])) {
            continue;
        }

        $usedSlots[$key] = true;

        $insertSlot->execute([
            ':band_id' => $bandId,
            ':day_of_week' => $dayOfWeek,
            ':start_time' => sprintf('%02d:00:00', $startHour),
            ':end_time' => sprintf('%02d:00:00', $endHour),
            ':week_parity' => $weekParities[array_rand($weekParities)],
        ]);

        $slotsInserted++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();

    throw $e;
}

echo "Seeded {$bookingsInserted} ad-hoc bookings and {$slotsInserted} recurring slots for {$year}-{$month}.\n";
