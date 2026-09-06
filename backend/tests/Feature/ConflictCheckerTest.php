<?php

declare(strict_types=1);

use App\Calendar\ConflictChecker;

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
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (2, 2, '18:00:00', '20:00:00', 'odd')");

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
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (2, 2, '18:00:00', '20:00:00', 'all')");

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
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (2, 2, '18:00:00', '20:00:00', 'even')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 19:00:00'),
        new DateTimeImmutable('2026-09-08 21:00:00'),
    );

    expect($conflicts)->toBe([]);
});

test('a recurring slot on a different day of week is not a conflict', function () {
    // Wednesday slot checked against a Tuesday candidate.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (2, 3, '18:00:00', '20:00:00', 'all')");

    $checker = new ConflictChecker(db());
    $conflicts = $checker->findConflicts(
        new DateTimeImmutable('2026-09-08 18:00:00'),
        new DateTimeImmutable('2026-09-08 20:00:00'),
    );

    expect($conflicts)->toBe([]);
});
