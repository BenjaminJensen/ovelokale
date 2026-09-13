---
name: refine-ticket
description: Refine one rough ticket into a ready-for-agent spec — explore the code it touches, settle the open decisions with the user, and rewrite the issue in place.
disable-model-invocation: true
---

# Refine Ticket

Take **one** existing ticket — usually a sentence someone filed in passing — and turn it into a spec that can be implemented without asking a question whose answer would change the design.

This sits between filing and building: idea → **refine-ticket** → [implement](../implement/SKILL.md). It is not [to-tickets](../to-tickets/SKILL.md): that one breaks a *plan* into *many* new tickets, this one deepens a single ticket that already exists.

## Rules

- **Refine in place.** Rewrite the existing issue's body. Never open a replacement issue — the number is already referenced elsewhere.
- **Facts are your job, decisions are the user's.** Never ask the user something the codebase can tell you. Never decide something on their behalf when two answers lead to materially different work.
- **Don't apply the triage label until the user approves the draft.** The label (`ready-for-agent` by default) is the claim that this is implementable as written; it's theirs to make, not yours.
- **A refined ticket must still fit one context window.** If refinement reveals the work is bigger than that, stop and run [to-tickets](../to-tickets/SKILL.md) on it instead, keeping the original as the parent.

## Process

### 1. Load the ticket

`gh issue view <number> --json title,body,labels,comments`. Read the comments, not just the body — earlier context often lives there. If the user didn't name a ticket, list the unrefined ones (`gh issue list --search "-label:ready-for-agent"`) and ask which.

### 2. Read the domain model

- `CONTEXT.md` for the vocabulary the ticket must be written in.
- `docs/adr/` for decisions already made in this area. A refined ticket cites them by number ("Per ADR 0002, …") instead of re-deciding them.
- `.github/ISSUE_TEMPLATE/` for the house shape, if the repo has one.
- One or two already-refined tickets (`gh issue list --label ready-for-agent --state all`) as worked examples of the expected depth.

### 3. Find the real blocker in the code

The highest-value step, and the one most easily skipped. Explore until you can name **the exact thing that makes the current behaviour impossible** — the column, the constant, the guard, the missing field.

"Users can't book without a band" is a wish. "`bookings.band_id` is `NOT NULL`, and `canSubmit` in `BookingDialog` requires `bandId > 0`, so a user in no band can't book at all" is a spec. Same insight, but the second one can be started.

While you're in there, look for the three things the filer almost never knows:

- **Work the ticket implies but doesn't mention.** Ownership, auditing, migration of existing rows, a column that has to exist before the feature can. If the ticket needs something the schema can't currently express, that's part of the ticket.
- **Rules that already hold and must NOT change.** If existing logic already covers the new case, say so explicitly — otherwise someone "fixes" it. It still needs a test, so it becomes an acceptance criterion rather than an instruction.
- **Code that already does most of the job.** An existing fallback, helper, or nullable field the implementer should build on instead of inventing a parallel one.

### 4. Put the open decisions to the user

Only decisions that change the work. Nullable column versus a join table is a decision; a variable name is not.

For each: the options, what each one costs, and your recommendation. Then wait. If the decisions are interdependent or numerous, run them as [grilling](../grilling/SKILL.md) rounds, working the frontier. If they're few and independent, one round of multiple-choice questions is enough.

### 5. Draft the body

Follow `.github/ISSUE_TEMPLATE/ready-for-agent.md` when the repo has one; otherwise the shape below. What separates a refined body from a rough one:

- The **decision** and the **alternatives you rejected, with the reason each lost**. Without this, the first implementer to hit friction quietly re-decides it.
- **Names.** Real files, columns, classes, symbols — and the exact user-visible strings, in the project's UI language. This ticket is being refined against the code as it is today and implemented shortly after, so names are an asset here; that's the opposite of `to-tickets`, which writes tickets for code that doesn't exist yet and rightly avoids paths that would go stale.
- **What deliberately doesn't change**, so it gets a test instead of an edit.
- **What's out of scope**, so the change doesn't widen.
- Acceptance criteria that are **observable** — demonstrable behaviour, including the negative and empty cases, not implementation steps.

<issue-template>

## What to build

Prose. The change and what makes it necessary (naming the concrete blocker); the decision and the rejected alternatives with their reasons; anything that deliberately does not change; schema, API and UI in enough detail to be unambiguous; relevant ADRs by number; a closing line on what is out of scope.

## Acceptance criteria

- [ ] Independently observable criteria, covering the negative and edge cases.
- [ ] A final one about test coverage, naming the project's test tooling.

## Blocked by

Issue numbers, or "None (can start immediately)".

</issue-template>

### 6. Publish

Show the draft first. On approval, `gh issue edit <number> --body-file <path>` and add the triage label. Sharpen the title only if it's actively misleading — the old one is what people search for.

### 7. Offer to record the decision properly

If step 4 settled something contested, the ticket body is its temporary home, not its permanent one: the issue gets closed and the reasoning goes with it. Offer to run [domain-modeling](../domain-modeling/SKILL.md) to write the ADR, and to add any new term to `CONTEXT.md`. Then the ticket can cite it by number and shed the inline justification.
