<?php

declare(strict_types=1);

namespace App\Calendar;

use DateTimeImmutable;
use PDO;

/**
 * Per ADR 0002, a conflict is any overlap on the same date, same-band or
 * cross-band alike — so this returns every overlapping occurrence
 * regardless of band, rather than taking a candidate band to filter by.
 */
final readonly class ConflictChecker
{
    private BookingRepository $bookingRepository;

    private RecurringSlotRepository $recurringSlotRepository;

    public function __construct(PDO $pdo)
    {
        $this->bookingRepository = new BookingRepository($pdo);
        $this->recurringSlotRepository = new RecurringSlotRepository($pdo);
    }

    /**
     * Every existing occurrence (ad-hoc booking or recurring-slot
     * occurrence, any band) whose time range overlaps [$startTime, $endTime).
     *
     * @return list<array<string, mixed>>
     */
    public function findConflicts(DateTimeImmutable $startTime, DateTimeImmutable $endTime): array
    {
        $conflicts = [];

        foreach ($this->bookingRepository->findOverlappingRange($startTime, $endTime) as $booking) {
            $conflicts[] = $booking->toArray();
        }

        $date = $startTime->setTime(0, 0);
        $dateString = $date->format('Y-m-d');
        $dayOfWeek = (int) $date->format('N');
        $weekParity = WeekParity::forIsoWeekNumber((int) $date->format('W'));

        foreach ($this->recurringSlotRepository->findMatchingParity($weekParity, null) as $slot) {
            if ($slot->dayOfWeek !== $dayOfWeek) {
                continue;
            }

            if ($dateString < $slot->startDate || ($slot->endDate !== null && $dateString > $slot->endDate)) {
                continue;
            }

            $slotStart = new DateTimeImmutable($dateString . ' ' . $slot->startTime);
            $slotEnd = new DateTimeImmutable($dateString . ' ' . $slot->endTime);

            if ($slotStart < $endTime && $slotEnd > $startTime) {
                $conflicts[] = [
                    'source' => 'recurring',
                    'recurring_slot_id' => $slot->id,
                    'band_id' => $slot->bandId,
                    'band_name' => $slot->bandName,
                    'day_of_week' => $slot->dayOfWeek,
                    'date' => $dateString,
                    'start_time' => $slotStart->format('Y-m-d H:i:s'),
                    'end_time' => $slotEnd->format('Y-m-d H:i:s'),
                    'week_parity' => $slot->weekParity->value,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Every existing occurrence (ad-hoc booking or recurring slot, any band)
     * that would overlap a *new* recurring slot with the given pattern,
     * across every week it would ever apply — not just its first
     * occurrence. Two parities are compatible (and so checked for overlap)
     * unless they're the fixed 'odd'/'even' pair, which per the domain
     * glossary never land on the same calendar week.
     *
     * @return list<array<string, mixed>>
     */
    public function findConflictsForNewRecurringSlot(
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        WeekParity $weekParity,
        string $startDate,
        ?string $endDate,
    ): array {
        $conflicts = [];

        foreach (
            $this->bookingRepository->findOverlappingPattern($dayOfWeek, $startTime, $endTime, $startDate, $endDate) as $booking
        ) {
            $bookingParity = WeekParity::forIsoWeekNumber((int) $booking->startTime->format('W'));

            if (self::parityCompatible($weekParity, $bookingParity)) {
                $conflicts[] = $booking->toArray();
            }
        }

        foreach (
            $this->recurringSlotRepository->findOverlappingPattern($dayOfWeek, $startTime, $endTime, $startDate, $endDate) as $slot
        ) {
            if (self::parityCompatible($weekParity, $slot->weekParity)) {
                $conflicts[] = [
                    'source' => 'recurring_pattern',
                    'id' => $slot->id,
                    'band_id' => $slot->bandId,
                    'band_name' => $slot->bandName,
                    'day_of_week' => $slot->dayOfWeek,
                    'start_time' => $slot->startTime,
                    'end_time' => $slot->endTime,
                    'week_parity' => $slot->weekParity->value,
                    'start_date' => $slot->startDate,
                    'end_date' => $slot->endDate,
                ];
            }
        }

        return $conflicts;
    }

    private static function parityCompatible(WeekParity $a, WeekParity $b): bool
    {
        return $a === WeekParity::All || $b === WeekParity::All || $a === $b;
    }
}
