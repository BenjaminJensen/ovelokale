<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Calendar\WeekParity;

/**
 * Dev-only seed for the Playwright suite. Unlike seed_september.php, which
 * inserts *random* data for eyeballing a full calendar, this inserts a
 * *fixed* fixture so assertions are reproducible: two consecutive runs leave
 * the database in an identical state.
 *
 * DESTRUCTIVE: wipes bookings, recurring slots, band memberships, bands and
 * users before rebuilding them, so band ids and names are pinned rather than
 * inherited from whatever was seeded before.
 *
 * Everything is anchored to the Monday of the *current* week, so the fixture
 * always lands where the calendar opens (it starts on today's month) without
 * the suite having to navigate to a hard-coded date. Two days are left empty
 * on purpose — Tuesday and Sunday — for the specs that create bookings, so a
 * mutating spec can never disturb another spec's assertions.
 *
 * A JSON manifest of everything inserted is written to stdout; the specs read
 * it instead of recomputing the same date arithmetic in TypeScript. The human
 * summary goes to stderr so the manifest can be redirected to a file.
 *
 * Usage: docker compose exec -T web php scripts/seed_e2e.php > frontend/e2e/.fixture.json
 *        (normally invoked via scripts/e2e.sh)
 */

// The browser context in playwright.config.ts runs in Europe/Copenhagen. Pin
// PHP to the same zone so "the Monday of this week" means the same day on
// both sides, even when the container clock is UTC and it is past 22:00 local.
date_default_timezone_set('Europe/Copenhagen');

const BANDS = [
    1 => ['name' => 'Aurora', 'color' => '#3E63DD'],
    2 => ['name' => 'Bjergtrolde', 'color' => '#0E9F6E'],
    3 => ['name' => 'Copenhagen Drift', 'color' => '#B4530A'],
    4 => ['name' => 'Dunkelt Lys', 'color' => '#8B5CF6'],
];

const USERS = [
    // Id 1 is App\User\CurrentUser::ID — the stand-in for the logged-in user.
    1 => ['name' => 'Testbruger Et', 'email' => 'e2e-user-1@ovelokale.test'],
    2 => ['name' => 'Testbruger To', 'email' => 'e2e-user-2@ovelokale.test'],
];

/** Only user 1 in band 1, so "Aurora" is the sole own-band in the dialog. */
const BAND_MEMBERS = [[1, 1]];

$anchorMonday = (new DateTimeImmutable('today'))->modify('monday this week');
$nextMonday = $anchorMonday->modify('+7 days');

/** @param DateTimeImmutable $monday */
$weekDates = static fn (DateTimeImmutable $monday): array => array_map(
    static fn (int $i): string => $monday->modify("+{$i} days")->format('Y-m-d'),
    range(0, 6)
);

$anchorDates = $weekDates($anchorMonday);
$nextDates = $weekDates($nextMonday);

$anchorIsoWeek = (int) $anchorMonday->format('W');
$nextIsoWeek = (int) $nextMonday->format('W');
$anchorParity = WeekParity::forIsoWeekNumber($anchorIsoWeek);
$otherParity = $anchorParity === WeekParity::Odd ? WeekParity::Even : WeekParity::Odd;

// Day indexes are 0 = Monday .. 6 = Sunday, matching the order of the day
// columns the week view renders.
$bookings = [
    'conflict_target' => ['band' => 1, 'day_index' => 0, 'date' => $anchorDates[0], 'start' => '17:00', 'end' => '19:00'],
    'midweek' => ['band' => 2, 'day_index' => 2, 'date' => $anchorDates[2], 'start' => '18:00', 'end' => '20:00'],
    // A deliberately overlapping pair. The API refuses to create these (ADR
    // 0002), but bad data can still reach the table, and the week view draws
    // both with a red outline — a state only a real browser can show.
    'clash_early' => ['band' => 2, 'day_index' => 5, 'date' => $anchorDates[5], 'start' => '15:00', 'end' => '17:00'],
    'clash_late' => ['band' => 3, 'day_index' => 5, 'date' => $anchorDates[5], 'start' => '16:00', 'end' => '18:00'],
    // Lives in the following week only, so week navigation has something to prove.
    'next_week_marker' => ['band' => 3, 'day_index' => 2, 'date' => $nextDates[2], 'start' => '17:00', 'end' => '19:00'],
];

$recurring = [
    // Bounded by an end date (ADR 0001): occurs in the anchor week, never after it.
    'bounded' => [
        'band' => 4, 'day_index' => 3, 'start' => '19:00', 'end' => '21:00',
        'parity' => WeekParity::All->value, 'start_date' => $anchorDates[0], 'end_date' => $anchorDates[6],
    ],
    // Matches the anchor week's parity: visible now, gone next week.
    'anchor_parity' => [
        'band' => 2, 'day_index' => 4, 'start' => '17:00', 'end' => '19:00',
        'parity' => $anchorParity->value, 'start_date' => $anchorDates[0], 'end_date' => null,
    ],
    // The opposite parity: hidden now, visible next week.
    'other_parity' => [
        'band' => 3, 'day_index' => 4, 'start' => '20:00', 'end' => '22:00',
        'parity' => $otherParity->value, 'start_date' => $anchorDates[0], 'end_date' => null,
    ],
];

$pdo = db();
$pdo->beginTransaction();

try {
    foreach (['bookings', 'recurring_slots', 'band_members', 'bands', 'users'] as $table) {
        $pdo->exec("DELETE FROM {$table}");
    }

    $insertUser = $pdo->prepare('INSERT INTO users (id, name, email) VALUES (:id, :name, :email)');
    foreach (USERS as $id => $user) {
        $insertUser->execute([':id' => $id, ':name' => $user['name'], ':email' => $user['email']]);
    }

    $insertBand = $pdo->prepare('INSERT INTO bands (id, name, color) VALUES (:id, :name, :color)');
    foreach (BANDS as $id => $band) {
        $insertBand->execute([':id' => $id, ':name' => $band['name'], ':color' => $band['color']]);
    }

    $insertMember = $pdo->prepare('INSERT INTO band_members (band_id, user_id) VALUES (:band_id, :user_id)');
    foreach (BAND_MEMBERS as [$bandId, $userId]) {
        $insertMember->execute([':band_id' => $bandId, ':user_id' => $userId]);
    }

    $insertBooking = $pdo->prepare(
        'INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
         VALUES (:band_id, :start_time, :end_time, :booked_by_user_id)'
    );

    foreach ($bookings as $booking) {
        $insertBooking->execute([
            ':band_id' => $booking['band'],
            ':start_time' => "{$booking['date']} {$booking['start']}:00",
            ':end_time' => "{$booking['date']} {$booking['end']}:00",
            ':booked_by_user_id' => 1,
        ]);
    }

    $insertSlot = $pdo->prepare(
        'INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date, end_date)
         VALUES (:band_id, :day_of_week, :start_time, :end_time, :week_parity, :start_date, :end_date)'
    );

    foreach ($recurring as $slot) {
        $insertSlot->execute([
            ':band_id' => $slot['band'],
            ':day_of_week' => $slot['day_index'] + 1,
            ':start_time' => "{$slot['start']}:00",
            ':end_time' => "{$slot['end']}:00",
            ':week_parity' => $slot['parity'],
            ':start_date' => $slot['start_date'],
            ':end_date' => $slot['end_date'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();

    throw $e;
}

/** Swap band ids for band names — the specs pick bands by the label the UI shows. */
$withBandName = static function (array $row): array {
    $row['band'] = BANDS[$row['band']]['name'];

    return $row;
};

$manifest = [
    'schema' => 1,
    'timezone' => date_default_timezone_get(),
    'anchor_week' => [
        'monday' => $anchorDates[0],
        'iso_week' => $anchorIsoWeek,
        'parity' => $anchorParity->value,
        'dates' => $anchorDates,
    ],
    'next_week' => [
        'monday' => $nextDates[0],
        'iso_week' => $nextIsoWeek,
        'parity' => $otherParity->value,
        'dates' => $nextDates,
    ],
    'bands' => array_values(array_map(
        static fn (int $id, array $band): array => ['id' => $id, 'name' => $band['name'], 'color' => $band['color']],
        array_keys(BANDS),
        BANDS
    )),
    'own_band' => BANDS[BAND_MEMBERS[0][0]]['name'],
    'bookings' => array_map($withBandName, $bookings),
    'recurring' => array_map($withBandName, $recurring),
    // Days deliberately left empty, for the specs that create something.
    'free' => [
        'adhoc' => ['day_index' => 1, 'date' => $anchorDates[1], 'hour' => 9, 'band' => BANDS[2]['name']],
        'recurring' => ['day_index' => 6, 'date' => $anchorDates[6], 'hour' => 8, 'band' => BANDS[4]['name']],
    ],
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

fwrite(STDERR, sprintf(
    "Seeded e2e fixture: week %d (%s), %d bookings, %d recurring slots, anchored on %s.\n",
    $anchorIsoWeek,
    $anchorParity->value,
    count($bookings),
    count($recurring),
    $anchorDates[0]
));
