# WBPMS — Consolidated Recommendations

**Prepared:** 2026-08-30
**Sources reviewed:** `five-day-development-roadmap.md`, `database-schema.md`,
`database-documentation-revision-log.md`, `design.md`, `requirements.md`

This document consolidates every recommendation found across the project
documentation into one reference. Items are grouped by concern area.
Each entry notes its source document and, where applicable, whether it
is gated behind an approval decision before implementation work may begin.

---

## 1. Pre-Kickoff Decisions (Gate — Must Resolve Before Coding)

These items are listed as open checkboxes in the roadmap's mandatory
decision gate (§4). No development that depends on an undecided item
should proceed by assumption.

### 1.1 Business and Schema Decisions

| # | Decision required | Source | Blocking what |
|---|---|---|---|
| B-1 | Confirm the active branch count (source says both 2 and 3). Approve branch seed data. | `roadmap §4`, `database-schema.md §5 issue 11`, `revision log §1` | Branch filtering, payroll runs, employee assignments, demo seeds |
| B-2 | Approve the canonical employee attribute set — required fields, uniqueness rules, and archive behavior. | `roadmap §4`, `revision log §1`, `database-schema.md §6 rec 3` | `employee` migration, `EmployeeService`, Employee Management module |
| B-3 | Approve a payroll-run header plus employee-detail relationship (or explicitly approve another cardinality). | `roadmap §4`, `database-schema.md §6 rec 4` | `payroll`, `payroll_earnings`, `deduction` migrations |
| B-4 | Confirm which salary field is authoritative and remove duplicate sources of truth. | `roadmap §4` | `salary` migration, `SalaryService.getCurrentRate` |
| B-5 | Define payroll statuses, allowed transitions, return-reason storage, and final-record immutability. | `roadmap §4` | `PayrollService` state machine, payroll approval flow |
| B-6 | Approve attendance and request audit fields. | `roadmap §4` | `attendance_adjustment`, `audit_logs` migrations |
| B-7 | Approve the single `.dat` record format and provide a sanitized sample file. | `roadmap §4` | `AttendanceService.parseDatFile`, all attendance import tests |
| B-8 | Approve timezone, pay period, schedule, grace period, overtime, missing punch, rounding, and money precision rules. | `roadmap §4` | Every attendance and payroll calculation |
| B-9 | Approve the exact SSS, PhilHealth, and Pag-IBIG fixture/version used in the demo, including caps and rounding. | `roadmap §4` | `ContributionEngine`, seeder data, payroll golden test cases |
| B-10 | Confirm the `cash_advance_history` ↔ `request` relationship — is an approved cash-advance request the trigger for creating a repayment row? | `database-schema.md §8`, `design.md Data Models` | `CashAdvanceHistory` migration and `RequestService` |
| B-11 | Decide whether geography is a strict province → city → barangay hierarchy. If yes, add `city.province_id` and `barangay.city_id` FKs explicitly. | `database-schema.md §6 rec 6` | `city`, `barangay` migrations |

### 1.2 Technical Decisions

| # | Decision required | Source | Blocking what |
|---|---|---|---|
| T-1 | Select one validation library (`zod` or `express-validator`). | `roadmap §4`, `tech.md` | All controllers and middleware |
| T-2 | Select server-managed sessions or JWT. Document logout and expiry rules. | `roadmap §4`, `tech.md` | `AuthController`, `AuthMiddleware`, `REQN011` |
| T-3 | Select a thin server-rendered UI or a separate SPA client. Prefer the option the team already knows. | `roadmap §4` | Lane D work, shared UI shell |
| T-4 | Select the unit/integration test runner and HTTP test library. | `roadmap §4` | All test scaffolding |
| T-5 | Agree on Node.js LTS version, MySQL version, package manager, environment variable names, and local database workflow. | `roadmap §4` | `package.json`, CI configuration, developer onboarding |
| T-6 | Freeze API contracts and standard success/error response shapes before lanes implement controllers independently. | `roadmap §4` | All cross-lane controller/service integration |

---

## 2. Schema and Database Recommendations

### 2.1 Canonical Schema Decisions (from `database-schema.md §6`)

| # | Recommendation | Status in `design.md` |
|---|---|---|
| S-1 | Use Figure 163/164 as the operational base model (richer payroll, schedule, approval, audit structures). | ✅ Applied with disbursement exception |
| S-2 | Keep `employee.branch_id` — it is source-backed by both database-model figures. Implement via effective-dated `employee_branch_assignment` to preserve transfer history. | ✅ Applied |
| S-3 | Reconcile employee fields by taking the union of identity, employment, address, branch, and government-identifier fields from both sources. Document nullability and uniqueness explicitly. | ✅ Applied (pending approval) |
| S-4 | Use child tables keyed by `payroll_id` for multiple earnings and deductions rather than the single `payroll.deduction_id` structure in Table 92. | ✅ Applied |
| S-5 | Use canonical corrected names: `city_name` (not `description`), `request_type_id` (not `Requeest_type_id`), `payroll_earnings` (not `PAYROLL_LEARNINGS`), `log_id` (not `Log_in`), separate `approval_status` and `remarks` (not `approval_status_remarks`). | ✅ Applied |
| S-6 | Do not add `city.province_id` / `barangay.city_id` hierarchy FKs by default. Keep geography flat (three independent IDs on `address`) unless explicitly decided otherwise. | ⚠️ Decided flat by default — underlying business need still open |
| S-7 | Complete full Data Dictionary entries (types, defaults, nullability, lengths, validation rules) for every canonical table before implementation sign-off. | ❌ Not done — required before migrations |
| S-8 | Exclude the orphan `benefits_tbl` from the canonical model. Government contributions are deductions, per the supplemental DFD. Any future benefit feature requires separate approved requirements. | ✅ Applied |
| S-9 | Make `users.employee_id` nullable and unique. The Business Owner is a role-bearing user with no employee row and is excluded from payroll. | ✅ Applied |
| S-10 | Add `payroll.pay_date` to separate the Friday disbursement from the Friday–Thursday attendance period, making monthly contribution scheduling deterministic. | ✅ Applied (via `payroll_period.pay_date`) |
| S-11 | Replace the assumed bank-file flow with a weekly disbursement batch (one aggregate cheque) and individual employee deposit-slip records. No ATM or non-BDO flow is in scope. | ✅ Applied (`disbursement_batch` + `deposit_slip`) |

### 2.2 Required Schema Extensions (not in original documentation)

These tables satisfy requirements that no source model ever covered. They
must be present before the relevant modules can be built.

| Table | Purpose | Requirement |
|---|---|---|
| `sss_bracket` | Effective-dated SSS salary bracket/contribution lookup | REQ061–062 |
| `philhealth_rate` | Current PhilHealth policy rate with effective date | REQ063–064 |
| `pagibig_rate` | Current Pag-IBIG policy rate with effective date | REQ063–064 |
| `holiday_calendar` | Holiday dates with type (`Regular`/`Special`) and pay multiplier | REQ030, REQ047 |
| `contribution_record` | EEMR basis, employee/employer shares, deduction date, lock/audit state | REQ058–064 |
| `payroll_period` | Concrete Friday–Thursday pay period shared across branch runs | REQ047, REQ010 |
| `payroll_run` | One branch-level payroll transaction per period with its own approval state | REQ050–051 |
| `attendance_import_batch` | Import audit trail (file, checksum, parsed/matched/unmatched counts) | REQ018–024 |
| `biometric_punch` | Immutable raw punch staging; unmatched punches retained for HR review | REQ018–024 |
| `biometric_device` | Registered import source with file format, timezone, and coverage | REQ018–024 |
| `biometric_device_branch` | Effective-dated N–M device-to-branch coverage | REQ018 AC3, AC12 |
| `attendance_site` | Physical biometric location (separate from organizational branch) | REQ018 AC3 |
| `employee_branch_assignment` | Effective-dated history for permanent transfers | REQ004 AC10–11 |
| `employee_biometric_enrollment` | Device-scoped employee identity for punch matching | REQ018 AC4 |
| `disbursement_batch` | One weekly pay-to-cash cheque record | REQ073 |
| `deposit_slip` | One BDO deposit-slip record per employee per week | REQ073 |
| `attendance_policy_flag` | HR-review alerts for tardiness/absence thresholds; no automatic discipline | REQ006 AC16–17 |

### 2.3 Literal Source Corrections (apply in capstone documentation)

| Original text (Table 85 / Table 90) | Corrected text | Reason |
|---|---|---|
| `Requeest_type_id` | `request_type_id` | Typographical error |
| `PAYROLL_LEARNINGS` | `PAYROLL_EARNINGS` | Typographical error |
| `Log_in` | `log_id` | Does not describe a row identifier |
| `approval_status_remarks` | `approval_status`, `remarks` (two separate columns) | Table 97 separates them |
| `CITY.description` | `CITY.city_name` | Table 89 uses the specific name |
| Table 90 heading "Data Dictionary of Cash Advance" | "Data Dictionary of Barangay" | Body contains `barangay_id`/`barangay_name` |

---

## 3. Architecture and Implementation Recommendations

### 3.1 Project Scaffold

- Scaffold the TypeScript/Express application following the layout in
  `.kiro/steering/structure.md` before any feature branches begin.
- Add configuration validation, database connection, error handling, a
  health endpoint, and lint/type-check/build/test scripts on Day 1.
- Create CI that runs lint, type check, unit tests, and a production build on
  every pull request. Do not merge to `main` without a green CI run.

### 3.2 Authentication and RBAC

- Implement password hashing (`bcrypt`) and session/token auth before any
  business module. RBAC guards must be in place before business-module
  controllers are wired up.
- Return a generic error on failed login without revealing which field
  was incorrect (REQ001 AC2).
- Restrict every protected route via `AuthMiddleware` that checks `role_id`
  against the RBAC table in `design.md`.
- Write an RBAC smoke test on Day 1 (at least one allowed and one denied
  route per role) before other lanes proceed.

### 3.3 Business Logic Placement

- All calculations (`AttendanceService.computeHours`, `ContributionEngine`,
  `PayrollService`) must live in the Service layer with no Express or
  Sequelize dependencies, so they can be unit-tested in isolation.
- Payroll computation must persist an immutable calculation snapshot —
  approved payroll records must never be mutated.
- The payroll approval state machine
  (`Draft → Computed → Pending Owner Approval → Approved / Returned`)
  must be enforced at the application layer with a database-level unique
  constraint preventing double approval.

### 3.4 Attendance Import

- The `.dat` parser must be a pure function (no DB calls) tested
  independently with valid, invalid, duplicate, unmatched, and
  incomplete-punch fixture files before being wired to the upload endpoint.
- An invalid file must be rejected outright — no partial import, no partial
  persistence.
- Raw punches must be persisted immutably in `biometric_punch`; never
  overwrite imported data — only annotate it via `attendance_adjustment`.
- Unmatched punches must be retained in `biometric_punch` for HR
  reconciliation; they must never be dropped or auto-assigned.
- Import must be transactional: failure at any stage rolls back completely.
- File checksum stored on `attendance_import_batch` is the idempotency key
  for duplicate-upload prevention.

### 3.5 Payroll Calculation Rules

Follow these rules exactly when implementing `PayrollService`:

- **Pay period:** Friday through Thursday; Saturday is the rest day; six
  scheduled working days per week.
- **Overtime:** `hourly_rate × 1.25 × overtime_hours`.
- **Regular holiday:** `daily_rate × 2.00` when work is performed.
- **Special holiday:** `daily_rate × 1.30` when work is performed.
- **Holiday + overtime compounding:** do not compound until HR explicitly
  confirms a rule. Record as separate line items.
- **EEMR basis for contributions:** `(daily_rate × 313) ÷ 12`. Use this
  as Monthly Basic Salary for SSS and PhilHealth — not variable weekly earnings.
- **Contribution deduction schedule:** deduct SSS, PhilHealth, and Pag-IBIG
  employee shares only on the last Friday of the month. Employer shares must
  still be recorded for reporting every month.
- **Income tax:** apply zero withholding while projected annual taxable
  income is ≤ ₱250,000. Store the threshold as a configurable value, not a
  hardcoded constant. Do not treat this as a permanent universal exemption.
- **13th-month pay:** `total basic salary paid in the year ÷ 12`.
- **Disbursement:** one aggregate pay-to-cash cheque per week; one manually
  prepared BDO deposit slip per employee. No bank API or ATM payroll.

### 3.6 Employee Transfers

- A transfer closes the prior `employee_branch_assignment` and opens a
  new non-overlapping effective-dated assignment without rewriting any
  historical attendance, payroll, or payslip data.
- Payroll membership uses the branch assignment effective on the
  `payroll_period.period_start` date. Mid-period transfers do not split
  the employee's payroll.
- Attendance punch matching uses the punch timestamp, not the current
  or upload-time branch.

### 3.7 Employee Self-Service Scoping

- `EmployeePortalController` must always scope every query to
  `session.employee_id`. An employee must never be able to read another
  employee's attendance, requests, or payslips.
- This access boundary must be covered by an automated integration test
  (Employee A cannot read Employee B's records).

---

## 4. Testing Recommendations

### 4.1 Required Test Coverage (P0 MVP)

| Area | Minimum evidence required |
|---|---|
| Authentication | Valid login; generic invalid-login error; inactive/unauthorized denial |
| RBAC | HR payroll access; Owner approval access; Employee denied from both administrative actions |
| Employee isolation | Employee A cannot read Employee B's attendance or payslip |
| Attendance import | Valid, invalid-format, duplicate, unmatched, incomplete, and transaction-rollback cases |
| Attendance calculation | On-time, late, undertime, overtime, and missing-punch golden cases |
| Payroll calculation | Zero/typical/boundary inputs; precision and rounding; golden gross/net result |
| Payroll state | Valid submit/approve/return; required return reason; invalid transition; double-approval prevention |
| Persistence | Clean migrate/seed; repeated test setup; approved payroll snapshot unchanged after approval |
| E2E | One complete three-role journey through the running application |

### 4.2 Testing Approach

- Unit-test all Service/Engine calculation functions with no DB dependency.
  Use golden input/output pairs including edge cases: exact schedule match,
  missing punch, zero salary, SSS bracket boundaries, last-Friday vs.
  non-last-Friday contribution runs.
- Integration-test each Controller against a seeded test database.
- Write tests before wiring controllers — Lane B and Lane C must have
  unit-tested pure functions on Day 1 regardless of UI or DB readiness.
- Lane D owns E2E tests; every other lane owns its own unit and integration
  tests. Testing is not handed off to one person.
- Never commit real employee personal data, payroll data, or database dumps
  to the repository.

---

## 5. Repository and Workflow Recommendations

- Protect `main`; never push feature work directly.
- Use short-lived branches named after requirement IDs, e.g.,
  `feature/REQ018-dat-parser`, `fix/REQN007-owner-route`.
- Link every pull request to its issue and applicable `REQ0xx`/`REQNxxx`
  identifiers. Use those same identifiers in code comments (`// implements REQ047`).
- Require at least one reviewer outside the author's lane and passing CI
  before merging.
- Never rewrite a migration that teammates have already consumed; add a
  corrective migration instead.
- Never commit secrets, real employee data, database dumps, or generated
  payslips containing personal information.
- Keep pull requests small and single-purpose. Prefer reviewed slices over
  end-of-day module dumps.
- Lane A owns migration ordering and shared middleware; other lanes propose
  schema changes through reviewed PRs.
- Announce any contract/schema change immediately to all affected lanes.

---

## 6. Capstone Documentation Update Checklist

These corrections must be made to the original capstone document itself
(tracked in `database-documentation-revision-log.md`). No migration is
authorized until the checklist is signed off.

- [ ] Select and approve a canonical schema owner.
- [ ] Confirm branch count (2 vs. 3).
- [ ] Approve the canonical employee attribute set.
- [ ] Approve payroll header/detail cardinalities.
- [ ] Confirm request and attendance-adjustment audit requirements.
- [ ] Approve and add holiday and government-rate entities.
- [ ] Correct all literal Table 85 / Table 90 errors listed in §2.3.
- [ ] Regenerate Table 85 from the approved schema.
- [ ] Regenerate Figures 163 and 164 from the approved schema.
- [ ] Create full Data Dictionary entries for every approved entity.
- [ ] Verify every FK in the logical model exists in the physical model
      with the correct parent-table reference.
- [ ] Update `design.md`, SQL migrations, ORM models, and tests only after
      the canonical decisions are recorded and signed off.

---

## 7. Five-Day MVP Recommended Sequence

Based on the roadmap, this is the recommended implementation order:

1. **Day 1 — Foundation:** scaffold, configuration, DB connection, migrations
   and seeders, auth + RBAC, CI, frozen API contracts. Do not begin
   lane-specific schema until the Day 1 integration gate passes.
2. **Day 2 — Master data and attendance ingestion:** employee CRUD, schedule
   assignment, biometric ID mapping, `.dat` upload + parser, employee
   matching, import summary, idempotent duplicate handling, salary lookup,
   contribution calculation functions.
3. **Day 3 — Calculations and payroll vertical slice:** attendance hours/late/
   undertime/overtime computation, payroll computation, immutable snapshot
   persistence, first E2E integration test.
4. **Day 4 — Approval, payslip, security (feature freeze at midday):**
   submit/approve/return state transitions, return-reason enforcement,
   double-approval protection, payslip data, employee-scoped portal, RBAC
   regression, ownership checks.
5. **Day 5 — Hardening and acceptance:** full test suite, lint, type check,
   build, cross-browser check, demo rehearsal, acceptance against golden
   outputs, tag accepted revision, document deferred items.

**P1 stretch** (only after P0 passes the Day 4 integration gate):
request submission + HR approval, basic dashboards, payroll summary CSV,
user activation UI, manual attendance adjustment UI.

---

## 8. Deferred Items (Out of Scope for Five-Day Phase)

Do not represent the following as complete in the MVP demonstration:

- Full email/OTP password recovery with a production mail provider.
- Complete user, request, schedule-type, holiday, contribution, archive, and
  reconciliation administration screens.
- Every report type, bank-transfer formats, polished PDFs, and 13th-month
  reporting.
- Multiple biometric `.dat` formats, overnight/split shifts, and complex
  punch-correction rules.
- Production-certified Philippine statutory calculations unless the
  contribution tables, caps, rounding rules, and effective dates are
  formally approved.
- Rich analytics, polished SPA/mobile experience, production deployment,
  high availability, disaster recovery, and formal performance certification.
