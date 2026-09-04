# ADR-0002: Schema Integrity Corrections

- **Status:** Accepted
- **Date:** 2026-08-31
- **Branch:** `WBPMS-dev`
- **Amends:** ADR-0001
- **Implementation dictionary:**
  [`Canonical-Database-Schema-v1.1.md`](../capstone_files/Canonical-Database-Schema-v1.1.md)

## Context

A second schema-quality review found that ADR-0001 established a coherent
domain model but did not yet provide enough relational detail for safe
migrations. Several requirements could still be violated by duplicate rows,
ambiguous timestamps, duplicated lifecycle state, or missing source lineage.

The original capstone, HR-answer, and reviewer PDFs remain immutable source
evidence. This ADR and the linked addendum are the capstone corrections that
translate that evidence into the implementation schema.

## Decisions

1. The capstone addendum is the authoritative column/type/nullability/key
   dictionary. `design.md` remains the architectural view.
2. `payroll_run.status` is the only payroll approval state. The stored values
   are `Draft`, `Computed`, `PendingOwnerApproval`, `Approved`, and `Returned`.
   Employee payroll rows do not duplicate approval status or reviewer fields.
3. `payroll` retains `payroll_period_id` only to enforce company-wide
   `UNIQUE(payroll_period_id, employee_id)`. A composite foreign key guarantees
   that this period equals the parent run's period.
4. One MVP attendance row exists per employee and business date. Every raw
   punch used as evidence is linked through `attendance_punch`; incomplete days
   allow either `time_in` or `time_out` to be NULL.
5. Each import batch stores a validated `source_year` and `source_month`.
   Workbook headers, weekdays, month, and selected year must agree before any
   row is committed.
6. Payroll details snapshot the selected salary and calculation inputs.
   Earning and deduction lines store quantity, unit rate, multiplier, source
   references, and JSON calculation evidence so approved results remain
   explainable after master-data changes.
7. Contribution records are unique per payroll/program, reference the exact
   contribution-policy version, and reference the unique employee-share
   deduction row.
8. Effective-dated branch, device coverage, enrollment, schedule, salary, and
   policy records use half-open periods `[effective_from, effective_to)`. MySQL
   migrations enforce valid bounds and one open record; services additionally
   lock the parent key with `SELECT ... FOR UPDATE` and reject any overlap.
9. MySQL-persisted sessions are part of the schema contract. Password reset
   challenges store only hashed OTPs. `users.account_email` belongs to the
   account, allowing a non-employee Owner to recover access.
10. Audit events may have no authenticated `user_id`, allowing failed-login
    events to retain attempted identifier, IP, user agent, event type, and
    request ID.
11. The versioned contribution-policy tables include the effective date and
    caps omitted from the earlier `design.md` shorthand.
12. An approved cash-advance request creates one obligation linked by a unique
    `request_id`; repayments are separate child rows linked to payroll and the
    exact deduction line.
13. Leave balances use annual entitlements plus an append-only ledger. Request
    lifecycle distinguishes decision status from archival metadata and uses
    type-specific detail tables.
14. One weekly `disbursement_batch` belongs to one `payroll_period`, not one
    branch run. It may be created only after every included branch run is
    approved.
15. `payslip.payroll_id` is unique. Bank accounts are effective-dated and the
    service enforces one current active BDO account per employee.
16. The recurring payroll calendar is protected by checks/service validation:
    Sunday start, Friday end, six calendar dates, and pay date on that Friday.

## Consequences

- Migrations must follow the capstone addendum rather than reverse-engineering
  types or constraints from the historical diagrams.
- The schema contains no `benefit` table or `total_benefits` aggregate.
- Approved payroll rows and their calculation lines are immutable. Corrections
  require a returned run before approval; post-approval reversal remains a
  later explicitly designed workflow.
- Temporal non-overlap is a database-plus-service invariant because a normal
  MySQL unique constraint cannot express arbitrary date-range exclusion.
- The five-day MVP may omit deferred user interfaces, but migrations include
  the integrity structures needed by the complete documented system.

## Verification requirements

Migration and integration tests must prove:

- duplicate employee/date attendance is rejected;
- a payroll employee cannot appear in two branch runs for one period;
- mismatched payroll/run periods are rejected;
- duplicate contribution programs and payslips are rejected;
- invalid payroll dates and invalid status/timestamp combinations are rejected;
- temporal overlaps are rejected under concurrent writes;
- failed-login audit rows work with `user_id = NULL`;
- an approved payroll cannot be edited or approved twice;
- one period creates at most one disbursement batch.
