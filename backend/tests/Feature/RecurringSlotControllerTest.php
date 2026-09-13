<?php

declare(strict_types=1);

use App\Http\RecurringSlotController;

function recurringSlotCount(): int
{
    $statement = db()->query('SELECT COUNT(*) FROM recurring_slots');

    if ($statement === false) {
        throw new RuntimeException('Query failed.');
    }

    return (int) $statement->fetchColumn();
}

beforeEach(function () {
    db()->exec("INSERT INTO users (id, name, email) VALUES (1, 'Stub User', 'stub@example.com')");
    db()->exec("INSERT INTO bands (id, name) VALUES (1, 'The Wailers'), (2, 'The Beatles')");
});

test('creates a recurring slot, deriving day_of_week from start_date', function () {
    // 2026-09-08 is a Tuesday (ISO day_of_week 2).
    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(201);
    expect($result['body']['recurring_slot'])->toMatchArray([
        'band_id' => 1,
        'band_name' => 'The Wailers',
        'day_of_week' => 2,
        'start_time' => '18:00:00',
        'end_time' => '20:00:00',
        'week_parity' => 'all',
        'start_date' => '2026-09-08',
        'end_date' => null,
    ]);

    expect(recurringSlotCount())->toBe(1);
});

test('creates a recurring slot with an end_date', function () {
    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'end_date' => '2026-12-01',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'odd',
    ]);

    expect($result['status'])->toBe(201);
    expect($result['body']['recurring_slot'])->toMatchArray(['end_date' => '2026-12-01']);
});

test('rejects a recurring slot that conflicts with an existing ad-hoc booking on a future matching date', function () {
    // 2026-09-15 is the Tuesday following 2026-09-08.
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (2, '2026-09-15 19:00:00', '2026-09-15 21:00:00', 1)");

    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(409);
    expect($result['body']['conflicts'])->toHaveCount(1);
    expect(recurringSlotCount())->toBe(0);
});

test('rejects a recurring slot that conflicts with an existing recurring slot', function () {
    db()->exec("INSERT INTO recurring_slots (band_id, booked_by_user_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 1, 2, '18:00:00', '20:00:00', 'all', '2020-01-01')");

    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'start_time' => '19:00',
        'end_time' => '21:00',
        'week_parity' => 'odd',
    ]);

    expect($result['status'])->toBe(409);
    expect(recurringSlotCount())->toBe(1);
});

test('allows two recurring slots on opposite fixed parities at the same day/time (per the domain glossary)', function () {
    db()->exec("INSERT INTO recurring_slots (band_id, booked_by_user_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 1, 2, '18:00:00', '20:00:00', 'even', '2020-01-01')");

    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'odd',
    ]);

    expect($result['status'])->toBe(201);
    expect(recurringSlotCount())->toBe(2);
});

test('creates a personal recurring slot attributed to the stubbed current user', function () {
    $result = RecurringSlotController::store([
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(201);
    expect($result['body']['recurring_slot'])->toMatchArray([
        'band_id' => null,
        'band_name' => null,
        'day_of_week' => 2,
        'booked_by_user_id' => 1,
        'booked_by_user_name' => 'Stub User',
    ]);

    $statement = db()->query('SELECT * FROM recurring_slots');

    if ($statement === false) {
        throw new RuntimeException('Query failed.');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    expect($row)->not->toBeFalse();
    expect($row['band_id'])->toBeNull();
    expect((int) $row['booked_by_user_id'])->toBe(1);
});

test('creates a personal recurring slot when band_id is explicitly null', function () {
    $result = RecurringSlotController::store([
        'band_id' => null,
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(201);
    expect($result['body']['recurring_slot'])->toMatchArray([
        'band_id' => null,
        'booked_by_user_id' => 1,
    ]);
    expect(recurringSlotCount())->toBe(1);
});

test('rejects a personal recurring slot that conflicts with an existing band occurrence', function () {
    db()->exec("INSERT INTO recurring_slots (band_id, booked_by_user_id, day_of_week, start_time, end_time, week_parity, start_date)
        VALUES (2, 1, 2, '18:00:00', '20:00:00', 'all', '2020-01-01')");

    $result = RecurringSlotController::store([
        'start_date' => '2026-09-08',
        'start_time' => '19:00',
        'end_time' => '21:00',
        'week_parity' => 'odd',
    ]);

    expect($result['status'])->toBe(409);
    expect($result['body']['conflicts'])->toHaveCount(1);
    expect($result['body']['conflicts'][0])->toMatchArray(['band_id' => 2]);
    expect(recurringSlotCount())->toBe(1);
});

test('rejects a non-numeric band_id', function () {
    $result = RecurringSlotController::store([
        'band_id' => 'not-a-band',
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(400);
    expect(recurringSlotCount())->toBe(0);
});

test('rejects a band_id that does not exist', function () {
    $result = RecurringSlotController::store([
        'band_id' => 999,
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(400);
});

test('rejects an end_time before start_time', function () {
    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'start_time' => '20:00',
        'end_time' => '18:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(400);
});

test('rejects an end_date before start_date', function () {
    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'end_date' => '2026-09-01',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(400);
});

test('rejects an invalid week_parity', function () {
    $result = RecurringSlotController::store([
        'band_id' => 1,
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'biweekly',
    ]);

    expect($result['status'])->toBe(400);
});

test('conflicts endpoint reports overlapping occurrences without creating a slot', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (2, '2026-09-15 19:00:00', '2026-09-15 21:00:00', 1)");

    $result = RecurringSlotController::conflicts([
        'start_date' => '2026-09-08',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'week_parity' => 'all',
    ]);

    expect($result['status'])->toBe(200);
    expect($result['body']['conflicts'])->toHaveCount(1);
    expect(recurringSlotCount())->toBe(0);
});
