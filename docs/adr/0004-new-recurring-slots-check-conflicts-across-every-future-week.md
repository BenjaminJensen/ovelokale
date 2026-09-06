# Creating a recurring slot checks conflicts across every week it will ever apply, not just its first occurrence

Ad-hoc booking conflict checks (per ADR 0002) only ever need to look at one date, because an ad-hoc booking only ever occupies one date. A *new* recurring slot is different: once created, it produces an occurrence on every matching week from its `start_date` onward (indefinitely, if `end_date` is blank), so checking only the first occurrence would let it through cleanly today and then silently start colliding with an existing ad-hoc booking or another recurring slot three months from now — a conflict the hard-block in ADR 0002 is supposed to make impossible.

So `ConflictChecker::findConflictsForNewRecurringSlot` checks the candidate pattern (day-of-week, time range, week parity, date bounds) against two things, each expressed as a single bounded query rather than a loop enumerating individual weeks:

- Every ad-hoc booking on the matching day-of-week whose date falls in the candidate's date range and whose parity is compatible, regardless of how far in the future it is.
- Every other recurring slot whose day-of-week, time range, and date range overlap the candidate's, again filtered by parity compatibility.

Two week parities are compatible (and therefore checked for a real conflict) unless they're the fixed `odd`/`even` pair, which per the domain glossary can never land on the same calendar week. This means, for example, an `odd`-parity Tuesday 18:00–20:00 slot and an `even`-parity Tuesday 18:00–20:00 slot can coexist even though every other field matches.

We considered only checking the slot's first occurrence (`start_date`), matching the ad-hoc dialog's live-check shape exactly and reusing `findConflicts` as-is. We rejected that: it would make the hard-block a formality that a recurring slot can trivially step around just by not colliding on day one, which defeats the purpose of ADR 0002 for the one booking type where a single check point isn't enough.

We also considered enumerating every occurrence date between `start_date` and `end_date` (or some capped horizon for indefinite slots) and running the existing single-date `findConflicts` once per date. We rejected that too: it turns an indefinite slot into an unbounded loop, and even bounded it's needless work when the same answer is reachable as two direct SQL range queries — the ad-hoc query filters by day-of-week (via `WEEKDAY()`) and date bounds in SQL, while week-parity filtering happens in PHP against each row's own date, consistent with how `WeekParity::forIsoWeekNumber` is already used elsewhere in this codebase rather than reimplemented in SQL.
