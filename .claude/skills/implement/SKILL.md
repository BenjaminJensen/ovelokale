---
name: implement
description: Implement a single ticket produced by the to-tickets skill, on a fresh branch, running every check before committing.
disable-model-invocation: true
---

# Implement

Implement exactly **one** ticket end to end: branch, code, checks, commit, PR.

## Rules

- **Never commit to `main`/`master`.** Always work on a fresh branch cut from an up-to-date `main`.
- **Never implement without a ticket.** The ticket is the spec; if the user asks you to "just implement X" without pointing at a ticket produced by [to-tickets](../to-tickets/SKILL.md) (a GitHub issue or a `.scratch/<feature-slug>/issues/*.md` file), stop and tell them to run that skill first.
- **All checks must pass before every commit**, not just before the PR. A red check blocks the commit, full stop — fix it, don't work around it (no baseline ignores, no skipped tests, no `--no-verify`).

## Process

### 1. Load the ticket

Given an issue number/URL, fetch it with `gh issue view <number> --json title,body,labels,state`. Given a local file path, read it directly.

Confirm every ticket in its "Blocked by" line is actually done (issue closed, or local file's checkboxes complete) — a ticket with open blockers is not on the frontier yet. If a blocker isn't done, stop and tell the user rather than guessing at the missing behaviour.

### 2. Branch

From an up-to-date `main`, create a branch. Follow the repo's existing naming convention (`feature/<slug>`, `frontend/<slug>`, `backend/<slug>` — see `git log`): pick the prefix matching the layer(s) the ticket touches, `feature/` when it spans both.

### 3. Pick the development guide(s)

A tracer-bullet ticket cuts through schema/API/UI, so it may need both:

- Backend work (PHP API, schema, mail, DB) → follow [backend/development.md](../../../backend/development.md)
- Frontend work (Vue SPA) → follow [frontend/development.md](../../../frontend/development.md)

Read whichever apply before writing code — they hold the exact container commands, test/lint/style tooling, and layout conventions for that side.

### 4. Implement

Build exactly what the ticket's "What to build" / acceptance criteria describe — no more. Use the project's domain glossary vocabulary (`CONTEXT.md`) and respect existing ADRs (`docs/adr/`).

If implementation surfaces a term that conflicts with the glossary, an undefined term, or a hard-to-reverse decision with a real trade-off behind it, invoke the [domain-modeling](../domain-modeling/SKILL.md) skill to update `CONTEXT.md` or write an ADR before moving on — don't let that knowledge live only in code.

### 5. Run every check, then commit

Before each commit, run the full check suite for every side you touched and confirm all are green:

- Backend: `composer test`, `composer stan`, `composer cs` (all inside `docker compose exec web`)
- Frontend: `npm test`, `npm run typecheck`, `npm run lint`, `npm run format:check` (all inside `docker compose exec frontend`)

Only commit once everything above is green. Reference the ticket in the commit message (e.g. `Closes #<N>` only in the final commit of the branch, once every acceptance criterion is met).

### 6. Open a PR

Push the branch and open a PR with `gh pr create`, body referencing the ticket (`Closes #<N>` for a GitHub issue) and checking off the acceptance criteria it satisfies. Do not merge it yourself and do not push to `main` directly — the PR is the review point.

For a local-file ticket (no GitHub issue), there's nothing to close automatically — say so in the PR body and let the user tick the file's checkboxes once merged.
