# Two-Developer P0 Task Distribution

**Applies to:** `WBPMS-dev`, the five-day MVP, and the active
[implementation plan](tasks.md).

**Naming:** replace “Developer A” and “Developer B” with the team members'
names in your issue board. `tasks.md` remains the complete backlog; this
document selects and assigns only the two-developer P0 slice. A task is not
complete until its acceptance evidence and tests pass.

## Ownership and code boundaries

| Owner | Owns | Must not independently change |
|---|---|---|
| Developer A — foundation and payroll | Composer/bootstrap, config, PDO/Phinx, migrations, seed data, authentication/RBAC, audit/transaction helpers, salary/contributions, payroll, approval, and merge integration | Parser contract/fixtures, attendance calculations, and attendance UI behavior |
| Developer B — attendance and user flow | XLS fixtures, parser DTOs/errors, attendance import/matching/generation, employee/schedule/device setup, PHP views, employee payslip access, and E2E flow | Migrations after they are merged, payroll formulas/state machine, and shared infrastructure APIs |

Developer A is the only person who creates or merges Phinx migrations. Developer
B proposes schema changes in review before coding against them. Developer B is
the only person who changes `LdeXlsDailyLogParser` behavior or parser fixtures.

## P0 assignments by task ID

### Developer A — foundation and payroll

| Priority | `tasks.md` IDs | P0 boundary and acceptance handoff |
|---|---|---|
| A1 | 1.1, 1.4 | PHP/Composer scaffold, front controller, environment loading, PDO factory, repository transaction helper, health endpoint, PHPUnit/PHPStan commands. Handoff: Developer B can run parser tests and a test database locally. |
| A2 | 1.2, 1.3, 1.5 | Phinx schema/seed subset required for users, employees, schedules, imports, punches, attendance, salary, contribution fixture, payroll, payslip, and audit. Handoff: reproducible migrate/seed and reviewed schema contract. |
| A3 | 2.1, 2.3, 2.4 | Seeded login, MySQL-backed session, role guard, CSRF, login/RBAC tests. Excludes 2.2 password recovery. Handoff: HR, Owner, and Employee test accounts. |
| A4 | P0 subset of 8.1; 9.1, 9.2, 9.5 | Effective salary lookup, approved demo contribution policy, EEMR, last-Friday rule, and golden contribution tests. Excludes salary/contribution maintenance screens and history administration. Handoff: a versioned `PayrollInput`/calculation contract. |
| A5 | 10.1–10.3, P0 subset of 10.4, 10.5–10.8 | One period/branch payroll run, immutable itemized calculation, submit/return/approve state flow, double-approval guard, and basic payslip data. Excludes 13th-month calculation and performance certification. Handoff: approved payroll/payslip read model for Developer B's views. |
| A6 | Upload-boundary subset of 6.2; P0 subset of 14.2, 14.5, 15.1 | Own the uploaded-file/temp-storage/SHA-256/transaction boundary, audit the P0 actions, wire protected routes, and run the RBAC regression with Developer B. The boundary passes a validated temporary path to B's parser. |

### Developer B — attendance and user flow

| Priority | `tasks.md` IDs | P0 boundary and acceptance handoff |
|---|---|---|
| B1 | 6.3; parser-validation/test subset of 6.11 | Synthetic XLS fixtures; workbook shape/date/time/formula/resource validation; pure `LdeXlsDailyLogParser`; deterministic DTO/error tests. The upload file/signature/temp-storage boundary remains A6. Handoff: versioned `ParsedAttendanceFile` and error contract. |
| B2 | P0 subset of 4.1–4.4; P0 subset of 5.1–5.3 | HR create/update for the demo employee, initial branch assignment, one effective schedule, and basic forms/tests. Excludes search, archive, transfer history, calendar, and holiday administration. Handoff: employee/schedule/enrollment data ready for import. |
| B3 | P0 subset of 6.1, 6.4–6.8, 6.10, remaining 6.11 | Device/enrollment setup, transactional import, matching, checksum/idempotency, immutable punches, attendance generation, hours/exception calculation, HR upload/review view, and integration tests. Excludes manual adjustment UI (6.9). Handoff: generated attendance accepted by payroll. |
| B4 | 13.4, 13.5, 14.1, 14.4 | Employee-scoped payslip view, cross-employee denial test, date/time formatting, and browser smoke tests. Handoff: complete Employee demo step. |
| B5 | P0 subset of 15.2–15.4 | Sanitized demo XLS and expected results, three-role walkthrough of the committed path, and shared defect triage. It does not require walking every user story. |

## Day-by-day execution order

| Day | Developer A | Developer B | Required shared handoff |
|---|---|---|---|
| Day 1 | A1; begin A2/A3 | B1; define employee/schedule/import DTOs | End of day: merge scaffold; freeze parser DTO/error and repository interfaces. |
| Day 2 | Finish A2/A3; start A4 | B2; start B3 after migrations are available | HR can create a demo employee/schedule/enrollment and upload a valid fixture. |
| Day 3 | Finish A4; start A5 | Finish B3 and attendance tests | Attendance output and payroll input contract are exercised together. |
| Day 4 | Finish A5/A6 | B4; finish P0 B3 defects | Owner approval and employee payslip journey pass; freeze new P0 scope at midday. |
| Day 5 | Joint hardening | Joint hardening | Run PHPUnit, PHPStan, migration/reseed, browser smoke, and the timed three-role demo. |

## Required integration contracts

| Contract | Producer | Consumer | Locked by |
|---|---|---|---|
| `ParsedAttendanceFile`, `ParsedPunch`, parser error codes | Developer B | Developer A/import persistence | End of Day 1 |
| Migration schema, repositories, transaction helper, seeded roles | Developer A | Developer B | Start of Day 2 |
| `AttendanceImportSummary` and generated attendance data | Developer B | Developer A/payroll | End of Day 2 |
| Payroll input/calculation and approved payslip read model | Developer A | Developer B/views | End of Day 3 |
| Three-role demo fixture and expected results | Both | Both | Start of Day 4 |

## Deferred until P0 is green

- 2.2; all of 3 and 7; full 4/5 administration; 6.9; salary/contribution
  maintenance screens (8.2–8.3, 9.3–9.4); 13th-month work; all 11 and 12;
  most of 13; performance certification (10.9, 11.5, 14.3); and the full
  every-user-story pass in 15.3.

## Daily rules

1. Work in short branches: `feature/foundation-auth`, `feature/attendance-xls`,
   `feature/payroll-approval`, or `feature/payslip-portal`.
2. Open a pull request before handing off a contract. The other developer must
   review it before merge.
3. Do not modify the other developer's owned domain to unblock yourself. Raise
   the blocker in the 10-minute dependency check, agree a small interface
   change, then record it in the pull request.
4. Merge and run the focused integration path every afternoon. If it fails,
   both developers fix the vertical slice before beginning new work.
5. Never mark the whole parent module complete when only its listed P0 subset
   is done; record the completed subtask IDs and test evidence instead.
