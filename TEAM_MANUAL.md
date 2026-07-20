# Team Manual — Loan & Payment Management Feature

This is your personal step-by-step guide. Find your name below and follow only that section — you don't need to read the others in detail, though skimming them helps you understand what you're merging with later.

**Everyone does Part 0 first.** Then jump to your role.

---

## Part 0 — Everyone: one-time setup

```bash
git clone https://github.com/<lead-username>/student-loan-management-system.git
cd student-loan-management-system
docker compose up -d
```

Check your containers are running:
```bash
docker compose ps
```
You should see the PHP app, MySQL, and phpMyAdmin containers all "Up."

Open the app in your browser to confirm it works **before** you change anything:
```
http://localhost/src/crud/index.html
```
(Adjust the port if your `docker-compose.yml` maps something other than 80 — check that file if this doesn't load.)

If you see the Student Management table, you're ready. If not, stop here and message the group chat — don't start your task on a broken environment.

---

## Johnpaul — Database Administrator

**Branch:**
```bash
git checkout -b db/loans-payments-schema
```

**What you're adding:** `loan_payment_schema.sql` — creates the `loans` and `payments` tables with foreign keys back to `students` and `loans`.

**Steps:**
1. Save the `loan_payment_schema.sql` file (already written — get it from the lead) into `mysql-init/` in your local project.
2. Apply it to your **already-running** database (mysql-init only auto-runs on a brand-new container):
   ```bash
   docker compose exec -T mysql-db mysql -u root -prootpassword school_db < mysql-init/loan_payment_schema.sql
   ```
   (If that errors with "service not found," check `docker-compose.yml` for your MySQL service's actual name and swap it in.)
3. Verify it worked — open phpMyAdmin (check `docker-compose.yml` for the port), select `school_db`, confirm you now see `loans` and `payments` tables alongside `students`.
4. Commit and push:
   ```bash
   git add mysql-init/loan_payment_schema.sql
   git commit -m "feat(db): add loans and payments tables with foreign keys"
   git push -u origin db/loans-payments-schema
   ```
5. Open a PR on GitHub → base `main` → request review from the Lead.

**Your job also includes:** double-checking the foreign keys actually work. In phpMyAdmin, try deleting a test student that has a loan — the loan should disappear automatically (that's `ON DELETE CASCADE` doing its job). This is worth a screenshot for the QA/documentation lead.

---

## Aaron — Backend Developer

**Branch:**
```bash
git checkout -b feature/backend-loans-payments
```

**Wait for:** Johnpaul's schema PR to be merged first — your API code needs the `loans`/`payments` tables to exist to test against. Pull `main` again after it merges:
```bash
git checkout main
git pull origin main
git checkout feature/backend-loans-payments
git merge main
```

**What you're adding:**
- `src/crud/loanapi.php` — replace the empty stub with the working version (already written — get it from the Lead)
- `src/crud/paymentapi.php` — new file, same source

**Steps:**
1. Copy the two files into `src/crud/`.
2. Test each endpoint directly before touching the frontend. With your containers running:
   - Visit `http://localhost/src/crud/loanapi.php?student_id=1` in your browser — should return `[]` or a JSON list, not a PHP error.
   - Use a tool like Postman, or your browser's dev console with `fetch()`, to test a POST request creates a loan.
3. Commit and push:
   ```bash
   git add src/crud/loanapi.php src/crud/paymentapi.php
   git commit -m "feat(backend): implement loan and payment CRUD endpoints"
   git push -u origin feature/backend-loans-payments
   ```
4. Open a PR → base `main` → request review.

**Understand before you commit:** read through both files once. You should be able to explain, in your own words, what a "prepared statement" is and why `payments_api.php`'s GET endpoint calculates `total_paid` by looping through payments. If your instructor asks you about your own PR, you need to answer for it.

---

## Joshua — Frontend / UI Developer

**Branch:**
```bash
git checkout -b feature/frontend-loan-payment-ui
```

**Wait for:** Aaron's backend PR to be merged — your UI calls his endpoints. Pull `main` again after it merges, same commands as above.

**What you're adding:** `src/crud/index.html` — replaces the existing file, adds the Loan Workspace and Payment Workspace sections (already written — get it from the Lead).

**Steps:**
1. Replace `src/crud/index.html` with the new version.
2. Test the full flow in your browser:
   - Click "Loans" on a student row → does the Loan Workspace open?
   - Add a loan → does it appear in the list?
   - Click "Payments" on a loan → does the Payment Workspace open?
   - Add a payment → do Total Paid and Remaining Balance update?
3. Commit and push:
   ```bash
   git add src/crud/index.html
   git commit -m "feat(frontend): add loan and payment workspace UI"
   git push -u origin feature/frontend-loan-payment-ui
   ```
4. Open a PR → base `main` → request review.

**Understand before you commit:** be able to explain what "event delegation" means and point to where it's used (the click listener on the loan/payment table bodies). This is the one JS pattern in the file that isn't obvious at a glance.

---

## Shane — QA & Documentation Lead

**Your work happens throughout, not just at the end.** Don't wait for everyone else to finish — test each PR as it's opened.

**When Johnpaul's PR is open:** pull his branch locally, run the migration, confirm the tables and foreign keys look right in phpMyAdmin.

**When Aaron's PR is open:** pull his branch, test each endpoint manually (see his section above for how). Leave a PR comment noting anything broken.

**When Joshua's PR is open:** pull his branch, click through the entire flow from the checklist below.

**Full testing checklist (run this once everything is merged to `main`):**
- [ ] Add a student → "Loans" button appears for them
- [ ] Loan Workspace shows only that student's loans
- [ ] Can add a loan with each Loan Type and each Status
- [ ] Payment Workspace shows only that loan's payments
- [ ] Total Paid and Remaining Balance are correct after adding a payment
- [ ] Deleting a loan removes its payments too
- [ ] Deleting a student removes their loans (and those loans' payments)
- [ ] Submitting an empty form shows a sensible message, not a blank crash

**Bug tracking:** for anything broken, open a GitHub Issue describing it (what you did, what you expected, what happened instead), assign it to whoever owns that file, and track it on the project board.

**Documentation:** write a short `USER_GUIDE.md` — a few screenshots or a plain description of how to use the Loans and Payments features, for someone who's never seen the app. This is graded separately from functionality, so don't skip it.

**Branch for your own commits (docs, minor fixes):**
```bash
git checkout -b qa/testing-and-docs
```

---

## Lead (you)

- Keep the GitHub Projects board updated as PRs open/merge
- Review and merge in this order: Johnpaul → Aaron → Joshua → Shane's docs
- After each merge, ping the group chat so the next person knows to pull `main` before continuing
- Do a final full test pass yourself before submission, using Shane's checklist

---

## If something breaks and you don't know why

Don't silently work around it or delete things to make an error disappear. Post in the group chat: what you were doing, the exact error message (screenshot is fine), and what file you were editing. Broken states are normal mid-project — silently "fixing" them by guessing is how repos end up in worse shape.