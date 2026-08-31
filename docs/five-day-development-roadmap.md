# Five-Day Collaborative Development Roadmap

## 1. Purpose

This roadmap defines a five-day implementation phase for the Web-Based
Payroll Management System (WBPMS). The repository currently contains an
approved technology direction and detailed specifications, but no executable
application. Five days is therefore enough for a tested **MVP vertical
slice**, not the complete production system described in `requirements.md`.

The phase should prove the most important business flow end to end:

> HR Head signs in → maintains an employee and schedule → imports attendance
> → computes payroll → submits it → Business Owner approves or returns it →
> Employee views the resulting payslip.

The complete scope remains in `tasks.md`. Work not included in the committed
MVP is retained as follow-up work rather than silently removed.

## 2. Recommended team

This plan is optimized for **two developers plus an available business/HR
reviewer**. It commits to one tested vertical slice rather than parallel,
independent modules.

| Workstream | Suggested owner | Primary responsibility |
|---|---|---|
| A — Foundation and payroll | Developer A / integration lead | PHP scaffold, PDO/Phinx, seed data, authentication/RBAC, transaction/audit infrastructure, salary/contribution fixtures, payroll calculation, approval state, and daily integration |
| B — Attendance and user flow | Developer B | Employee/schedule/device setup, strict `.xls` parser, attendance import/calculation, role-specific PHP views, fixtures, and end-to-end verification |
| Product/domain review | Business Owner and HR Head | Same-day decisions on schema, payroll rules, fixtures, and acceptance evidence |

### Working agreement for two developers

- Each developer owns one workstream and reviews the other developer's pull
  requests. No change merges without the other developer's review and a
  passing focused test run.
- Developer A runs the daily integration gate; Developer B can block a merge
  that breaks attendance evidence, fixtures, or the HR workflow.
- Merge at least once per day. Avoid long-lived branches and work that cannot
  be demonstrated through the shared vertical slice.
- If either developer is unavailable, freeze feature work and limit activity
  to documented defect fixes, tests, and setup work.

The active task-by-task ownership, dependencies, and handoffs are maintained
in the [two-developer task distribution](two-developer-task-distribution.md).

## 3. Scope for this phase

### P0 — committed MVP

- Frameworkless PHP 8.5/Composer/PDO/Phinx/MySQL project that can be installed,
  migrated, seeded, statically analyzed, tested, and started from a clean checkout.
- Seeded login/RBAC for Business Owner, HR Head, and Employee; password
  recovery and user-administration screens remain deferred.
- HR create/update for the demo employee, one effective schedule, branch
  assignment, salary, and biometric enrollment/device mapping using synthetic
  data.
- One strict legacy `.xls` import path using `LDE_XLS_DAILY_LOG_V1`:
  validation, matching, duplicate protection, unmatched retention, attendance
  generation, and late/undertime/overtime/incomplete flags.
- Payroll calculation for one Friday-through-Thursday period using the
  documented demo contribution fixture and an itemized, auditable result.
- `Draft`/`Computed` → `PendingOwnerApproval` → `Approved` or `Returned`, with
  return reason and double-approval protection.
- One employee-scoped payslip view and the minimal audit records needed to
  prove sign-in, import, computation, submission, approval, and return.
- PHPUnit coverage for parser, attendance, payroll, RBAC, and the primary
  three-role end-to-end journey.

### P1 — stretch only after P0 passes the integration gate

- Employee request submission plus HR approval for one request type.
- Basic role dashboards and incomplete-attendance alerts.
- Payroll summary CSV or printable report.
- User activation/deactivation UI.
- Manual attendance adjustment UI.
- Basic contribution-maintenance UI.

### Deferred beyond the five-day phase

- Full email/OTP password recovery and production mail-provider setup.
- Complete user, request, schedule-type, holiday, contribution, archive, and
  reconciliation administration.
- Every report type, polished deposit-slip/payslip PDFs, and 13th-month
  reporting.
- Multiple biometric formats, overnight/split shifts, and complex punch
  correction rules.
- Production-certified Philippine statutory calculations unless the current
  tables, caps, rounding rules, and effective dates are formally approved.
- Rich analytics, a polished SPA/mobile experience, production deployment,
  high availability, disaster recovery, and formal performance certification.

Deferred work must not be represented as complete in the final demonstration.

## 4. Mandatory decision gate

Complete this gate during a pre-kickoff session or within the first two hours
of Day 1. Record decisions in a short Architecture Decision Record (ADR).
Development that depends on an undecided item must not proceed by assumption.

### Business and schema decisions

- [x] Use configurable branch/site/device master data and ADR-0001's
      three-branch/two-site demo topology; official display names remain a
      production seed-data confirmation.
- [x] Approve the canonical employee fields, required fields, uniqueness, and
      archive behavior.
- [x] Approve `payroll_period` → branch `payroll_run` → employee payroll detail.
- [x] Confirm `salary.daily_rate` as authoritative and remove duplicate sources
      of truth.
- [x] Define payroll statuses, allowed transitions, return-reason storage, and
      final-record immutability.
- [x] Approve attendance and request audit fields.
- [x] Approve ADR-0001's `.xls` workbook contract from the three supplied samples.
- [x] Approve timezone, pay period, schedule, zero undocumented grace period, overtime, missing
      punch, rounding, and money precision rules.
- [x] Approve the Final Defense Reviewer example as the demo-only SSS,
      PhilHealth, and Pag-IBIG fixture with half-up centavo rounding and an
      explicit non-production disclaimer.

### Technical decisions

- [x] Use project-owned request/domain validation.
- [x] Use MySQL-persisted server sessions with 30-minute idle and 12-hour
      absolute expiry; logout destroys the server session.
- [x] Use thin native PHP templates with progressive JavaScript.
- [x] Use PHPUnit 12 and PHPStan.
- [x] Use PHP 8.5, Composer 2, PDO repositories, Phinx, MySQL 8.4 LTS, the
      ADR-0003 environment variable names, and local database workflow.
- [x] Use PhpSpreadsheet's explicit `Reader\Xls` behind the pure
      `LdeXlsDailyLogParser`, with ADR-0003 upload and resource controls.
- [x] Use ADR-0001's standard success/error envelopes before
      the two developers implement controllers independently.
- [x] Use ADR-0002 and the v1.1 capstone schema addendum for migration types,
      keys, lineage, lifecycle enums, and integrity tests.

If the business gate cannot be completed, use clearly labeled synthetic demo
rules and exclude statutory-correctness claims from the phase outcome.

## 5. Five-day schedule

## Day 1 — Decisions, foundation, and contracts

### Morning

- Hold a 60–120 minute kickoff with all developers and the HR/business
  reviewer.
- Complete the mandatory decision gate and write the ADR.
- Convert P0 into small tracked issues with one owner and explicit acceptance
  evidence.
- Freeze shared API contracts, model names, money/date conventions, and RBAC
  routes.

### Parallel implementation

**Developer A — foundation and payroll**

- Scaffold the frameworkless PHP/Composer application using the structure in
  `.kiro/steering/structure.md`.
- Add configuration, PDO connection, error handling, health endpoint,
  PHPStan/PHPUnit/PHP syntax scripts, CI, Phinx baseline migrations, and
  synthetic seed data.
- Implement password hashing, login, authenticated identity, role guards, and
  the transaction/audit infrastructure needed by later flows.
- Define payroll-period, payroll-run, payroll-detail, salary, contribution,
  and payslip repository contracts; create the first golden payroll examples.

**Developer B — attendance and user flow**

- Create sanitized valid, invalid, duplicate, unmatched, and incomplete-punch
  fixtures and expected parser outputs.
- Implement the pure `LdeXlsDailyLogParser` and parser tests before any upload
  controller is wired.
- Define the employee, schedule, enrollment, import-batch, punch, and
  attendance contracts with Developer A.
- Establish the minimal PHP layout, role-aware navigation, test helpers, and
  initial login/RBAC smoke test.

### Day 1 integration gate

From a clean checkout, the team must be able to:

1. Install dependencies.
2. Configure a local test database from the documented environment template.
3. Run migrations and seeders.
4. Run static analysis, tests, and PHP syntax/dependency checks.
5. Start the server, pass the health check, log in as all three roles, and
   verify at least one allowed and one denied protected route.

Do not begin incompatible lane-specific schemas if this gate fails.

## Day 2 — Master data and attendance ingestion

**Developer A — foundation and payroll**

- Complete shared PDO repository contracts, Phinx migration ordering, request
  validation, authorization errors, upload transaction helpers, and audit
  writes.
- Implement effective salary lookup, read-only approved contribution fixture,
  and unit-tested contribution/gross-net calculation functions.
- Review every migration and repository contract with Developer B before merge.

**Developer B — attendance and user flow**

- Implement HR create/update for the demo employee, branch assignment,
  biometric enrollment, and one effective schedule.
- Implement transactional `.xls` upload, parser integration, matching, import
  summary, unmatched storage, and idempotent duplicate handling.
- Build thin HR views for employee/schedule/upload and show safe validation
  errors and parsed/matched/unmatched/duplicate counts.

### Day 2 integration gate

An HR Head can create a demo employee, assign a schedule and biometric ID,
upload the agreed fixture, and see deterministic import results. Re-uploading
the same fixture does not duplicate attendance. Developer A reviews the
attendance merge; CI remains green.

## Day 3 — Attendance calculations and payroll vertical slice

**Developer A — foundation and payroll**

- Implement payroll computation from attendance, salary, and approved
  contribution/deduction inputs.
- Persist immutable calculation snapshots and itemized employee payroll details;
  prevent duplicate runs for the same period/branch scope.
- Stabilize shared transactions, audit logging, and protected route wiring;
  resolve integration defects before optional infrastructure.

**Developer B — attendance and user flow**

- Generate daily attendance records and calculate hours worked, late minutes,
  undertime, and overtime using the approved rules.
- Flag incomplete records, expose a minimal review list, and build attendance
  review/payroll-computation screens with Developer A's service contracts.
- Add boundary tests for schedule start/end, duplicate and missing punches,
  unmatched employees, plus the first end-to-end test: login → employee →
  attendance import → payroll computation.

### Day 3 integration gate

The shared demo fixture produces the exact reviewed attendance and payroll
golden outputs through the application—not only through isolated unit tests.
At this point the team has a demonstrable vertical slice even if all UI polish
and optional modules are incomplete.

## Day 4 — Approval, payslip, security, and feature freeze

**Developer A — foundation and payroll**

- Complete submit, approve, and return transitions with a required return
  reason, final payroll immutability, and double-approval protection.
- Expose itemized payslip/payroll summary data and review migration/seed
  repeatability from an empty database.
- Pair with Developer B on the Owner approval and Employee payslip views.

**Developer B — attendance and user flow**

- Complete only the attendance exceptions necessary for the demo; defer manual
  adjustment UI unless all P0 work is green.
- Perform RBAC and employee-ownership regression, including Employee A's
  attempted access to Employee B's attendance or payslip.
- Complete the P0 end-to-end test, security headers, safe errors, and audit
  coverage with Developer A.

### Day 4 integration gate and freeze

By midday, stop accepting P0 feature expansion. After the freeze, merge only:

- Release-blocking defect fixes
- Missing tests for committed behavior
- Security/access-control corrections
- Required demo/documentation corrections

The complete three-role demo journey must pass from a clean seeded database.
No P1 work begins until both developers agree the P0 gate is green.

## Day 5 — Hardening, acceptance, and handoff

### Morning hardening

- Run all unit, integration, and end-to-end tests.
- Run PHPStan, PHPUnit, PHP syntax checks, migration-up, migration-down where
  safe, and clean reseed checks.
- Test Chrome, Firefox, and Edge on the critical demo paths.
- Test invalid login, forbidden routes, cross-employee access, bad upload,
  duplicate upload, incomplete punch, invalid payroll transition, and double
  approval.
- Run a small, recorded performance smoke check with a declared data volume;
  do not claim formal REQN003–REQN006 certification from an undefined load.

### Afternoon acceptance

- HR reviewer compares attendance and payroll results with the golden examples.
- Business Owner validates submit/approve/return behavior.
- Team performs two timed demo rehearsals from a clean database.
- Record known limitations and deferred requirements.
- Tag the accepted MVP revision only after CI and the acceptance checklist pass.
- Hold a short retrospective and prioritize the next development phase.

### Day 5 exit gate

- Clean setup instructions work on another developer machine or clean CI job.
- All P0 tests, PHPStan, and PHP syntax/dependency checks pass.
- No open critical/high security or payroll-calculation defect remains.
- Demo data contains no real employee personal information.
- The acceptance record identifies what passed, failed, and was deferred.
- One rollback/recovery approach for the demo database is documented.

## 6. Collaboration workflow

### Repository workflow

- Protect `main`; never push feature work directly to it.
- Use short-lived branches such as `feature/REQ018-xls-parser`,
  `fix/REQN007-owner-route`, or `test/REQ047-payroll-golden-case`.
- Link every pull request to its issue and applicable `REQ0xx`/`REQNxxx` IDs.
- Require the other developer as reviewer and passing CI.
- Keep pull requests small and single-purpose. Prefer several reviewed slices
  over one end-of-day module dump.
- Squash merge feature branches unless migration history requires otherwise.
- Do not rewrite a migration that teammates have already consumed; add a
  corrective migration.
- Never commit secrets, real employee data, database dumps, or generated
  payslips containing personal information.

### Ownership boundaries

- Developer A coordinates migration order, shared middleware, money precision,
  payroll transitions, and the daily integration gate.
- Developer B owns attendance parsing/calculation behavior, fixtures, shared
  presentation conventions, and the end-to-end suite.
- Both developers own unit/integration tests for their work and review the
  other developer's migration, security, and business-rule changes.
- Any contract/schema change is announced and reviewed before either developer
  begins dependent work.

### Daily cadence

| Time box | Activity |
|---|---|
| 15 minutes, start of day | Stand-up: yesterday's evidence, today's deliverable, blockers, contract/schema changes |
| 10 minutes, before lunch | Dependency sync between affected lane owners |
| 30–45 minutes, late afternoon | Merge window and shared integration-gate run |
| 15 minutes, end of day | Update issue board, risks, decisions, test evidence, and next-day handoff |

Blockers involving requirements or payroll rules must be escalated to the
business/HR reviewer the same day. Developers should not invent business rules
to avoid a visible blocker.

## 7. Issue-board structure

Use these columns:

`Backlog → Ready → In Progress → Review → Integration → Done → Blocked`

Each issue should contain:

- One owner and one reviewer
- Requirement identifiers
- Acceptance scenario
- API/schema dependencies
- Expected tests
- Demo or screenshot/API evidence
- Explicit exclusions

Limit each developer to one primary issue in progress. A second item is allowed
only for a small review or unblock task.

## 8. Definition of done

An issue is done only when:

- Its stated acceptance criteria pass.
- Applicable requirement IDs are traceable in the issue, test, or code comment.
- Code follows the agreed PHP and repository conventions.
- Input validation, authentication, authorization, and employee ownership are
  enforced where applicable.
- Database work includes a reviewed migration and does not rely on manual local
  schema changes.
- Unit/integration tests cover the happy path and important failure path.
- Static analysis, tests, and PHP syntax/dependency checks pass in CI.
- No secrets or real personal/payroll data are committed.
- Relevant API/setup/demo documentation is updated.
- Another developer has reviewed and can run the change.

“Works on my machine,” an unreviewed UI, or a controller without calculation
and authorization tests is not done.

## 9. Critical test matrix

| Area | Minimum evidence during this phase |
|---|---|
| Authentication | Valid login, generic invalid-login error, inactive/unauthorized denial if included |
| RBAC | HR payroll access, Owner approval access, Employee denial from both administrative actions |
| Employee isolation | Employee A cannot read Employee B attendance or payslip |
| Attendance import | Valid, invalid-format, duplicate, unmatched, incomplete, and transaction rollback cases |
| Attendance calculation | On-time, late, undertime, overtime, and missing-punch golden cases |
| Payroll calculation | Zero/typical/boundary inputs, precision and rounding, golden gross/net result |
| Payroll state | Valid submit/approve/return, required return reason, invalid transition, double approval |
| Persistence | Clean migrate/seed, repeated test setup, approved payroll snapshot remains unchanged |
| UI/E2E | One complete three-role journey through the actual application |

## 10. Risk controls

| Risk | Control and fallback |
|---|---|
| Business/schema approval is delayed | Time-box the decision gate. Use documented synthetic demo rules and mark statutory/production behavior unverified. |
| Workbook variants differ from the supplied `.xls` samples | Keep parsing behind `AttendanceFileParser`, reject unknown structures transactionally, and add adapters only from sanitized evidence. |
| Contribution rules are disputed | Use versioned, reviewer-approved golden fixtures; never silently copy current online values into historical payroll. |
| Developers conflict on models | Freeze core contracts on Day 1, review every migration together, and let Developer A coordinate merge order. |
| UI consumes the schedule | Use thin functional forms/views and prioritize the E2E business path over visual polish. |
| Integration happens too late | Merge at least daily; require a real vertical path by Day 3 and freeze features on Day 4. |
| Sensitive information leaks | Use synthetic data, environment-managed secrets, authorization tests, and private generated-file storage. |
| A developer becomes a bottleneck | Pair on critical shared code, require cross-lane reviews, and document daily handoffs. |

## 11. Demonstration script

Target a 12–15 minute demonstration:

1. Start from the documented seeded environment and show passing CI.
2. Sign in as HR Head and demonstrate role-protected navigation.
3. Create or inspect an employee with branch, schedule, biometric ID, and
   salary configuration.
4. Upload one supplied/sanitized `.xls` daily-log fixture.
5. Show matched, duplicate, unmatched, invalid, or incomplete outcomes.
6. Review calculated worked hours, late time, undertime, and overtime.
7. Compute a payroll draft and compare its itemized result with the approved
   golden example.
8. Submit payroll for Owner approval.
9. Sign in as Business Owner and approve it, or first show a return with a
   reason and then approve the revised run.
10. Demonstrate that an approved payroll cannot be edited or approved twice.
11. Sign in as an Employee and view only that employee's payslip.
12. Show the audit trail and summarize deferred functionality honestly.

## 12. Suggested next phase

After this five-day MVP, plan subsequent iterations in dependency order:

1. Confirm production branch names/master data and complete effective-dated
   statutory policy tables before production claims.
2. Complete user management, schedules/holidays, attendance reconciliation,
   and all three request workflows.
3. Complete contribution maintenance, leave balances, salary history, and
   payroll correction/reversal behavior.
4. Add password recovery, all reports/PDF/CSV/bank exports, dashboards, and
   complete employee self-service.
5. Perform defined-volume performance tests, security review, backup/restore,
   deployment, UAT, and production-readiness review.

The five-day output should be treated as the tested foundation for these
iterations, not as a production payroll authorization.
