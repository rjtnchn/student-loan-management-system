# Loan & Payment Feature — Team Plan

## Role assignments (matches the exam's Role Assignment Table exactly)

| Role | Name | Owns |
|---|---|---|
| Project Manager / Team Lead | You | Task board, timeline, reviews + merges all PRs |
| Backend Developer | Aaron | `loans_api.php`, `payments_api.php` |
| Database Administrator | Johnpaul | `loans_payments_schema.sql` (the loans/payments tables + FKs) |
| Frontend / UI Developer | Joshua | `index.html` — Loan Workspace + Payment Workspace UI |
| QA & Documentation Lead | Shane | Tests every flow, tracks bugs as GitHub Issues, writes the user guide |

## Branches

```
db/loans-payments-schema        → Johnpaul
feature/backend-loans-payments  → Aaron
feature/frontend-loan-payment-ui → Joshua
qa/testing-and-docs             → Shane (opened last, but Shane should test continuously — see below)
```

## Merge order (why it's in this order)

1. **Johnpaul's schema first** — everyone needs the `loans`/`payments` tables to exist locally before their code can be tested against them.
2. **Aaron's API second** — the frontend needs working endpoints to call.
3. **Joshua's UI third** — connects to Aaron's already-merged endpoints.
4. **Shane's docs/bug-fix PRs throughout, not just at the end** — see below, this is graded specifically.

## ⚠️ About the "Collaboration & Teamwork" rubric line

Re-read this part of the exam sheet carefully:

> *"the team must use collaboration tools throughout development — not just divide the work and merge it at the end"*

This is graded (up to 5 pts) separately from whether the app works. A repo with 4 giant commits, one per person, all made the same night, will score **"Need Improvements"** or **"Poor"** on this line even if the app works perfectly — the rubric explicitly wants evidence of *ongoing* collaboration, not a one-time merge.

**What actually satisfies this:**

1. **Use GitHub Issues + a Projects board (Kanban).** Create one Issue per task (e.g. "Design loans table," "Build loan workspace UI," "Test payment deletion"), assign it to the right person, move it across a To Do / In Progress / Done board. This is your "task board," and the exam explicitly says the faculty will check for it.
2. **Multiple commits over multiple days**, not one dump the night before. Even small commits ("wip: loan form markup," "fix: loan_type dropdown values") show real incremental work — this is normal, expected, and looks *better* than one clean commit, not worse.
3. **Actual PR review comments.** Even a one-line "looks good" or "should this handle a missing student_id?" from a teammate is what "code review" means here — an approved PR with zero comments still counts, but a couple of real comments strengthens it.
4. **A communication tool.** A Messenger/Discord group chat where you coordinate counts — screenshot it if your instructor wants proof, since the sheet mentions it as part of what's checked.

## Suggested timeline (so it's spread out, not last-minute)

| When | Who | What |
|---|---|---|
| Day 1 | Johnpaul | Schema PR — open, get reviewed, merge |
| Day 1–2 | Aaron | API PR — several commits (create loans_api.php, then payments_api.php, then the totals calculation) |
| Day 2–3 | Joshua | UI PR — several commits (loan workspace first, then payment workspace) |
| Throughout | Shane | Opens Issues for bugs found, small PRs fixing them, drafts the user guide in parallel |
| Final day | You | Final review pass, merge any remaining PRs, confirm the whole flow works end-to-end |

## Setup for everyone (Docker)

```bash
git clone <your-repo-url>
cd <repo-folder>
docker compose up -d
```
Then run `loans_payments_schema.sql` against the `db` container (via a MySQL client, phpMyAdmin if your compose setup includes it, or `docker compose exec db mysql -u root -prootpassword school_db < loans_payments_schema.sql`).

## Testing checklist for Shane (QA)

- [ ] Can add a student, then immediately see a "Loans" button for them
- [ ] Loan Workspace shows only that student's loans, not everyone's
- [ ] Adding a loan with each Loan Type and each Status works
- [ ] Payment Workspace shows only that loan's payments, not everyone's
- [ ] Total Paid and Remaining Balance update correctly after adding a payment
- [ ] Deleting a loan also removes its payments (tests the FK `ON DELETE CASCADE`)
- [ ] Deleting a student also removes their loans (and those loans' payments)
- [ ] Submitting a form with an empty required field shows a sensible error, not a blank crash