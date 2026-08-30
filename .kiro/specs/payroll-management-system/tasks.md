# Implementation Plan

- [ ] 1. Project and database foundation
  - [ ] 1.0 **Approval gate — do not skip:** work through the "Capstone
        update checklist" in `database-documentation-revision-log.md`
        with the business owner/HR subject-matter owner. In particular:
        confirm official branch names and employee distribution (observed
        counts are seed data, not limits), approve the canonical `employee` attribute set,
        approve payroll header/detail cardinalities, and decide the
        `benefit` table's relationship (it has no FK to anything in any
        source — see `design.md` Data Models). Do not proceed to 1.2
        until these are checked off; `design.md`'s current schema is a
        **draft** built from audit recommendations, not an approved one.
  - [ ] 1.1 Scaffold Node.js/TypeScript project structure per `structure.md` (`src/{models,controllers,services,middleware,routes}`, `migrations/`, `seeders/`)
  - [ ] 1.2 Once 1.0 is approved, write Sequelize migrations implementing every table in `design.md` Data Models (post-approval version), with FKs and indexes on lookup columns (`employee_id`, `date`, `status`); resolve every table still marked "pending approval" or "conflict not yet resolved" in `design.md` before writing its migration
  - [ ] 1.3 Seed reference data and confirmed initial branches/sites/devices without hardcoding counts
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
  - [ ] 4.1 Employee CRUD/filtering plus effective `employee_branch_assignment`
  - [ ] 4.2 Permanent `transferEmployee` transaction with no overlaps and stable employee/user identity
  - [ ] 4.3 Employee views for current branch, transfer, and assignment history
  - [ ] 4.4 Integration test create → transfer → historical lookup → archive
  - _Requirements: 4_

- [ ] 5. Work Schedule module
  - [ ] 5.1 `ScheduleService`: assign, update, archive, calendar view, holiday CRUD (REQ025–REQ031)
  - [ ] 5.2 `ScheduleController` + calendar UI component
  - [ ] 5.3 Unit test: schedule validity-period handling (overlapping periods rejected/handled)
  - _Requirements: 5_

- [ ] 6. Attendance Management module (`.dat` file upload → timesheet)
  - [ ] 6.1 Configurable sites, devices, effective device-branch coverage, and employee enrollments
  - [ ] 6.2 Device-aware upload and pure `.dat` parser
  - [ ] 6.3 Match device+code+punch-time; retain raw unmatched/coverage exceptions
  - [ ] 6.4 Punch/file duplicate safeguards (REQ024)
  - [ ] 6.5 Transactional device-aware import with branch-grouped summary and timesheet generation
  - [ ] 6.6 Implement `computeHours` (late/undertime/overtime) against the employee's active `work_schedule`
  - [ ] 6.7 Implement `flagIncomplete` for timesheet entries missing time-in/out, surfaced to Dashboard alerts
  - [ ] 6.8 Implement manual adjustment flow writing to `attendance_adjustment` with reason + adjuster (REQ023)
  - [ ] 6.9 Build an "unmatched punches" reconciliation screen for HR to manually assign unmatched punches to employees
  - [ ] 6.10 `AttendanceController` + views: upload screen with import-summary feedback, employee-scoped timesheet table with computed columns
  - [ ] 6.11 Tests for shared-device branches, transfer/late upload, duplicates, unmatched/coverage exceptions, and calculations
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
  - [ ] 10.1 Implement payroll period and branch-run preview/generation using cutoff-start branch membership (REQ047)
  - [ ] 10.2 Digital payslip generation in company format (REQ048)
  - [ ] 10.3 `compute13thMonthPay` (REQ049)
  - [ ] 10.4 Approval state machine: Draft → Computed → Pending Owner Approval → Approved/Returned (REQ050, REQ051)
  - [ ] 10.5 `PayrollController` (HR: compute/submit) and Owner-facing review actions (approve/return with note)
  - [ ] 10.6 Guard against double-approval / edits after `Approved`
  - [ ] 10.7 Tests for golden calculation, unique period+branch/employee, mid-cutoff transfer ownership, and approval lifecycle
  - [ ] 10.8 Performance test: payroll computation completes within 5s for a full branch roster (REQN005)
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
  - [ ] 15.2 Seed a realistic demo dataset (per the approved branch count from task 1.0, sample employees, schedules, attendance, requests)
  - [ ] 15.3 Walk every user story in `requirements.md` end-to-end (login → module action → expected result) and record pass/fail
  - [ ] 15.4 Fix defects found during the walkthrough before marking the spec complete
  - _Requirements: all_
