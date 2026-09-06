<?php

declare(strict_types=1);

use App\Calendar\Booking;
use App\Calendar\CalendarLookup;
use App\Calendar\RecurringSlot;
use App\Calendar\WeekParity;

test('merges ad-hoc bookings and matching recurring slots into one sorted list', function () {
    $weekStart = new DateTimeImmutable('2026-09-07'); // Monday, ISO week 37 (odd)

    $bookings = [
        new Booking(
            id: 1,
            bandId: 10,
            startTime: new DateTimeImmutable('2026-09-09 19:00:00'),
            endTime: new DateTimeImmutable('2026-09-09 21:00:00'),
            bookedByUserId: 5,
            bookedByUserName: 'Jane Doe',
            bandName: 'The Wailers',
        ),
    ];

    $recurringSlots = [
        // Tuesday 18:00-20:00, "all" parity weeks -> should be included.
        new RecurringSlot(id: 2, bandId: 20, dayOfWeek: 2, startTime: '18:00:00', endTime: '20:00:00', weekParity: WeekParity::All, bandName: 'The Beatles', startDate: '2020-01-01', endDate: null),
    ];

    $occurrences = CalendarLookup::resolveOccurrences($weekStart, $bookings, $recurringSlots);

    expect($occurrences)->toHaveCount(2);

    // Recurring slot (Tue 2026-09-08) sorts before the ad-hoc booking (Wed 2026-09-09).
    expect($occurrences[0])->toMatchArray([
        'source' => 'recurring',
        'recurring_slot_id' => 2,
        'band_id' => 20,
        'band_name' => 'The Beatles',
        'day_of_week' => 2,
        'date' => '2026-09-08',
        'start_time' => '2026-09-08 18:00:00',
        'end_time' => '2026-09-08 20:00:00',
    ]);

    expect($occurrences[1])->toMatchArray([
        'source' => 'ad_hoc',
        'id' => 1,
        'band_id' => 10,
        'band_name' => 'The Wailers',
        'start_time' => '2026-09-09 19:00:00',
        'end_time' => '2026-09-09 21:00:00',
        'booked_by_user_id' => 5,
    ]);
});

test('resolves an empty week to an empty occurrence list', function () {
    $weekStart = new DateTimeImmutable('2026-09-07');

    expect(CalendarLookup::resolveOccurrences($weekStart, [], []))->toBe([]);
});
