<?php

declare(strict_types=1);

use App\Http\BookingController;

function bookingCount(): int
{
    $statement = db()->query('SELECT COUNT(*) FROM bookings');

    if ($statement === false) {
        throw new RuntimeException('Query failed.');
    }

    return (int) $statement->fetchColumn();
}

beforeEach(function () {
    db()->exec("INSERT INTO users (id, name, email) VALUES (1, 'Stub User', 'stub@example.com')");
    db()->exec("INSERT INTO bands (id, name) VALUES (1, 'The Wailers'), (2, 'The Beatles')");
});

test('creates an ad-hoc booking attributed to the stubbed current user', function () {
    $result = BookingController::store([
        'band_id' => 1,
        'start_time' => '2026-09-09 19:00:00',
        'end_time' => '2026-09-09 21:00:00',
    ]);

    expect($result['status'])->toBe(201);
    expect($result['body']['booking'])->toMatchArray([
        'source' => 'ad_hoc',
        'band_id' => 1,
        'band_name' => 'The Wailers',
        'start_time' => '2026-09-09 19:00:00',
        'end_time' => '2026-09-09 21:00:00',
        'booked_by_user_id' => 1,
    ]);

    $statement = db()->query('SELECT * FROM bookings');

    if ($statement === false) {
        throw new RuntimeException('Query failed.');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    expect($row)->not->toBeFalse();
    expect((int) $row['band_id'])->toBe(1);
    expect((int) $row['booked_by_user_id'])->toBe(1);
});

test('rejects a booking that conflicts with an existing occurrence', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (2, '2026-09-09 19:00:00', '2026-09-09 21:00:00', 1)");

    $result = BookingController::store([
        'band_id' => 1,
        'start_time' => '2026-09-09 20:00:00',
        'end_time' => '2026-09-09 22:00:00',
    ]);

    expect($result['status'])->toBe(409);
    expect($result['body']['error'])->toBeString();
    expect($result['body']['conflicts'])->toHaveCount(1);
    expect($result['body']['conflicts'][0])->toMatchArray(['band_id' => 2]);

    expect(bookingCount())->toBe(1);
});

test('a same-band overlap still conflicts, per ADR 0002', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (1, '2026-09-09 19:00:00', '2026-09-09 21:00:00', 1)");

    $result = BookingController::store([
        'band_id' => 1,
        'start_time' => '2026-09-09 20:00:00',
        'end_time' => '2026-09-09 22:00:00',
    ]);

    expect($result['status'])->toBe(409);
});

test('rejects a missing band_id', function () {
    $result = BookingController::store([
        'start_time' => '2026-09-09 19:00:00',
        'end_time' => '2026-09-09 21:00:00',
    ]);

    expect($result['status'])->toBe(400);
});

test('rejects a band_id that does not exist', function () {
    $result = BookingController::store([
        'band_id' => 999,
        'start_time' => '2026-09-09 19:00:00',
        'end_time' => '2026-09-09 21:00:00',
    ]);

    expect($result['status'])->toBe(400);
});

test('rejects missing start_time or end_time', function () {
    $result = BookingController::store(['band_id' => 1, 'start_time' => '2026-09-09 19:00:00']);

    expect($result['status'])->toBe(400);
});

test('rejects an end_time before start_time', function () {
    $result = BookingController::store([
        'band_id' => 1,
        'start_time' => '2026-09-09 21:00:00',
        'end_time' => '2026-09-09 19:00:00',
    ]);

    expect($result['status'])->toBe(400);
});

test('rejects a range that spans midnight', function () {
    $result = BookingController::store([
        'band_id' => 1,
        'start_time' => '2026-09-09 23:00:00',
        'end_time' => '2026-09-10 01:00:00',
    ]);

    expect($result['status'])->toBe(400);
});

test('conflicts endpoint reports overlapping occurrences without creating a booking', function () {
    db()->exec("INSERT INTO bookings (band_id, start_time, end_time, booked_by_user_id)
        VALUES (2, '2026-09-09 19:00:00', '2026-09-09 21:00:00', 1)");

    $result = BookingController::conflicts([
        'start_time' => '2026-09-09 20:00:00',
        'end_time' => '2026-09-09 22:00:00',
    ]);

    expect($result['status'])->toBe(200);
    expect($result['body']['conflicts'])->toHaveCount(1);
    expect(bookingCount())->toBe(1);
});

test('conflicts endpoint returns an empty list when nothing overlaps', function () {
    $result = BookingController::conflicts([
        'start_time' => '2026-09-09 20:00:00',
        'end_time' => '2026-09-09 22:00:00',
    ]);

    expect($result['status'])->toBe(200);
    expect($result['body']['conflicts'])->toBe([]);
});

test('conflicts endpoint rejects a missing start_time', function () {
    $result = BookingController::conflicts(['end_time' => '2026-09-09 22:00:00']);

    expect($result['status'])->toBe(400);
});
