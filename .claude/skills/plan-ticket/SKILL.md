---
name: plan-ticket
description: Turn one refined ticket into a local implementation plan a fresh subagent can execute — the file-by-file sequence, the test mapping, and the exact checks — without re-exploring the codebase.
disable-model-invocation: false
---

# Plan Ticket

Take one **refined** ticket and write the implementation plan for it to `.scratch/`, in enough detail that an agent starting with an empty context window can build it without re-deriving anything.

This is the step between a refined ticket and [implement](../implement/SKILL.md). Its whole reason to exist is that exploration is expensive and doesn't survive a context boundary: someone already read the code to refine the ticket, and without a plan the implementer reads it all again. The plan is where that knowledge is parked.

**Stop when the plan is written.** Do not start building, and do not dispatch anyone — reviewing a plan costs one read, while discovering a wrong approach after the fact costs a subagent run and a branch to unpick.

## Rules

- **Only plan a refined ticket.** If it has no acceptance criteria, or the decisions in it are still open, stop and run [refine-ticket](../refine-ticket/SKILL.md) first. Planning an unrefined ticket just buries the open questions one layer deeper.
- **The plan is *how*, never *what* or *why*.** Those live in the ticket; link it, don't restate it. If you catch yourself justifying a decision in the plan, it belongs in the ticket or in an ADR via [domain-modeling](../domain-modeling/SKILL.md).
- **The ticket wins.** A plan is a disposable working note, written against the code as it looked today. Where the two disagree, the ticket is the spec.
- **Write nothing outside `.scratch/`.** No source edits, no branch, no commits. `.scratch/` is gitignored.
- **Write for a stranger.** Assume the reader has never seen this repo and cannot ask you a follow-up question. Paths from the repo root, commands copy-pasteable, no "as discussed".

## Process

### 1. Load the ticket and check it's ready

`gh issue view <number> --json title,body,labels,comments`, or read the local file for a [to-tickets](../to-tickets/SKILL.md) fallback ticket.

Confirm everything in its "Blocked by" line is actually done. A ticket with open blockers can't be planned honestly — the code it would build on doesn't exist yet.

### 2. Read the ground rules

`CONTEXT.md` for vocabulary, `docs/adr/` for decisions that constrain the approach, and whichever development guides the ticket touches (`backend/development.md`, `frontend/development.md`) — the exact container commands, tooling and layout conventions live there, and the plan is going to quote them.

### 3. Explore for real

Open every file that will change. Read them; do not infer them from their names. This is the expensive step, and preserving its output is the entire point of the artifact — a plan built on guesses is worse than no plan, because it will be trusted.

You're looking for: what's already there to extend rather than duplicate, what breaks when each piece changes, and anything the ticket assumed that turns out not to hold. **If exploration contradicts the ticket, stop and say so** — that's a refinement bug, and it's cheaper to fix in the ticket than in the plan.

### 4. Sequence the work

Order the steps so the tree stays buildable at each boundary, so a failure is attributable to the step that caused it. Typically schema and data first, then the domain layer, then the API, then types, then UI — but derive it from the actual dependencies, not the template.

Where a change would break every call site at once, sequence it expand–contract rather than as one edit (`to-tickets` describes this in full).

### 5. Map every acceptance criterion to a test

Name the test file and the case for each criterion in the ticket, before any of them exist. Any criterion you can't map is a criterion that isn't observable — that's a spec gap, so send it back to [refine-ticket](../refine-ticket/SKILL.md) rather than papering over it in the plan.

### 6. Write the plan

To `.scratch/issue-<N>/plan.md` for a GitHub issue, or `.scratch/<feature-slug>/plans/<NN>-<slug>.md` alongside a local ticket file.

<plan-template>

# Plan: #<N> <ticket title>

**Ticket:** <url or path> — the spec. Read it first; this file is only the how.
**Branch:** `<prefix>/<slug>` (per the repo's existing convention)
**Blockers:** confirmed done, or none

## What you're walking into

The shape of the existing code being extended, and what a stranger to this repo would otherwise waste an hour rediscovering: the helper that already covers half of it, the fallback that already handles the new case, the thing that looks reusable and isn't.

## Steps

Ordered, each one leaving the tree buildable.

### 1. <short imperative title>
- **Files:** paths from the repo root
- **Change:** what to do, concretely enough to act on without a second opinion
- **Verify:** the command or observation proving this step landed

## Tests

| Acceptance criterion | Test file | Case |
| --- | --- | --- |

## Checks

The exact commands for every side touched, quoted from the development guides.

## Unknowns

Anything exploration could not settle, and what to do on hitting it — including who to ask. Say "none" if there are none; an empty section reads like an oversight.

</plan-template>

### 7. Hand it back

Report the path, the step count, and anything exploration turned up that should go back into the ticket or an ADR. Then stop — [implement](../implement/SKILL.md) picks it up from here.
