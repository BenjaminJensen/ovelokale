<?php

declare(strict_types=1);

namespace App\Http;

use App\Band\BandRepository;
use App\Calendar\BookingRepository;
use App\Calendar\ConflictChecker;
use App\User\CurrentUser;
use DateTimeImmutable;
use Exception;

final class BookingController
{
    /**
     * POST /api/bookings — body: {band_id, start_time, end_time}
     *
     * Re-checks for conflicts immediately before insert (via the same
     * ConflictChecker the live client-side check uses, per ADR 0002), so a
     * conflict created between the client's last check and this submission
     * is still caught.
     *
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function store(array $body): array
    {
        $bandIdParam = $body['band_id'] ?? null;

        if (!is_numeric($bandIdParam) || (int) $bandIdParam <= 0) {
            return [
                'status' => 400,
                'body' => ['error' => 'band_id must be a positive integer'],
            ];
        }

        $range = self::parseRange($body);

        if (isset($range['error'])) {
            return ['status' => 400, 'body' => ['error' => $range['error']]];
        }

        /** @var DateTimeImmutable $startTime */
        $startTime = $range['start'];
        /** @var DateTimeImmutable $endTime */
        $endTime = $range['end'];

        $bandId = (int) $bandIdParam;

        if ((new BandRepository(db()))->findById($bandId) === null) {
            return [
                'status' => 400,
                'body' => ['error' => 'band_id refers to an unknown band'],
            ];
        }

        $conflicts = (new ConflictChecker(db()))->findConflicts($startTime, $endTime);

        if ($conflicts !== []) {
            return [
                'status' => 409,
                'body' => [
                    'error' => 'This slot conflicts with an existing booking',
                    'conflicts' => $conflicts,
                ],
            ];
        }

        $booking = (new BookingRepository(db()))->create($bandId, $startTime, $endTime, CurrentUser::ID);

        return [
            'status' => 201,
            'body' => ['booking' => $booking->toArray()],
        ];
    }

    /**
     * GET /api/bookings/conflicts?start_time=...&end_time=...
     *
     * The live client-side check while the dialog is being edited — shares
     * ConflictChecker with store() so the two can't drift apart (ADR 0002).
     *
     * @param array<string, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function conflicts(array $query): array
    {
        $range = self::parseRange($query);

        if (isset($range['error'])) {
            return ['status' => 400, 'body' => ['error' => $range['error']]];
        }

        /** @var DateTimeImmutable $startTime */
        $startTime = $range['start'];
        /** @var DateTimeImmutable $endTime */
        $endTime = $range['end'];

        return [
            'status' => 200,
            'body' => ['conflicts' => (new ConflictChecker(db()))->findConflicts($startTime, $endTime)],
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{error: string}|array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    private static function parseRange(array $input): array
    {
        $startParam = $input['start_time'] ?? null;
        $endParam = $input['end_time'] ?? null;

        if (!is_string($startParam) || $startParam === '' || !is_string($endParam) || $endParam === '') {
            return ['error' => 'start_time and end_time are required'];
        }

        try {
            $startTime = new DateTimeImmutable($startParam);
            $endTime = new DateTimeImmutable($endParam);
        } catch (Exception) {
            return ['error' => 'start_time and end_time must be valid datetimes'];
        }

        if ($endTime <= $startTime) {
            return ['error' => 'end_time must be after start_time'];
        }

        if ($startTime->format('Y-m-d') !== $endTime->format('Y-m-d')) {
            return ['error' => 'start_time and end_time must fall on the same day, between 00:00 and 23:59'];
        }

        return ['start' => $startTime, 'end' => $endTime];
    }
}
