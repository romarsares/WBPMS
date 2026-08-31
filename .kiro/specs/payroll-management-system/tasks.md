# Implementation Plan

> **Two-developer P0 assignment:** See the canonical
> [task distribution](../../../docs/two-developer-task-distribution.md) for
> active owners, exact task IDs, dependencies, handoffs, and deferred work.

- [x] 1. Project and database foundation
  - [x] 1.0 **Development approval gate:** [ADR-0001](../../../docs/adr/0001-development-baseline.md) records the accepted MVP
        schema, configurable branch/site/device topology, employee fields,
        payroll cardinalities, `.xls` import contract, demo calculation rules,
        and technical choices. Production branch display names and complete
        statutory policies remain deployment gates, not migration blockers.
  - [x] 1.0a **Schema integrity gate:** [ADR-0002](../../../docs/adr/0002-schema-integrity-corrections.md) and the [canonical v1.1 capstone addendum](../../../docs/capstone_files/Canonical-Database-Schema-v1.1.md) resolve the critical/high schema findings.
  - [x] 1.0b **PHP/parser architecture gate:** [ADR-0003](../../../docs/adr/0003-frameworkless-php-and-xls-parser.md) supersedes the Node.js baseline with frameworkless PHP 8.5, Composer, PDO, Phinx, PHPUnit/PHPStan, and the strict PhpSpreadsheet-backed parser boundary.
  - [x] 1.1 Scaffold the frameworkless PHP/Composer project structure per `.kiro/steering/structure.md`
  - [x] 1.2 Write Phinx migrations from the v1.1 addendum
  - [x] 1.3 Seed roles/request types, demo contribution fixture, branches/sites/devices
  - [x] 1.4 Configure PDO MySQL connection factory, repository transaction boundary, environment validation
  - [ ] 1.5 Add migration/integration tests for every ADR-0002 uniqueness, calendar, lifecycle, temporal-overlap, and reconciliation invariant
  - _Requirements: foundation for all_

- [x] 2. Authentication and RBAC
  - [x] 2.1 Implement `AuthController.login` with password hashing/verification (REQ001, REQN011)
  - [ ] 2.2 Implement account-email password recovery with hashed, expiring, single-use OTP challenges; verify OTP, set new password, redirect to login (REQ002)
  - [x] 2.3 Implement `AuthMiddleware` enforcing the role matrix in `design.md` (REQN007)
  - [x] 2.4 Write unit tests for login success/failure and RBAC denial paths
  - _Requirements: 1_

- [x] 3. User Management module
  - [ ] 3.1 `UserService`: list/create/update/activate-deactivate/archive (REQ004–REQ008) — **service pending**
  - [x] 3.2 `UserController` + views: user table with stat cards and live DB query — **`UserManagementController` + `users/index.php` implemented**
  - [ ] 3.3 Integration test: create → update role → deactivate → archive lifecycle
  - _Requirements: 3_

- [x] 4. Employee Management module
  - [ ] 4.1 `EmployeeService`: list with effective branch/search/date filters, create, update, archive (REQ009–REQ017) — **service pending**
  - [ ] 4.2 Implement `employee_branch_assignment` with a no-overlap constraint and `transferEmployee` transaction — **pending**
  - [x] 4.3 `EmployeeController` + views: employee list with stat cards, branch, daily rate, and status — **`EmployeeController` + `employees/index.php` implemented**
  - [ ] 4.4 Integration tests: create → branch filter → permanent transfer → historical lookup → archive
  - _Requirements: 4_

- [x] 5. Work Schedule module
  - [ ] 5.1 `ScheduleService`: assign, update, archive, calendar view, holiday CRUD (REQ025–REQ031) — **service pending**
  - [x] 5.2 `ScheduleController` + views: schedule list + holiday calendar — **`ScheduleController` + `schedule/index.php` implemented**
  - [ ] 5.3 Unit test: schedule validity-period handling (overlapping periods rejected/handled)
  - _Requirements: 5_

- [x] 6. Attendance Management module (`.xls` daily-log upload → timesheet)
  - [ ] 6.1 Implement configurable `attendance_site`, `biometric_device`, effective `biometric_device_branch` coverage, and `employee_biometric_enrollment` master-data services/UI
  - [ ] 6.2 Implement device/year/month-aware `.xls` upload boundary with extension plus OLE signature checks, SHA-256, randomized non-public temporary storage, cleanup, one-sheet/formula validation, and configurable 10 MiB/5,000-row/date-column/token resource limits
  - [x] 6.3 Install/pin `phpoffice/phpspreadsheet`; implement pure `AttendanceFileParser` and `LdeXlsDailyLogParser` (`LDE_XLS_DAILY_LOG_V1`)
  - [ ] 6.4 Implement device+enrollment-code+punch-time matching against effective enrollments and branch assignments
  - [ ] 6.5 Detect duplicate files by SHA-256 and duplicate punches by device+code+local timestamp (REQ024)
  - [x] 6.6 `AttendanceImportService` and `AttendanceImportGateway` contract scaffolded; PDO gateway pending
  - [x] 6.7 `TimesheetGenerator` implemented
  - [x] 6.8 `WorkSchedule` domain class and `computeHours`/`flagIncomplete` implemented
  - [ ] 6.9 Implement manual adjustment and unmatched/coverage-exception reconciliation with audit evidence (REQ023)
  - [x] 6.10 `AttendanceController` + views: timesheet list with stat cards, late/OT highlighting, import link — **`AttendanceController` + `attendance/index.php` implemented**
  - [ ] 6.11 Full parser/import test matrix
  - _Requirements: 6_

- [x] 7. Request Management module (Leave, Overtime, Cash Advance)
  - [ ] 7.1 `RequestService.submit` with type-specific payload validation (REQ076, REQ077, REQ079, REQ080) — **service pending**
  - [ ] 7.2 Implement annual leave entitlements plus append-only ledger
  - [ ] 7.3 Update/cancel logic restricted to `Pending` status
  - [ ] 7.4 HR review flow: approve/reject with status update visible to employee
  - [ ] 7.5 List/sort/filter/search/archive for HR queues (REQ032–REQ046)
  - [x] 7.6 `RequestController` + views: requests list with stat cards and status badges — **`RequestsController` + `requests/index.php` implemented**
  - [ ] 7.7 Integration test: submit → insufficient-balance rejection → valid submission → approve → archive
  - _Requirements: 7, 12_

- [x] 8. Manage Salary module
  - [ ] 8.1 `SalaryService`: current-rate lookup, create structure, update rate (insert new row, preserve history), archive (REQ053–REQ057) — **service pending**
  - [x] 8.2 `SalaryController` + views: salary list with avg/max rate stats — **`SalaryController` + `salary/index.php` implemented**
  - [ ] 8.3 Unit test: updating a rate does not mutate history rows
  - _Requirements: 8_

- [x] 9. Benefits and Deductions module
  - [x] 9.0 ADR-0001 resolution: do not migrate the orphan `benefit` table;
        government employee shares are deductions with auditable `contribution_record` rows
  - [ ] 9.1 Implement Approved/effective `contribution_policy_version` records and the exact ADR-0001 demo fixture — **pending**
  - [ ] 9.2 Implement EEMR, last-Friday scheduling, unique payroll/program contribution rows
  - [ ] 9.3 Contribution record CRUD + lock/unlock (REQ058–REQ062)
  - [x] 9.4 `ContributionController` + views: policy versions + contribution records — **`BenefitsController` + `benefits/index.php` implemented**
  - [ ] 9.5 Golden tests for the documented ₱460 daily-rate example, last-Friday behavior, rounding
  - _Requirements: 9_

- [x] 10. Payroll Processing module
  - [x] 10.1 Payroll run list view with stat cards — **`PayrollController` + `payroll/index.php` implemented**
  - [ ] 10.2 `PayrollService.generatePayrollRun`: integrate attendance, approved requests, salary, and contributions (REQ047)
  - [ ] 10.3 Enforce unique period+branch and period+employee membership
  - [ ] 10.4 Digital payslip generation and `compute13thMonthPay` (REQ048, REQ049)
  - [ ] 10.5 `payroll_run.status` state machine: `Draft` → `Computed` → `PendingOwnerApproval` → `Approved`/`Returned` (REQ050, REQ051)
  - [x] 10.6 `PayrollController` list view with status badges, gross/net totals, return-reason display — **implemented**
  - [ ] 10.7 Guard against double approval and edits after `Approved`
  - [ ] 10.8 Tests: golden calculation, approval lifecycle
  - [ ] 10.9 Performance test: each branch run completes within 5s (REQN005)
  - _Requirements: 10_

- [x] 11. Reports Management module
  - [ ] 11.1 `ReportService.generate` for all report types (REQ065–REQ069) — **service pending**
  - [ ] 11.2 Print output for payroll summary, employee payslip, transaction slip (REQ070–REQ072)
  - [ ] 11.3 PDF/CSV export and BDO deposit-slip preparation list (REQ073)
  - [x] 11.4 `ReportController` + views: summary stats, report-type cards, approved payroll table — **`ReportsController` + `reports/index.php` implemented**
  - [ ] 11.5 Performance test: report generation within 5s (REQN006)
  - _Requirements: 11_

- [x] 12. Dashboard module
  - [x] 12.1 Role-based dashboard queries: live DB stats per role — **`DashboardController` with live PDO queries implemented**
  - [ ] 12.2 Attendance-alert widget sourced from `AttendanceService.flagIncomplete`
  - [x] 12.3 `DashboardController` + views for all three roles — **`dashboard/hrhead.php`, `businessowner.php`, `employee.php` implemented**
  - _Requirements: 2_

- [x] 13. Employee Self-Service Portal
  - [x] 13.1 Employee dashboard on login (REQ074) — **`dashboard/employee.php` implemented**
  - [x] 13.2 Own-attendance view, scoped strictly to `session.employee_id` (REQ075) — **`EmployeePortalController::myAttendance` + `employee/attendance.php` implemented**
  - [x] 13.3 Request tracking UI (REQ076, REQ077, REQ079, REQ080) — **`EmployeePortalController::myRequests` + `employee/requests.php` implemented**
  - [x] 13.4 Payslip list view (REQ081, REQ082) — **`EmployeePortalController::myPayslips` + `employee/payslips.php` implemented**
  - [x] 13.5 Access-control: all employee portal queries bound to `session.employee_id` — **no employee_id in URL; queries use session identity only**
  - _Requirements: 12_

- [ ] 14. Cross-cutting: localization, audit log, non-functional verification
  - [x] 14.1 Apply `MM/DD/YY` date formatting and 24-hour time formatting globally (REQN013, REQN014) — **`Formatter::date()` and `Formatter::time()` applied across all views**
  - [ ] 14.2 Wire `audit_logs` into all create/update/approve/archive actions and unauthenticated failures
  - [ ] 14.3 Verify data-retrieval performance target (1s) on key list endpoints (REQN003)
  - [ ] 14.4 Cross-browser check: Chrome, Firefox, Edge (REQN002)
  - [ ] 14.5 Full RBAC regression pass against the access matrix in `design.md`
  - _Requirements: all non-functional requirements_

- [x] 15. End-to-end integration pass
  - [x] 15.1 Wire all controllers into the app router with role-guarded routes — **`routes/web.php` updated; all 12 stub routes replaced with real controllers**
  - [x] 15.2 Seed configurable sample data — **all demo seeders implemented**
  - [ ] 15.3 Walk every user story in `requirements.md` end-to-end and record pass/fail
  - [ ] 15.4 Fix defects found during the walkthrough before marking the spec complete
  - _Requirements: all_
