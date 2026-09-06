<?php

declare(strict_types=1);

namespace App\Calendar;

use DateTimeImmutable;
use PDO;

final readonly class CalendarLookup
{
    private BookingRepository $bookingRepository;

    private RecurringSlotRepository $recurringSlotRepository;

    public function __construct(PDO $pdo)
    {
        $this->bookingRepository = new BookingRepository($pdo);
        $this->recurringSlotRepository = new RecurringSlotRepository($pdo);
    }

    /**
     * Resolve every occurrence (ad-hoc + recurring) that falls in the week
     * starting on $weekStart (a Monday), optionally filtered to one band.
     *
     * @return array{
     *     week_start: string,
     *     week_end: string,
     *     iso_week_number: int,
     *     iso_year: int,
     *     week_parity: string,
     *     bookings: list<array<string, mixed>>,
     * }
     */
    public function forWeek(DateTimeImmutable $weekStart, ?int $bandId = null): array
    {
        $weekStart = $weekStart->setTime(0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        $isoWeekNumber = (int) $weekStart->format('W');
        $weekParity = WeekParity::forIsoWeekNumber($isoWeekNumber);

        $bookings = $this->bookingRepository->findOverlapping($weekStart, $weekEnd, $bandId);
        $recurringSlots = $this->recurringSlotRepository->findMatchingParity($weekParity, $bandId);

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'iso_week_number' => $isoWeekNumber,
            'iso_year' => (int) $weekStart->format('o'),
            'week_parity' => $weekParity->value,
            'bookings' => self::resolveOccurrences($weekStart, $bookings, $recurringSlots),
        ];
    }

    /**
     * Pure merge of already-fetched bookings and recurring slots into a
     * single, time-sorted list of occurrences for the given week.
     *
     * @param list<Booking> $bookings
     * @param list<RecurringSlot> $recurringSlots
     *
     * @return list<array<string, mixed>>
     */
    public static function resolveOccurrences(DateTimeImmutable $weekStart, array $bookings, array $recurringSlots): array
    {
        $occurrences = [];

        foreach ($bookings as $booking) {
            $occurrences[] = $booking->toArray();
        }

        foreach ($recurringSlots as $slot) {
            $date = $weekStart->modify(sprintf('+%d days', $slot->dayOfWeek - 1));

            $occurrences[] = [
                'source' => 'recurring',
                'recurring_slot_id' => $slot->id,
                'band_id' => $slot->bandId,
                'day_of_week' => $slot->dayOfWeek,
                'date' => $date->format('Y-m-d'),
                'start_time' => $date->format('Y-m-d') . ' ' . $slot->startTime,
                'end_time' => $date->format('Y-m-d') . ' ' . $slot->endTime,
                'week_parity' => $slot->weekParity->value,
            ];
        }

        usort($occurrences, static fn (array $a, array $b): int => (string) $a['start_time'] <=> (string) $b['start_time']);

        return $occurrences;
    }
}
