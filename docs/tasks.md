# Implementation Plan

> **Two-developer P0 assignment:** See
> [two-developer task distribution](two-developer-task-distribution.md) for
> active owners, exact task IDs, dependencies, handoffs, and deferred work.

- [x] 1. Project and database foundation
  - [x] 1.0 **Development approval gate:** [ADR-0001](adr/0001-development-baseline.md) accepted
  - [x] 1.0a **Schema integrity gate:** [ADR-0002](adr/0002-schema-integrity-corrections.md) and [canonical v1.1 addendum](capstone_files/Canonical-Database-Schema-v1.1.md) accepted
  - [x] 1.0b **PHP/parser architecture gate:** [ADR-0003](adr/0003-frameworkless-php-and-xls-parser.md) accepted
  - [x] 1.1 Frameworkless PHP/Composer project structure scaffolded
  - [x] 1.2 Six Phinx migrations covering all canonical v1.1 tables with FKs, checks, unique keys
  - [x] 1.3 Five seeders: roles/request types, demo users/branches/employees, contribution fixture
  - [x] 1.4 PDO connection factory (`Connection`), transaction boundary, environment validation
  - [x] 1.5 `SchemaInvariantsTest` — ADR-0002 uniqueness, calendar, lifecycle, temporal-overlap invariants
  - _Requirements: foundation for all_

- [x] 2. Authentication and RBAC
  - [x] 2.1 `AuthController.login` — `password_hash`/`password_verify`, audit logging (REQ001, REQN011)
  - [x] 2.2 Password recovery: `showForgot` → `sendOtp` (6-digit OTP, `password_reset_challenge`) →
        `showOtpForm`/`verifyOtp` → `showResetForm`/`resetPassword` (REQ002)
        OTP shown on-screen in `development` mode; email hook point documented for production.
  - [x] 2.3 `AuthMiddleware` — 30-min idle, 12-hr absolute TTL, role matrix, CSRF (REQN007)
  - [x] 2.4 `DatabaseSessionHandler` — MySQL-backed sessions
  - [x] 2.5 Unit tests: login success/failure, RBAC denial, session expiry, CSRF
  - _Requirements: 1_

- [x] 3. User Management module
  - [x] 3.1 `UserService` — list/create/update/activate/deactivate/archive (REQ004–REQ008)
  - [x] 3.2 `UserController` + views — user table, create/edit form, status toggle, archive
  - [x] 3.3 `UserLifecycleTest` integration test — full lifecycle
  - _Requirements: 3_

- [x] 4. Employee Management module
  - [x] 4.1 `EmployeeController` — index, create, store, show, editForm, update (REQ009–REQ017)
  - [x] 4.2 `EmployeeRepository` implements `EmployeeSetupGateway` — createEmployee, assignBranch,
        assignSchedule, enrollBiometric
  - [x] 4.3 Views: `hr/employees/index.php`, `hr/employees/form.php`, `hr/employees/edit.php`
  - [ ] 4.4 `transferEmployee` transaction (close old assignment, open new non-overlapping one)
  - [ ] 4.5 Integration test: create → filter → transfer → historical lookup → archive
  - [ ] 4.6 Employee lifecycle archive, rehire, and document capability
    - [ ] 4.6.1 Add `employee_employment_episode`, `employee_lifecycle_event`, and
          `employee_document` migrations, keys, retention metadata, and schema invariants.
    - [ ] 4.6.2 Implement `EmployeeLifecycleService::archive` as a locked transaction:
          validate blockers; close effective branch/schedule/biometric/salary/bank records;
          set employee Archived; set a linked Active user Inactive; revoke sessions; write
          lifecycle and audit events; preserve immutable history.
    - [ ] 4.6.3 Implement the rehire transaction: reuse employee identity, create a new
          employment episode and current setup, prevent overlap, and require controlled
          account reactivation.
    - [ ] 4.6.4 Replace generic employee-status editing with HR archive/rehire confirmation
          flows that show effective dates, impacted records, and blockers.
    - [ ] 4.6.5 Add private employee-document upload/list/view/replace/verify/archive flows;
          random storage keys, PDF/JPEG/PNG signature/type/size validation, checksums,
          malware-scan state, HR-only authorization, and access audit logs.
    - [ ] 4.6.6 Tests: successful archive cascade; rollback on each blocker; no mutation of
          approved payroll/history; session revocation; rehire without duplicate employee or
          account; document replacement lineage; upload/security rejection cases.
    - _Specification: `employee-lifecycle-archive-rehire-spec.md`; Requirements: REQ012,
      REQN007, REQN011 extensions_
  - _Requirements: 4_

- [x] 5. Work Schedule module
  - [x] 5.1 `ScheduleController` — index, store (REQ025–REQ031)
  - [x] 5.2 `ScheduleRepository` — findAll, create
  - [x] 5.3 View: `hr/schedules/index.php`
  - [ ] 5.4 Holiday calendar CRUD
  - [ ] 5.5 Overlap-rejection unit test
  - _Requirements: 5_

- [x] 6. Attendance Management module
  - [x] 6.1 `LdeXlsDailyLogParser` (`LDE_XLS_DAILY_LOG_V1`) — OLE signature, headers, date columns,
        string-preserved Enroll ID, `HH:mm` token expansion, resource limits (ADR-0003)
  - [x] 6.2 Upload boundary in `AttendanceController.upload` — extension + OLE + SHA-256 +
        randomized private storage + cleanup + resource limits
  - [x] 6.3 `PdoAttendanceImportGateway` — full PDO implementation of `AttendanceImportGateway`:
        enrollment matching, branch coverage check, schedule resolution, duplicate punch check,
        matched/unmatched punch persistence, generated attendance upsert, batch lifecycle
  - [x] 6.4 `AttendanceImportService` — parse → match → transactional persist → branch-grouped summary
  - [x] 6.5 `TimesheetGenerator` — one/two/multi-punch rules, INCOMPLETE + MULTI_PUNCH_REVIEW flags,
        late/undertime/overtime calculation against effective `WorkSchedule`
  - [x] 6.6 `AttendanceController` — index (list), import (form + batch history), upload (full flow)
  - [x] 6.7 Views: `hr/attendance/index.php`, `hr/attendance/import.php`, `hr/attendance/summary.php`
  - [ ] 6.8 Manual adjustment UI with audit evidence (REQ023)
  - [ ] 6.9 Parser tests: leading-zero enrollment, bad extension/signature, formula, resource limits,
        duplicate file/punch, unmatched enrollment, out-of-coverage, rollback
  - _Requirements: 6_

- [x] 7. Request Management module
  - [x] 7.1 `RequestsController` — index, show, approve, reject, archive (REQ032–REQ046)
  - [x] 7.2 `EmployeePortalController.storeRequest` — submit with type + reason validation (REQ076–REQ080)
  - [x] 7.3 Views: `employee/requests/index.php`, `employee/requests/form.php`
  - [ ] 7.4 `RequestService` with leave entitlement ledger + insufficient-balance block (REQ078)
  - [ ] 7.5 Cash advance obligation creation on approval
  - [ ] 7.6 Integration test: submit → balance rejection → approve → archive
  - _Requirements: 7, 12_

- [x] 8. Manage Salary module
  - [x] 8.1 `SalaryController` — index, create, store, edit, update, archive (REQ053–REQ057)
  - [x] 8.2 View: `salary/index.php`
  - [ ] 8.3 `SalaryService` with effective-date history preservation
  - [ ] 8.4 Unit test: rate update does not mutate history rows
  - _Requirements: 8_

- [x] 9. Benefits and Deductions module
  - [x] 9.1 `BenefitsController` — index, policies, approvePolicy, lockRecord, unlockRecord (REQ058–REQ062)
  - [x] 9.2 View: `benefits/index.php`
  - [x] 9.3 Contribution calculations wired into `PayrollService` (SSS bracket lookup, PhilHealth rate,
        Pag-IBIG fixed/rate model, EEMR formula) — see task 10
  - [ ] 9.4 Golden tests: ₱460 daily-rate example, last-Friday behavior, rounding, unsupported-policy rejection
  - _Requirements: 9_

- [x] 10. Payroll Processing module
  - [x] 10.1 `PayrollService.createPeriod` — Friday-start validation, Thursday end, pay_date = next Friday
  - [x] 10.2 `PayrollService.createRun` — period + branch, resolves active payroll policy
  - [x] 10.3 `PayrollService.computeRun` — eligibility by branch assignment, basic pay, overtime (×1.25),
        late deduction (₱1/min), undertime deduction, SSS/PhilHealth/Pag-IBIG via approved policy,
        cash-advance repayment (₱500/week), itemized `payroll_earnings` and `deduction` rows,
        `contribution_record` rows, run totals (REQ047)
  - [x] 10.4 `PayrollService.approve` — sets `Approved`, auto-generates one `payslip` row per employee (REQ048)
  - [x] 10.5 `PayrollService.submitForApproval` / `returnForRevision` — full state machine
        `Draft → Computed → PendingOwnerApproval → Approved / Returned` (REQ050, REQ051)
  - [x] 10.6 `PayrollService.compute13thMonth(year)` — total basic / 12 across approved runs (REQ049)
  - [x] 10.7 `PayrollService.payslipData(payslipId)` — full itemized payslip header + earnings + deductions
  - [x] 10.8 `PayrollController` — index, listPeriods, storePeriod, create, store, show, compute, submit
  - [x] 10.9 `OwnerController` — payrollList, reviewForm, approve (generates payslips), returnRun
  - [x] 10.10 Views: `hr/payroll/index.php`, `hr/payroll/run.php`, `hr/payroll/detail.php`,
         `hr/payroll/periods.php`, `owner/payroll/index.php`, `owner/payroll/review.php`
  - [ ] 10.11 Guard against approved-run mutation (immutability check exists in computeRun; extend to all mutations)
  - [ ] 10.12 Tests: golden calculation, double-approval guard, mid-period transfer, 5s performance
  - _Requirements: 10_

- [x] 11. Reports Management module
  - [x] 11.1 `ReportsController.index` — summary stats + approved payroll table (REQ065)
  - [x] 11.2 `ReportsController.export` — print-ready HTML for all six report types (REQ066–REQ073):
        - Payroll Summary (branch/period/gross/net)
        - Attendance Report (per employee, date range)
        - Leave/Request Report
        - Government Contributions Report (SSS/PhilHealth/Pag-IBIG per employee per period)
        - 13th Month Pay Report (delegates to `PayrollService.compute13thMonth`)
        - Employee List
  - [x] 11.3 Views: `hr/reports/index.php` with quick-links to all six report types
  - [ ] 11.4 PDF/CSV export and printable BDO deposit-slip preparation list (REQ073)
  - [ ] 11.5 Performance test: generation within 5s (REQN006)
  - _Requirements: 11_

- [x] 12. Dashboard module
  - [x] 12.1 `DashboardController.hrDashboard` — employee/request/attendance/payroll counts + recent runs (REQ003)
  - [x] 12.2 `DashboardController.ownerDashboard` — pending approvals, branch count, recent approved runs
  - [x] 12.3 `DashboardController.empDashboard` — own name/branch/schedule/leave balance/requests
  - [x] 12.4 Views: `hr/dashboard.php`, `owner/dashboard.php`, `employee/dashboard.php`
  - [ ] 12.5 Attendance-alert widget sourced from `attendance WHERE status = 'Incomplete'`
  - _Requirements: 2_

- [x] 13. Employee Self-Service Portal
  - [x] 13.1 Employee dashboard on login — name, branch, schedule, pending requests, leave balance (REQ074)
  - [x] 13.2 Own-attendance view scoped to `session.employee_id` (REQ075)
  - [x] 13.3 Request submission/tracking reusing direct DB insert; full `RequestService` deferred (REQ076–REQ080)
  - [x] 13.4 Payslip list (`employee/payslips`) and detail (`employee/payslip`) with itemized earnings/deductions (REQ081)
  - [x] 13.5 `payslipPrint` — print-ready HTML payslip at `/employee/payslips/{id}/print` (REQ082)
  - [x] 13.6 `EmployeeScope::assertOwnRecord` — ownership guard preventing cross-employee access (REQ075, REQ081)
  - _Requirements: 12_

- [ ] 14. Cross-cutting: audit log, non-functional verification
  - [x] 14.1 `MM/DD/YY` date and 24-hour time formatting via `Formatter` (REQN013, REQN014)
  - [ ] 14.2 Wire `audit_logs` into all create/update/approve/archive actions (partially wired in AuthService/UserService)
  - [ ] 14.3 1-second data-retrieval performance check on key list endpoints (REQN003)
  - [ ] 14.4 Cross-browser check: Chrome, Firefox, Edge (REQN002)
  - [ ] 14.5 Full RBAC regression pass against the access matrix in `design.md`
  - _Requirements: all non-functional requirements_

- [ ] 15. End-to-end integration pass
  - [ ] 15.1 Walk every user story in `requirements.md` end-to-end and record pass/fail
  - [ ] 15.2 Fix defects found during walkthrough before marking spec complete
  - [x] 15.3 Demo data seeded: roles, 3 branches, 2 devices, 3 demo users, 1 demo employee,
         contribution fixture — sufficient for full HR → Owner → Employee workflow
  - _Requirements: all_

---

## Remaining gaps (deferred from P0)

| Gap | Blocked by |
|---|---|
| `transferEmployee` transaction (branch handover) | Task 4.4 |
| Employee lifecycle archive/rehire + employee documents | Task 4.6 |
| Holiday calendar CRUD | Task 5.4 |
| Manual attendance adjustment UI (REQ023) | Task 6.8 |
| Parser edge-case tests | Task 6.9 |
| `RequestService` with leave entitlement ledger (REQ078) | Task 7.4 |
| Cash advance obligation on approval | Task 7.5 |
| `SalaryService` with history preservation | Task 8.3 |
| Contribution golden tests | Task 9.4 |
| Payroll immutability guard on all mutations | Task 10.11 |
| Payroll golden tests + performance | Task 10.12 |
| PDF/CSV export + BDO deposit-slip list (REQ073) | Task 11.4 |
| Audit log wiring for all modules | Task 14.2 |
| RBAC + performance + cross-browser regression | Tasks 14.3–14.5 |
| End-to-end walkthrough | Task 15.1–15.2 |
