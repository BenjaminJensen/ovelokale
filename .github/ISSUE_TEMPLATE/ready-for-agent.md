---
name: Ready for agent
about: A refined spec with enough detail to be implemented without further questions.
title: ''
labels: ready-for-agent
assignees: ''
---

<!--
  Apply the `ready-for-agent` label only once this is true: someone (or an
  agent) could open this and start work without asking a question whose answer
  would change the design.
-->

## What to build

<!--
  Prose, not bullets. Cover, roughly in this order:

  - The change, and what makes it necessary. If the current behaviour is broken
    or impossible, say so concretely — name the column, constant or guard that
    blocks it.
  - The decision, where there was one, and the alternatives you rejected with
    the reason each lost. A decision worth explaining at length here is usually
    an ADR in docs/adr/ instead; link it and keep this short.
  - Anything that deliberately does NOT change, and why — e.g. a rule that
    already holds in the general case. Call these out so they get test coverage
    instead of being silently assumed.
  - Schema, API shape, and UI in enough detail to be unambiguous: column types
    and nullability, request/response keys, the exact user-visible strings
    (Danish in the UI).
  - Names. Cite real files and symbols the way the rest of the repo does —
    `BookingDialog`, `App\Calendar\ConflictChecker`, `frontend/src/types/calendar.ts`.
    Point at existing code that already does most of the job.
  - Relevant ADRs, by number: "Per ADR 0002, …".
  - A closing line on what is out of scope, so nobody widens the change.
-->

## Acceptance criteria

<!--
  Checkboxes, each independently observable — something you could demonstrate
  or point a test at, not an implementation step. Cover the negative and
  edge cases too (rejected input, the empty case, the unbounded case).
  Keep the last one about tests.
-->

- [ ] 
- [ ] Covered by frontend (Vitest) and backend (Pest) tests.

## Blocked by

<!-- Issue numbers (#4), one per line — or "None (can start immediately)". -->

None (can start immediately)
