<?php

declare(strict_types=1);

use App\Http\CalendarController;

beforeEach(function () {
    db()->exec("INSERT INTO users (id, name, email) VALUES (7, 'Jane Doe', 'jane@example.com')");
});

test('returns ad-hoc bookings and parity-matching recurring slots for the requested week', function () {
    // Monday 2026-09-07, ISO week 37 (odd).
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-09 19:00:00', '2026-09-09 21:00:00', 7)");

    // Outside the requested week — must not show up.
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-20 19:00:00', '2026-09-20 21:00:00', 7)");

    // Odd-week slot — matches week 37.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (2, 2, '18:00:00', '20:00:00', 'odd')");

    // Even-week slot — must not match week 37.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (3, 3, '18:00:00', '20:00:00', 'even')");

    $result = CalendarController::index(['week_start' => '2026-09-07']);

    expect($result['status'])->toBe(200);
    expect($result['body']['week_parity'])->toBe('odd');
    expect($result['body']['iso_week_number'])->toBe(37);
    expect($result['body']['bookings'])->toHaveCount(2);
    expect($result['body']['bookings'][0]['source'])->toBe('recurring');
    expect($result['body']['bookings'][1]['source'])->toBe('ad_hoc');
    expect($result['body']['bookings'][1]['booked_by_user_name'])->toBe('Jane Doe');
});

test('odd-parity recurring slots do not appear in an even week', function () {
    // Odd-week slot — matched week 37, must not match week 38.
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (2, 2, '18:00:00', '20:00:00', 'odd')");

    // Monday 2026-09-14, ISO week 38 (even).
    $result = CalendarController::index(['week_start' => '2026-09-14']);

    expect($result['status'])->toBe(200);
    expect($result['body']['week_parity'])->toBe('even');
    expect($result['body']['iso_week_number'])->toBe(38);
    expect($result['body']['bookings'])->toHaveCount(0);
});

test('a week_parity=all recurring slot appears in both odd and even weeks', function () {
    db()->exec("INSERT INTO recurring_slots (band_id, day_of_week, start_time, end_time, week_parity)
        VALUES (2, 2, '18:00:00', '20:00:00', 'all')");

    // Monday 2026-09-07, ISO week 37 (odd).
    $oddResult = CalendarController::index(['week_start' => '2026-09-07']);
    // Monday 2026-09-14, ISO week 38 (even).
    $evenResult = CalendarController::index(['week_start' => '2026-09-14']);

    expect($oddResult['body']['bookings'])->toHaveCount(1);
    expect($oddResult['body']['bookings'][0])->toMatchArray([
        'source' => 'recurring',
        'band_id' => 2,
        'date' => '2026-09-08',
    ]);

    expect($evenResult['body']['bookings'])->toHaveCount(1);
    expect($evenResult['body']['bookings'][0])->toMatchArray([
        'source' => 'recurring',
        'band_id' => 2,
        'date' => '2026-09-15',
    ]);
});

test('an ad-hoc booking that only partially overlaps the week is still included', function () {
    // Starts Sunday 2026-09-06 (the day before the week starts) and ends
    // Monday 2026-09-07 (the first day of the week) — never starts inside
    // the week, but overlaps it.
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-06 22:00:00', '2026-09-07 01:00:00', 7)");

    $result = CalendarController::index(['week_start' => '2026-09-07']);

    expect($result['body']['bookings'])->toHaveCount(1);
    expect($result['body']['bookings'][0])->toMatchArray([
        'source' => 'ad_hoc',
        'start_time' => '2026-09-06 22:00:00',
        'end_time' => '2026-09-07 01:00:00',
    ]);
});

test('filters occurrences by band_id', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-09 19:00:00', '2026-09-09 21:00:00', 7)");
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (2, '2026-09-10 19:00:00', '2026-09-10 21:00:00', 7)");

    $result = CalendarController::index(['week_start' => '2026-09-07', 'band_id' => '2']);

    expect($result['body']['bookings'])->toHaveCount(1);
    expect($result['body']['bookings'][0]['band_id'])->toBe(2);
});

test('rejects a missing week_start', function () {
    $result = CalendarController::index([]);

    expect($result['status'])->toBe(400);
});

test('rejects an invalid band_id', function () {
    $result = CalendarController::index(['week_start' => '2026-09-07', 'band_id' => 'not-a-number']);

    expect($result['status'])->toBe(400);
});
