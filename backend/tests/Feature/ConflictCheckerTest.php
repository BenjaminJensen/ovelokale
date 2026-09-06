<?php

declare(strict_types=1);

use App\Calendar\ConflictChecker;
use App\Calendar\WeekParity;

beforeEach(function () {
    db()->exec("INSERT INTO users (id, name, email) VALUES (7, 'Jane Doe', 'jane@example.com')");
    db()->exec("INSERT INTO bands (id, name) VALUES (1, 'The Wailers'), (2, 'The Beatles')");
});

test('an overlapping ad-hoc booking is reported as a conflict', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-09 19:00:00', '2026-09-09 21:00:00', 7)");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-09 20:00:00'),
        new DateTimeImmutable('2026-09-09 22:00:00'),
    );

    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0])->toMatchArray([
        'source' => 'ad_hoc',
        'band_id' => 1,
        'start_time' => '2026-09-09 19:00:00',
        'end_time' => '2026-09-09 21:00:00',
    ]);
});

test('an ad-hoc booking on the same band is reported as a conflict just like a different band would be', function () {
    // Both bands 1 and 1 (same band) — a same-band overlap must still block.
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-09 19:00:00', '2026-09-09 21:00:00', 7)");
    // A different band, also overlapping.
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (2, '2026-09-09 20:30:00', '2026-09-09 22:00:00', 7)");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-09 20:00:00'),
        new DateTimeImmutable('2026-09-09 21:30:00'),
    );

    expect($conflicts)->toHaveCount(2);
    $bandIds = array_column($conflicts, 'band_id');
    expect($bandIds)->toEqualCanonicalizing([1, 2]);
});

test('back-to-back ad-hoc bookings that only touch do not conflict', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-09 18:00:00', '2026-09-09 20:00:00', 7)");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-09 20:00:00'),
        new DateTimeImmutable('2026-09-09 22:00:00'),
    );

    expect($conflicts)->toBe([]);
});

test('a non-overlapping ad-hoc booking on the same day returns an empty conflict list', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-09 08:00:00', '2026-09-09 10:00:00', 7)");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-09 19:00:00'),
        new DateTimeImmutable('2026-09-09 21:00:00'),
    );

    expect($conflicts)->toBe([]);
});

test('a parity- and day-matching recurring slot is reported as a conflict against a candidate ad-hoc range', function () {
    // Tuesday, 'odd' parity. 2026-09-08 is ISO week 37 (odd).
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'odd', '2020-01-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 19:00:00'),
        new DateTimeImmutable('2026-09-08 21:00:00'),
    );

    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0])->toMatchArray([
        'source' => 'recurring',
        'band_id' => 2,
        'day_of_week' => 2,
        'date' => '2026-09-08',
        'start_time' => '2026-09-08 18:00:00',
        'end_time' => '2026-09-08 20:00:00',
        'week_parity' => 'odd',
    ]);
});

test('an ad-hoc candidate overlapping both an ad-hoc booking and a recurring slot returns both', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-08 19:30:00', '2026-09-08 21:00:00', 7)");
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'all', '2020-01-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 19:00:00'),
        new DateTimeImmutable('2026-09-08 21:00:00'),
    );

    expect($conflicts)->toHaveCount(2);
    expect(array_column($conflicts, 'source'))->toEqualCanonicalizing(['ad_hoc', 'recurring']);
});

test('a recurring slot on a matching day but opposite week parity is not a conflict', function () {
    // 2026-09-08 is ISO week 37 (odd); an 'even'-only slot must not match.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'even', '2020-01-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 19:00:00'),
        new DateTimeImmutable('2026-09-08 21:00:00'),
    );

    expect($conflicts)->toBe([]);
});

test('a recurring slot on a different day of week is not a conflict', function () {
    // Wednesday slot checked against a Tuesday candidate.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 3, '18:00:00', '20:00:00', 'all', '2020-01-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 18:00:00'),
        new DateTimeImmutable('2026-09-08 20:00:00'),
    );

    expect($conflicts)->toBe([]);
});

test('a recurring slot is not a conflict for a candidate date before its start_date', function () {
    // Tuesday, 'all' parity, but the slot does not start until 2026-09-15.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'all', '2026-09-15')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 18:00:00'),
        new DateTimeImmutable('2026-09-08 20:00:00'),
    );

    expect($conflicts)->toBe([]);
});

test('a recurring slot is not a conflict for a candidate date after its end_date', function () {
    // Tuesday, 'all' parity, but the slot ended on 2026-09-01.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date, end_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'all', '2020-01-01', '2026-09-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 18:00:00'),
        new DateTimeImmutable('2026-09-08 20:00:00'),
    );

    expect($conflicts)->toBe([]);
});

test('a new recurring slot conflicts with an ad-hoc booking on a future matching date', function () {
    // Tuesday, 'all' parity, indefinite. 2026-09-08 and 2026-09-15 are both Tuesdays.
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-15 19:00:00', '2026-09-15 21:00:00', 7)");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflictsForNewRecurringSlot(
        2,
        '18:00:00',
        '20:00:00',
        WeekParity::All,
        '2026-09-08',
        null,
    );

    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0])->toMatchArray(['source' => 'ad_hoc', 'band_id' => 1]);
});

test('a new recurring slot does not conflict with an ad-hoc booking outside its date range', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-15 19:00:00', '2026-09-15 21:00:00', 7)");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflictsForNewRecurringSlot(
        2,
        '18:00:00',
        '20:00:00',
        WeekParity::All,
        '2026-09-08',
        '2026-09-08',
    );

    expect($conflicts)->toBe([]);
});

test('a new recurring slot conflicts with an overlapping existing recurring slot of compatible parity', function () {
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'all', '2020-01-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflictsForNewRecurringSlot(
        2,
        '19:00:00',
        '21:00:00',
        WeekParity::Odd,
        '2026-09-08',
        null,
    );

    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0])->toMatchArray(['band_id' => 2, 'week_parity' => 'all']);
});

test('a new recurring slot does not conflict with an existing slot of the opposite fixed parity', function () {
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'even', '2020-01-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflictsForNewRecurringSlot(
        2,
        '18:00:00',
        '20:00:00',
        WeekParity::Odd,
        '2026-09-08',
        null,
    );

    expect($conflicts)->toBe([]);
});

test('a new recurring slot does not conflict with a non-overlapping existing recurring slot date range', function () {
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity, start_date, end_date)
        VALUES (2, 2, '18:00:00', '20:00:00', 'all', '2020-01-01', '2026-01-01')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflictsForNewRecurringSlot(
        2,
        '18:00:00',
        '20:00:00',
        WeekParity::All,
        '2026-09-08',
        null,
    );

    expect($conflicts)->toBe([]);
});
