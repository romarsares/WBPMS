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

This plan is optimized for **four developers plus an available business/HR
reviewer**.

| Lane | Suggested owner | Primary responsibility |
|---|---|---|
| A — Platform and security | Developer A / technical lead | Project scaffold, database, migrations, seeders, authentication, RBAC, CI, shared middleware |
| B — People and attendance | Developer B | Employee, schedule, biometric mapping, `.dat` parser, attendance calculation and exceptions |
| C — Payroll domain | Developer C | Salary, approved contribution rules, payroll calculation, approval state machine, payslip data |
| D — Experience and quality | Developer D | Thin role-specific UI, API integration, dashboards, automated integration/E2E tests, demo data and documentation |
| Product/domain review | Business Owner and HR Head | Same-day decisions on schema, payroll rules, fixtures, and acceptance evidence |

### Team-size adjustments

- **Two developers:** combine A+C and B+D. Commit only authentication,
  employees, fixture-based attendance, simplified payroll, approval, and a
  payslip view.
- **Three developers:** combine A+D; keep B and C separate. Testing remains a
  shared responsibility.
- **Five or more developers:** assign a dedicated integration/QA owner and,
  only after the committed scope is stable, assign another developer to the
  stretch backlog.

One person must be the integration lead each day. The technical lead owns
architecture decisions, but does not become the only reviewer or tester.

## 3. Scope for this phase

### P0 — committed MVP

- Node.js/TypeScript/Express/Sequelize/MySQL project that can be installed,
  migrated, seeded, tested, built, and started from a clean checkout.
- Login with hashed passwords and RBAC for Business Owner, HR Head, and
  Employee.
- Employee list/create/update and branch assignment using synthetic demo
  data.
- One effective employee schedule and biometric device ID mapping.
- Upload and parse one documented `.dat` fixture format.
- Match punches, retain unmatched entries, prevent duplicate imports, flag
  incomplete entries, and calculate hours/late/undertime/overtime for the
  agreed schedule rules.
- Effective salary assignment and domain-approved contribution fixtures.
- Payroll calculation for one pay period with transparent calculation
  details.
- Payroll state flow: Draft/Computed → Pending Owner Approval → Approved or
  Returned, including a return reason and protection against double approval.
- Employee-scoped payslip view; one employee must never see another
  employee's data.
- Minimal audit entries for sign-in failures, attendance adjustment/import,
  payroll computation, submission, approval, and return.
- Automated unit and integration tests for the critical calculations and
  authorization boundaries.

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
- Every report type, bank-transfer formats, polished PDFs, and 13th-month
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

- [ ] Confirm the active branch count and branch seed data.
- [ ] Approve the canonical employee fields, required fields, uniqueness, and
      archive behavior.
- [ ] Approve a payroll-run header plus employee-detail relationship, or
      explicitly approve another cardinality.
- [ ] Confirm which salary field is authoritative and remove duplicate sources
      of truth.
- [ ] Define payroll statuses, allowed transitions, return-reason storage, and
      final-record immutability.
- [ ] Approve attendance and request audit fields.
- [ ] Approve the single `.dat` record format and provide sanitized samples.
- [ ] Approve timezone, pay period, schedule, grace period, overtime, missing
      punch, rounding, and money precision rules.
- [ ] Approve the exact SSS, PhilHealth, and Pag-IBIG fixture/version used in
      the demo, including caps and rounding.

### Technical decisions

- [ ] Select one validation library.
- [ ] Select server-managed sessions or JWT and document logout/expiry rules.
- [ ] Select a thin server-rendered UI or a separate client. Prefer the option
      the team already knows; do not spend this phase building UI infrastructure.
- [ ] Select the unit/integration test runner and HTTP test library.
- [ ] Agree on the Node.js LTS, MySQL version, package manager, environment
      variable names, and local database workflow.
- [ ] Define API contracts and standard success/error response shapes before
      lanes implement controllers independently.

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

**Lane A**

- Scaffold the TypeScript/Express application using the structure in
  `.kiro/steering/structure.md`.
- Add configuration validation, database connection, error handling, health
  endpoint, lint/type-check/build/test scripts, and CI.
- Create the first approved migrations and reference-data seeders.
- Implement password hashing, login, authenticated identity, and role guards.

**Lane B**

- Finalize employee, schedule, attendance-import, punch, and adjustment
  contracts with Lane A.
- Create sanitized valid, invalid, duplicate, unmatched, and incomplete-punch
  fixture files.
- Implement the parser as a pure function with unit tests before wiring upload.

**Lane C**

- Finalize payroll-run, payroll-detail, salary, contribution, deduction, and
  payslip contracts with Lane A.
- Create reviewed golden examples showing every payroll input and expected
  output.
- Implement pure calculation functions and unit tests without waiting for the
  controllers or UI.

**Lane D**

- Establish the shared UI shell, route-aware navigation, API client/form
  conventions, and three role landing pages.
- Create integration-test helpers, database reset/seed utilities, and the
  initial login/RBAC smoke test.
- Document the demo journey and test-user credentials using synthetic data.

### Day 1 integration gate

From a clean checkout, the team must be able to:

1. Install dependencies.
2. Configure a local test database from the documented environment template.
3. Run migrations and seeders.
4. Run lint, type checking, tests, and production build.
5. Start the server, pass the health check, log in as all three roles, and
   verify at least one allowed and one denied protected route.

Do not begin incompatible lane-specific schemas if this gate fails.

## Day 2 — Master data and attendance ingestion

**Lane A**

- Complete shared Sequelize models/associations and migration ordering.
- Add request validation, authorization error handling, upload limits, and
  transaction helpers.
- Review Lane B/C schema usage and prevent duplicated models or migrations.

**Lane B**

- Implement employee CRUD needed by the demo, branch filter, biometric ID
  mapping, and one effective schedule.
- Implement transactional `.dat` upload, parser integration, employee matching,
  import batch summary, unmatched storage, and idempotent duplicate handling.
- Preserve raw imported data rather than silently overwriting it.

**Lane C**

- Implement effective salary lookup and read-only approved contribution data.
- Complete unit-tested contribution and gross/net calculation functions using
  the reviewed golden examples.
- Expose calculation previews with a traceable breakdown.

**Lane D**

- Build thin HR screens for employees, schedules, and attendance upload.
- Display parsed/matched/unmatched/duplicate counts and clear validation errors.
- Add employee and attendance integration tests, including RBAC denial cases.

### Day 2 integration gate

An HR Head can create a demo employee, assign a schedule and biometric ID,
upload the agreed fixture, and see deterministic import results. Re-uploading
the same fixture does not duplicate attendance. CI remains green.

## Day 3 — Attendance calculations and payroll vertical slice

**Lane A**

- Stabilize shared transactions, audit logging, and protected route wiring.
- Resolve integration defects rather than adding optional infrastructure.

**Lane B**

- Generate daily attendance records and calculate hours worked, late minutes,
  undertime, and overtime using the approved rules.
- Flag incomplete records and expose a review list.
- Add boundary tests for schedule start/end, duplicate punches, missing punches,
  and unmatched employees.

**Lane C**

- Implement payroll computation using attendance, salary, and approved
  contribution/deduction inputs.
- Persist an immutable calculation snapshot and itemized employee payroll
  details.
- Prevent duplicate payroll runs for the same approved scope/period according
  to the ADR.

**Lane D**

- Build attendance review and payroll computation/review screens.
- Add the first end-to-end integration test: login → employee → attendance
  import → payroll computation.
- Validate currency, `MM/DD/YY`, and 24-hour formatting.

### Day 3 integration gate

The shared demo fixture produces the exact reviewed attendance and payroll
golden outputs through the application—not only through isolated unit tests.
At this point the team has a demonstrable vertical slice even if all UI polish
and optional modules are incomplete.

## Day 4 — Approval, payslip, security, and feature freeze

**Lane A**

- Perform RBAC regression across every protected endpoint.
- Add ownership checks for employee-scoped records, security headers, safe
  error responses, and audit coverage.
- Review migration/seed repeatability from an empty database.

**Lane B**

- Complete critical attendance exception and manual-adjustment behavior needed
  by the demo.
- Help fix cross-module defects in payroll inputs.

**Lane C**

- Complete submit, approve, and return transitions with a required return
  reason.
- Enforce final payroll immutability and double-approval protection.
- Expose itemized payslip data and payroll summary data.

**Lane D**

- Build Owner approval/return and Employee payslip views.
- Ensure Employee A cannot access Employee B's attendance or payslip.
- Complete the P0 end-to-end test and, if P0 is green by midday, take only one
  agreed P1 stretch item.

### Day 4 integration gate and freeze

By midday, stop accepting P0 feature expansion. After the freeze, merge only:

- Release-blocking defect fixes
- Missing tests for committed behavior
- Security/access-control corrections
- Required demo/documentation corrections

The complete three-role demo journey must pass from a clean seeded database.

## Day 5 — Hardening, acceptance, and handoff

### Morning hardening

- Run all unit, integration, and end-to-end tests.
- Run lint, type checking, build, migration-up, migration-down where safe, and
  clean reseed checks.
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
- All P0 tests and the production build pass.
- No open critical/high security or payroll-calculation defect remains.
- Demo data contains no real employee personal information.
- The acceptance record identifies what passed, failed, and was deferred.
- One rollback/recovery approach for the demo database is documented.

## 6. Collaboration workflow

### Repository workflow

- Protect `main`; never push feature work directly to it.
- Use short-lived branches such as `feature/REQ018-dat-parser`,
  `fix/REQN007-owner-route`, or `test/REQ047-payroll-golden-case`.
- Link every pull request to its issue and applicable `REQ0xx`/`REQNxxx` IDs.
- Require at least one reviewer outside the author's lane and passing CI.
- Keep pull requests small and single-purpose. Prefer several reviewed slices
  over one end-of-day module dump.
- Squash merge feature branches unless migration history requires otherwise.
- Do not rewrite a migration that teammates have already consumed; add a
  corrective migration.
- Never commit secrets, real employee data, database dumps, or generated
  payslips containing personal information.

### Ownership boundaries

- Lane A coordinates migration order and shared middleware; other lanes may
  propose schema changes through reviewed PRs.
- Lane B owns attendance parsing/calculation behavior and fixtures.
- Lane C owns monetary formulas, precision, rounding, and payroll transitions.
- Lane D owns shared presentation conventions and the E2E suite.
- Every lane owns its own unit and integration tests; testing is not handed off
  only to Lane D.
- Any contract/schema change is announced immediately to all affected lanes.

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
- Code follows the agreed TypeScript and repository conventions.
- Input validation, authentication, authorization, and employee ownership are
  enforced where applicable.
- Database work includes a reviewed migration and does not rely on manual local
  schema changes.
- Unit/integration tests cover the happy path and important failure path.
- Lint, type checking, tests, and production build pass in CI.
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
| Real `.dat` format is unavailable | Support one agreed synthetic fixture format, isolate the parser, and do not claim device compatibility. |
| Contribution rules are disputed | Use versioned, reviewer-approved golden fixtures; never silently copy current online values into historical payroll. |
| Lanes conflict on models | Freeze core contracts on Day 1 and coordinate all migrations through Lane A. |
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
4. Upload the agreed `.dat` fixture.
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

1. Resolve remaining canonical schema and statutory policy decisions.
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