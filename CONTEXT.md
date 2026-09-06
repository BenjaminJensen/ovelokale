# Ovelokale

Booking system for a shared band rehearsal room ("øvelokale"). Bands reserve time slots either as one-off bookings or as standing weekly patterns.

## Language

**Ad-hoc booking**:
A one-off reservation of the room for a specific date and time range. Stored in `bookings`.
_Avoid_: One-time booking, single booking

**Recurring slot**:
A standing weekly reservation defined by day-of-week, time range, and week parity, bounded by a required start date and an optional end date (blank means it repeats indefinitely — see ADR 0001). Stored in `recurring_slots`.
_Avoid_: Recurring booking, repeating booking

**Week parity**:
Whether a recurring slot applies to every week (`all`), only odd ISO week numbers (`odd`), or only even ISO week numbers (`even`). Presented to the user as three choices: "Hver uge" (all), "Ulige uger" (odd), "Lige uger" (even). Two slots on the same day/time but opposite parity (`odd` vs `even`) never land on the same calendar week, so they never conflict with each other; `all` conflicts with both.
_Avoid_: Biweekly, fortnightly

**Occurrence**:
A single dated appearance of either an ad-hoc booking or a recurring slot on the calendar, produced by resolving recurring slots against a specific week. Occurrences, not raw bookings/slots, are what the calendar displays.

**Conflict**:
Two occurrences whose time ranges overlap on the same date, regardless of whether they belong to the same band or different bands. The room can only be used by one rehearsal at a time — conflicts are never permitted, not even as a warn-and-allow.
_Avoid_: Double-booking (used interchangeably in conversation, but "conflict" is the canonical term in the UI and API)
