# Implementation Plan

- [ ] 1. Project and database foundation
  - [x] 1.0 **Development approval gate:** [ADR-0001](adr/0001-development-baseline.md) records the accepted MVP
        schema, configurable branch/site/device topology, employee fields,
        payroll cardinalities, `.xls` import contract, demo calculation rules,
        and technical choices. Production branch display names and complete
        statutory policies remain deployment gates, not migration blockers.
  - [x] 1.0a **Schema integrity gate:** [ADR-0002](adr/0002-schema-integrity-corrections.md) and the [canonical v1.1 capstone addendum](capstone_files/Canonical-Database-Schema-v1.1.md) resolve the critical/high schema findings.
  - [ ] 1.1 Scaffold Node.js/TypeScript project structure per `.kiro/steering/structure.md` (`src/{models,controllers,services,middleware,routes}`, `migrations/`, `seeders/`)
  - [ ] 1.2 Write Sequelize migrations from the v1.1 addendum, including explicit types/nullability/defaults, FKs and delete actions, checks, unique keys, lookup indexes, sessions/reset/audit tables, attendance lineage, policy versions, and payroll snapshots
  - [ ] 1.3 Seed roles/request types, ADR-0001's labeled demo contribution fixture, and configurable demo branches/sites/devices without hardcoding counts into application rules
  - [ ] 1.4 Configure DB connection (`config/database.ts`) and base Sequelize model setup
  - [ ] 1.5 Add migration/integration tests for every ADR-0002 uniqueness, calendar, lifecycle, temporal-overlap, and reconciliation invariant
  - _Requirements: foundation for all_

- [ ] 2. Authentication and RBAC
  - [ ] 2.1 Implement `AuthController.login` with password hashing/verification (REQ001, REQN011)
  - [ ] 2.2 Implement account-email password recovery with hashed, expiring, single-use OTP challenges; verify OTP, set new password, redirect to login (REQ002)
  - [ ] 2.3 Implement `AuthMiddleware` enforcing the role matrix in `design.md` (REQN007)
  - [ ] 2.4 Write unit tests for login success/failure and RBAC denial paths
  - _Requirements: 1_

- [ ] 3. User Management module
  - [ ] 3.1 `UserService`: list/create/update/activate-deactivate/archive (REQ004–REQ008)
  - [ ] 3.2 `UserController` + views: user table, create/edit form, status toggle, archive action
  - [ ] 3.3 Integration test: create → update role → deactivate → archive lifecycle
  - _Requirements: 3_

- [ ] 4. Employee Management module
  - [ ] 4.1 `EmployeeService`: list with effective branch/search/date filters, create, update, archive (REQ009–REQ017)
  - [ ] 4.2 Implement `employee_branch_assignment` with a no-overlap constraint and `transferEmployee` transaction; keep one employee and at most one linked user while closing the old assignment and opening the permanent destination assignment
  - [ ] 4.3 `EmployeeController` + views: employee table, create/edit form, effective branch filter, transfer form/history, and selectable list endpoint
  - [ ] 4.4 Integration tests: create → branch filter → permanent transfer → historical lookup → archive; verify the same user/employee identity and one assignment per date
  - _Requirements: 4_

- [ ] 5. Work Schedule module
  - [ ] 5.1 `ScheduleService`: assign, update, archive, calendar view, holiday CRUD (REQ025–REQ031)
  - [ ] 5.2 `ScheduleController` + calendar UI component
  - [ ] 5.3 Unit test: schedule validity-period handling (overlapping periods rejected/handled)
  - _Requirements: 5_

- [ ] 6. Attendance Management module (`.xls` daily-log upload → timesheet)
  - [ ] 6.1 Implement configurable `attendance_site`, `biometric_device`, effective `biometric_device_branch` coverage, and `employee_biometric_enrollment` master-data services/UI
  - [ ] 6.2 Implement device/year/month-aware `.xls` upload endpoint with OLE/BIFF signature, header, weekday/date-column, and `HH:mm` token validation
  - [ ] 6.3 Implement pure `parseXlsDailyLog` to expand `Enroll ID` + date + every time token while retaining Dept/Name/raw cell/row/column evidence
  - [ ] 6.4 Implement device+enrollment-code+punch-time matching against effective enrollments and branch assignments; retain unmatched and out-of-coverage punches in `biometric_punch`
  - [ ] 6.5 Detect duplicate files by SHA-256 and duplicate punches by device+code+local timestamp (REQ024)
  - [ ] 6.6 Wrap parse → match → stage → generate in a transaction recorded as a device-aware `attendance_import_batch`; return branch-grouped matched/unmatched/duplicate/incomplete/multi-punch totals
  - [ ] 6.7 Implement `generateTimesheet`: enforce one employee/date row, link every raw punch through `attendance_punch`, keep one punch incomplete, use two as earliest/latest, and preserve/flag all punches when more than two
  - [ ] 6.8 Implement `computeHours` and `flagIncomplete` against the effective schedule
  - [ ] 6.9 Implement manual adjustment and unmatched/coverage-exception reconciliation with audit evidence (REQ023)
  - [ ] 6.10 `AttendanceController` + views: choose device (branch is derived), upload once, view grouped branch summary and employee timesheets
  - [ ] 6.11 Tests: shared device serving multiple branches, late historical upload after transfer, old-device punch after transfer, duplicate transaction/file, unmatched enrollment, out-of-coverage branch, and calculation edge cases
  - _Requirements: 6_

- [ ] 7. Request Management module (Leave, Overtime, Cash Advance)
  - [ ] 7.1 `RequestService.submit` with type-specific payload validation (REQ076, REQ077, REQ079, REQ080)
  - [ ] 7.2 Implement annual leave entitlements plus append-only ledger and block submission when the derived balance is insufficient (REQ078)
  - [ ] 7.3 Update/cancel logic restricted to `Pending` status
  - [ ] 7.4 HR review flow: approve/reject with status update visible to employee; approved cash advance creates one request-linked obligation
  - [ ] 7.5 List/sort/filter/search/archive for HR queues (REQ032–REQ046)
  - [ ] 7.6 `RequestController` (HR-facing) + `EmployeePortalController` request endpoints
  - [ ] 7.7 Integration test: submit → insufficient-balance rejection → valid submission → approve → archive
  - _Requirements: 7, 12_

- [ ] 8. Manage Salary module
  - [ ] 8.1 `SalaryService`: current-rate lookup, create structure, update rate (insert new row, preserve history), archive (REQ053–REQ057)
  - [ ] 8.2 `SalaryController` + views
  - [ ] 8.3 Unit test: updating a rate does not mutate history rows
  - _Requirements: 8_

- [ ] 9. Benefits and Deductions module
  - [x] 9.0 ADR-0001 resolution: do not migrate the orphan `benefit` table;
        government employee shares are deductions with auditable
        `contribution_record` rows
  - [ ] 9.1 Implement Approved/effective `contribution_policy_version` records and the exact ADR-0001 demo fixture; reject unsupported EEMR/policy combinations
  - [ ] 9.2 Implement EEMR, last-Friday scheduling, unique payroll/program contribution rows, exact deduction linkage, and employer-share recording
  - [ ] 9.3 Contribution record CRUD + lock/unlock (REQ058–REQ062)
  - [ ] 9.4 `ContributionController` + views
  - [ ] 9.5 Golden tests for the documented ₱460 daily-rate example, last-Friday behavior, rounding, and unsupported-policy rejection
  - _Requirements: 9_

- [ ] 10. Payroll Processing module
  - [ ] 10.1 Implement `payroll_period` and branch-scoped `payroll_run`; HR UI selects period+branch and previews eligible employees before generation
  - [ ] 10.2 `PayrollService.generatePayrollRun`: integrate attendance, approved requests, salary, and contributions; snapshot salary and itemized calculation inputs for employees assigned to the branch at period start (REQ047)
  - [ ] 10.3 Enforce unique period+branch and period+employee membership to prevent duplicate branch runs or double pay
  - [ ] 10.4 Digital payslip generation in company format and `compute13thMonthPay` (REQ048, REQ049)
  - [ ] 10.5 Make `payroll_run.status` the sole state authority: `Draft` → `Computed` → `PendingOwnerApproval` → `Approved`/`Returned` (REQ050, REQ051)
  - [ ] 10.6 `PayrollController` (HR: preview/generate/submit by branch) and Owner review actions (approve/return with note)
  - [ ] 10.7 Guard against double approval and edits after `Approved`
  - [ ] 10.8 Tests: golden calculation, branch generation, mid-period permanent transfer remains wholly in cutoff-start branch, next period uses destination branch, and full approval lifecycle
  - [ ] 10.9 Performance test: each branch run completes within 5s for a declared roster volume (REQN005)
  - _Requirements: 10_

- [ ] 11. Reports Management module
  - [ ] 11.1 `ReportService.generate` for Payroll, Attendance, Request, Contributions, 13th-Month report types (REQ065–REQ069)
  - [ ] 11.2 Print output for payroll summary, employee payslip, transaction slip (REQ070–REQ072)
  - [ ] 11.3 Generate PDF/CSV reports and the printable BDO deposit-slip preparation list; create one period-level cheque batch after all included runs are Approved; no bank-upload file/API (REQ073)
  - [ ] 11.4 `ReportController` + views with type/period selection
  - [ ] 11.5 Performance test: report generation within 5s (REQN006)
  - _Requirements: 11_

- [ ] 12. Dashboard module
  - [ ] 12.1 Role-based dashboard queries: HR Head (analytics, pending approvals, payroll summaries), Owner (payroll status, cross-branch summaries), Employee (own snapshot)
  - [ ] 12.2 Attendance-alert widget sourced from `AttendanceService.flagIncomplete`
  - [ ] 12.3 `DashboardController` + views; verify 5s load target (REQN004)
  - _Requirements: 2_

- [ ] 13. Employee Self-Service Portal
  - [ ] 13.1 Employee dashboard on login (REQ074)
  - [ ] 13.2 Own-attendance view, scoped strictly to `session.employee_id` (REQ075)
  - [ ] 13.3 Request submission/tracking UI reusing `RequestService` (REQ076, REQ077, REQ079, REQ080)
  - [ ] 13.4 Payslip detail view and download (REQ081, REQ082)
  - [ ] 13.5 Access-control test: employee A cannot view employee B's records
  - _Requirements: 12_

- [ ] 14. Cross-cutting: localization, audit log, non-functional verification
  - [ ] 14.1 Apply `MM/DD/YY` date formatting and 24-hour time formatting globally (REQN013, REQN014)
  - [ ] 14.2 Wire `audit_logs` into all create/update/approve/archive actions and unauthenticated failures; retain request ID, attempted identifier, IP, and user agent when no user exists
  - [ ] 14.3 Verify data-retrieval performance target (1s) on key list endpoints (REQN003)
  - [ ] 14.4 Cross-browser check: Chrome, Firefox, Edge (REQN002)
  - [ ] 14.5 Full RBAC regression pass against the access matrix in `design.md`
  - _Requirements: all non-functional requirements_

- [ ] 15. End-to-end integration pass
  - [ ] 15.1 Wire all controllers into the app router with role-guarded routes
  - [ ] 15.2 Seed configurable sample branches/sites/devices, including one shared device serving two branches, plus employees, a permanent transfer, schedules, attendance, and requests
  - [ ] 15.3 Walk every user story in `requirements.md` end-to-end (login → module action → expected result) and record pass/fail
  - [ ] 15.4 Fix defects found during the walkthrough before marking the spec complete
  - _Requirements: all_
