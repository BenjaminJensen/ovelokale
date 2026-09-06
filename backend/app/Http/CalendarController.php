<?php

declare(strict_types=1);

namespace App\Http;

use App\Calendar\CalendarLookup;
use DateTimeImmutable;
use Exception;

final class CalendarController
{
    /**
     * GET /api/calendar?week_start=YYYY-MM-DD[&band_id=N]
     *
     * @param array<string, mixed> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function index(array $query): array
    {
        $weekStartParam = $query['week_start'] ?? null;

        if (!is_string($weekStartParam) || $weekStartParam === '') {
            return [
                'status' => 400,
                'body' => ['error' => 'week_start is required (format: YYYY-MM-DD)'],
            ];
        }

        try {
            $weekStart = new DateTimeImmutable($weekStartParam);
        } catch (Exception) {
            return [
                'status' => 400,
                'body' => ['error' => 'week_start must be a valid date (format: YYYY-MM-DD)'],
            ];
        }

        $bandId = null;

        if (isset($query['band_id']) && $query['band_id'] !== '') {
            if (!is_numeric($query['band_id']) || (int) $query['band_id'] <= 0) {
                return [
                    'status' => 400,
                    'body' => ['error' => 'band_id must be a positive integer'],
                ];
            }

            $bandId = (int) $query['band_id'];
        }

        $lookup = new CalendarLookup(db());

        return [
            'status' => 200,
            'body' => $lookup->forWeek($weekStart, $bandId),
        ];
    }
}
