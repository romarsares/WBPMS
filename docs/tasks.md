# Implementation Plan

- [ ] 1. Project and database foundation
  - [ ] 1.0 **Approval gate — do not skip:** work through the "Capstone
        update checklist" in `database-documentation-revision-log.md`
        with the business owner/HR subject-matter owner. In particular:
        confirm official branch names and employee distribution (observed
        counts are seed data, not limits — see `product.md`), approve the canonical `employee` attribute set,
        approve payroll header/detail cardinalities, and decide the
        `benefit` table's relationship (it has no FK to anything in any
        source — see `design.md` Data Models). Do not proceed to 1.2
        until these are checked off; `design.md`'s current schema is a
        **draft** built from audit recommendations, not an approved one.
  - [ ] 1.1 Scaffold Node.js/TypeScript project structure per `structure.md` (`src/{models,controllers,services,middleware,routes}`, `migrations/`, `seeders/`)
  - [ ] 1.2 Once 1.0 is approved, write Sequelize migrations implementing every table in `design.md` Data Models (post-approval version), with FKs and indexes on lookup columns (`employee_id`, `date`, `status`); resolve every table still marked "pending approval" or "conflict not yet resolved" in `design.md` before writing its migration
  - [ ] 1.3 Seed reference data: `role`, `request_type`, `sss_bracket`, `philhealth_rate`, `pagibig_rate`, and confirmed initial branches/sites/devices without hardcoding their counts into application rules
  - [ ] 1.4 Configure DB connection (`config/database.ts`) and base Sequelize model setup
  - _Requirements: foundation for all_

- [ ] 2. Authentication and RBAC
  - [ ] 2.1 Implement `AuthController.login` with password hashing/verification (REQ001, REQN011)
  - [ ] 2.2 Implement password recovery: request OTP, verify OTP, set new password, redirect to login (REQ002)
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

- [ ] 6. Attendance Management module (`.dat` file upload → timesheet)
  - [ ] 6.1 Implement configurable `attendance_site`, `biometric_device`, effective `biometric_device_branch` coverage, and `employee_biometric_enrollment` master-data services/UI
  - [ ] 6.2 Implement device-aware `.dat` upload endpoint (multipart upload, active device/format validation) rejecting unrecognized formats outright
  - [ ] 6.3 Implement pure `parseDatFile` to extract device employee code, timestamp, punch type, transaction ID when present, and raw/source-line evidence
  - [ ] 6.4 Implement device+code+punch-time matching against effective enrollments and branch assignments; retain unmatched and out-of-coverage punches in `biometric_punch`
  - [ ] 6.5 Detect duplicate files by checksum and duplicate punches by device transaction ID or device+code+timestamp+type (REQ024)
  - [ ] 6.6 Wrap parse → match → stage → generate in a transaction recorded as a device-aware `attendance_import_batch`; return totals grouped by resolved branch
  - [ ] 6.7 Implement `generateTimesheet` and preserve the effective branch assignment on attendance
  - [ ] 6.8 Implement `computeHours` and `flagIncomplete` against the effective schedule
  - [ ] 6.9 Implement manual adjustment and unmatched/coverage-exception reconciliation with audit evidence (REQ023)
  - [ ] 6.10 `AttendanceController` + views: choose device (branch is derived), upload once, view grouped branch summary and employee timesheets
  - [ ] 6.11 Tests: shared device serving multiple branches, late historical upload after transfer, old-device punch after transfer, duplicate transaction/file, unmatched enrollment, out-of-coverage branch, and calculation edge cases
  - _Requirements: 6_

- [ ] 7. Request Management module (Leave, Overtime, Cash Advance)
  - [ ] 7.1 `RequestService.submit` with type-specific payload validation (REQ076, REQ077, REQ079, REQ080)
  - [ ] 7.2 Leave-balance check blocking submission when insufficient (REQ078)
  - [ ] 7.3 Update/cancel logic restricted to `Pending` status
  - [ ] 7.4 HR review flow: approve/reject with status update visible to employee (REQ033, REQ038, REQ043)
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
  - [ ] 9.0 Resolve the `benefit` table conflict flagged in `design.md`
        (no FK to `employee` or `payroll` in any source representation)
        before building this module — decide whether benefits become
        `payroll.total_benefits`-only, or a proper `payroll_id`-keyed
        child table like `deduction`/`payroll_earnings`
  - [ ] 9.1 `ContributionEngine.computeSSS` against `sss_bracket` table, including bracket-boundary tests
  - [ ] 9.2 `ContributionEngine.computePhilHealth` / `computePagIbig` against current rate tables
  - [ ] 9.3 Contribution record CRUD + lock/unlock (REQ058–REQ062)
  - [ ] 9.4 `ContributionController` + views
  - [ ] 9.5 Unit tests for each contribution calculation at bracket edges and typical salaries
  - _Requirements: 9_

- [ ] 10. Payroll Processing module
  - [ ] 10.1 Implement `payroll_period` and branch-scoped `payroll_run`; HR UI selects period+branch and previews eligible employees before generation
  - [ ] 10.2 `PayrollService.generatePayrollRun`: integrate attendance, approved requests, salary, and contributions into gross/net pay for employees assigned to the branch at period start (REQ047)
  - [ ] 10.3 Enforce unique period+branch and period+employee membership to prevent duplicate branch runs or double pay
  - [ ] 10.4 Digital payslip generation in company format and `compute13thMonthPay` (REQ048, REQ049)
  - [ ] 10.5 Branch-run approval state machine: Draft → Computed → Pending Owner Approval → Approved/Returned (REQ050, REQ051)
  - [ ] 10.6 `PayrollController` (HR: preview/generate/submit by branch) and Owner review actions (approve/return with note)
  - [ ] 10.7 Guard against double approval and edits after `Approved`
  - [ ] 10.8 Tests: golden calculation, branch generation, mid-period permanent transfer remains wholly in cutoff-start branch, next period uses destination branch, and full approval lifecycle
  - [ ] 10.9 Performance test: each branch run completes within 5s for a declared roster volume (REQN005)
  - _Requirements: 10_

- [ ] 11. Reports Management module
  - [ ] 11.1 `ReportService.generate` for Payroll, Attendance, Request, Contributions, 13th-Month report types (REQ065–REQ069)
  - [ ] 11.2 Print output for payroll summary, employee payslip, transaction slip (REQ070–REQ072)
  - [ ] 11.3 Export to downloadable file (PDF/CSV, incl. bank transfer file format) (REQ073)
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
  - [ ] 14.2 Wire the `log` table into all create/update/approve/archive actions for audit trail
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