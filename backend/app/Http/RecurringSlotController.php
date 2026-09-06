<?php

declare(strict_types=1);

namespace App\Http;

use App\Band\BandRepository;
use App\Calendar\ConflictChecker;
use App\Calendar\RecurringSlotRepository;
use App\Calendar\WeekParity;
use DateTimeImmutable;
use Exception;

final class RecurringSlotController
{
    /**
     * POST /api/recurring-slots — body: {band_id, start_date, end_date?, start_time, end_time, week_parity}
     *
     * `day_of_week` is derived from `start_date`'s weekday (per ADR 0001).
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

        $pattern = self::parsePattern($body);

        if (isset($pattern['error'])) {
            return ['status' => 400, 'body' => ['error' => $pattern['error']]];
        }

        $bandId = (int) $bandIdParam;

        if ((new BandRepository(db()))->findById($bandId) === null) {
            return [
                'status' => 400,
                'body' => ['error' => 'band_id refers to an unknown band'],
            ];
        }

        /** @var array{dayOfWeek: int, startTime: string, endTime: string, weekParity: WeekParity, startDate: string, endDate: ?string} $pattern */
        $conflicts = (new ConflictChecker(db()))->findConflictsForNewRecurringSlot(
            $pattern['dayOfWeek'],
            $pattern['startTime'],
            $pattern['endTime'],
            $pattern['weekParity'],
            $pattern['startDate'],
            $pattern['endDate'],
        );

        if ($conflicts !== []) {
            return [
                'status' => 409,
                'body' => [
                    'error' => 'This pattern conflicts with an existing occurrence',
                    'conflicts' => $conflicts,
                ],
            ];
        }

        $slot = (new RecurringSlotRepository(db()))->create(
            $bandId,
            $pattern['dayOfWeek'],
            $pattern['startTime'],
            $pattern['endTime'],
            $pattern['weekParity'],
            $pattern['startDate'],
            $pattern['endDate'],
        );

        return [
            'status' => 201,
            'body' => ['recurring_slot' => $slot->toArray()],
        ];
    }

    /**
     * GET /api/recurring-slots/conflicts?start_date=...&end_date=...&start_time=...&end_time=...&week_parity=...
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
        $pattern = self::parsePattern($query);

        if (isset($pattern['error'])) {
            return ['status' => 400, 'body' => ['error' => $pattern['error']]];
        }

        /** @var array{dayOfWeek: int, startTime: string, endTime: string, weekParity: WeekParity, startDate: string, endDate: ?string} $pattern */
        return [
            'status' => 200,
            'body' => [
                'conflicts' => (new ConflictChecker(db()))->findConflictsForNewRecurringSlot(
                    $pattern['dayOfWeek'],
                    $pattern['startTime'],
                    $pattern['endTime'],
                    $pattern['weekParity'],
                    $pattern['startDate'],
                    $pattern['endDate'],
                ),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{error: string}|array{dayOfWeek: int, startTime: string, endTime: string, weekParity: WeekParity, startDate: string, endDate: ?string}
     */
    private static function parsePattern(array $input): array
    {
        $startDateParam = $input['start_date'] ?? null;
        $endDateParam = $input['end_date'] ?? null;
        $startTimeParam = $input['start_time'] ?? null;
        $endTimeParam = $input['end_time'] ?? null;
        $weekParityParam = $input['week_parity'] ?? null;

        if (!is_string($startDateParam) || $startDateParam === '') {
            return ['error' => 'start_date is required'];
        }

        if (!is_string($startTimeParam) || $startTimeParam === '' || !is_string($endTimeParam) || $endTimeParam === '') {
            return ['error' => 'start_time and end_time are required'];
        }

        if (!is_string($weekParityParam) || WeekParity::tryFrom($weekParityParam) === null) {
            return ['error' => 'week_parity must be one of: odd, even, all'];
        }

        try {
            $startDate = new DateTimeImmutable($startDateParam);
            $startTime = new DateTimeImmutable($startDateParam . ' ' . $startTimeParam);
            $endTime = new DateTimeImmutable($startDateParam . ' ' . $endTimeParam);
        } catch (Exception) {
            return ['error' => 'start_date, start_time, and end_time must be valid'];
        }

        if ($endTime <= $startTime) {
            return ['error' => 'end_time must be after start_time'];
        }

        $endDate = null;

        if ($endDateParam !== null && $endDateParam !== '') {
            if (!is_string($endDateParam)) {
                return ['error' => 'end_date must be a valid date'];
            }

            try {
                $endDateObj = new DateTimeImmutable($endDateParam);
            } catch (Exception) {
                return ['error' => 'end_date must be a valid date'];
            }

            if ($endDateObj < $startDate) {
                return ['error' => 'end_date must be on or after start_date'];
            }

            $endDate = $endDateObj->format('Y-m-d');
        }

        return [
            'dayOfWeek' => (int) $startDate->format('N'),
            'startTime' => $startTime->format('H:i:s'),
            'endTime' => $endTime->format('H:i:s'),
            'weekParity' => WeekParity::from($weekParityParam),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate,
        ];
    }
}
