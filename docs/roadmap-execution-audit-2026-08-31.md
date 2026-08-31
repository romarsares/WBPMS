# Roadmap Execution Audit — 2026-08-31

**Basis checked:** Developer A foundation commit `5c4fc3a`, current uncommitted
Developer B attendance/user-flow work, `tasks.md`, and the five-day roadmap.

## Status key

- **Implemented, unverified** — source is present but cannot be accepted until
  PHP/Composer, database migrations, and tests run successfully.
- **Partial** — a schema, DTO, template, contract, or limited unit test exists,
  but the usable end-to-end feature and its required tests do not.
- **Not started** — no implementation evidence was found.

## P0 ownership audit

| Owner | Roadmap item | Status | Evidence and remaining acceptance work |
|---|---|---|---|
| A | A1: runtime, PDO, health, quality scaffold | Implemented, unverified | Composer, front controller, bootstrap, connection, router, health endpoint, PHPUnit/PHPStan configuration, and foundational tests exist. PHP/Composer are unavailable on this workstation, so the Day 1 executable gate has not passed. |
| A | A2: canonical migrations and demo seed data | Implemented, unverified | Six Phinx migrations and roles, branches/devices, user, employee, and contribution seeders exist. Migration-up/down/reseed and invariant tests have not run. |
| A | A3: login/RBAC/CSRF/session | Implemented, unverified | Auth service/controller, middleware, database session handler, and unit tests exist. Password recovery is deliberately deferred; three-role browser verification has not run. |
| A | A4: salary/contribution calculation | Partial | Schema and labeled contribution fixture exist. No salary/contribution engine or golden calculation tests were found. |
| A | A5: payroll computation and approval | Not started | Payroll schema exists, but no payroll service, state-transition logic, payslip read model, or tests were found. |
| A | A6: upload boundary, audit/RBAC regression, protected routes | Partial | CSRF/RBAC/audit foundation exists. The secure attendance-upload boundary, protected attendance routes, and regression run are not implemented. |
| B | B1: XLS parser, DTO/error contract, fixture tests | Partial | `LdeXlsDailyLogParser`, immutable parser DTOs/errors, and valid/invalid-time tests exist. Formula, corruption, renamed file, resource, date-duplicate, and other required fixture cases are still missing and the suite has not run. |
| B | B2: employee/schedule/enrollment setup | Partial | Master-data schema, demo employee seed, setup gateway/service contract, and employee form exist. No PDO gateway/repository, controller, effective-period validation, or integration test exists. |
| B | B3: attendance import, matching, generation, HR review | Partial | Import gateway contract, orchestration service, timesheet generator, summary DTO, and HR templates exist. No PDO gateway, upload boundary, matching query, punch/lineage persistence, attendance controller, or integration tests exist. |
| B | B4: employee payslip/ownership/user flow | Partial | Ownership guard, isolation unit test, date/time formatter, and employee templates exist. No portal controller, payslip read model/download, browser test, or authenticated route is wired. |
| B | B5: seeded walkthrough and E2E triage | Partial | Sanitized fixture policy and golden parser-output JSON exist. The seeded three-role end-to-end walkthrough, expected payroll results, and defect triage are not present. |

## Five-day roadmap comparison

| Day | Required gate | Result |
|---|---|---|
| Day 1 | Clean setup, migrations/seeders, static analysis/tests, server health, three-role login plus allowed/denied route | **Not passed.** Source and tests are present, but no PHP/Composer runtime or database execution evidence exists. |
| Day 2 | HR can create setup data, upload a workbook, see deterministic summary, and re-upload without duplicates | **Not passed.** Schema/contracts/templates exist; persistence, upload handling, controllers, and tests are missing. |
| Day 3 | Attendance-to-payroll vertical slice produces reviewed golden outputs | **Not passed.** Pure attendance calculation exists; payroll service and database integration do not. |
| Day 4 | Approval/payslip journey, ownership regression, security/audit coverage | **Not passed.** Foundation RBAC exists; payroll approval, payslip delivery, browser checks, and E2E coverage do not. |
| Day 5 | Full automated suite, migration/reseed, browser smoke, acceptance, performance smoke, recovery plan | **Not started.** These depend on the preceding gates. |

## Task checklist comparison

The checked decision records in `tasks.md` (1.0, 1.0a, 1.0b, and 9.0) are
documented decisions, not proof that their dependent executable modules are
complete. No unchecked implementation task was marked complete by this audit.

### Current completed evidence

- Approved ADR and canonical-schema documentation gates.
- Developer A foundation source: migration/seed/auth/RBAC/session/router
  scaffold and unit-test source.
- Developer B pure attendance parser DTO/error contract, initial parser tests,
  timesheet calculation, ownership guard, and template/route contracts.

### Blocking conditions before any P0 item can be marked complete

1. Install PHP 8.5 and Composer, then install the locked dependencies.
2. Configure a MySQL test database and run migrations/seeds from a clean state.
3. Implement the missing PDO repositories/gateways and connect controllers to
   the router.
4. Add the remaining required test matrix and run PHPUnit, PHPStan, migration,
   browser, and end-to-end checks successfully.
5. Record the three-role acceptance run and resolve critical/high defects.

## Integration note

Developer B code is aligned to Developer A's `Wbpms\\` PSR-4 namespace,
shared PhpSpreadsheet/Phinx/PHPUnit configuration, `HRHead`/`Employee` role
names, and attendance schema conventions. The Developer B route manifest is
expressed in the shared `Router::add()` format but is intentionally not loaded
from `routes/web.php` until the referenced controllers and persistence layer
are implemented.
