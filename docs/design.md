# Design Document

## Overview

This design implements the requirements in `requirements.md` as a
three-tier **Node.js (Express + TypeScript) / MySQL (Sequelize)** web
application. Business logic (attendance computation, payroll calculation,
contribution computation) lives in a Service layer between thin
Express controllers/routers and the database (via Sequelize models), so
calculation rules stay testable and independent of the UI.

## Architecture

```
┌─────────────────────────┐
│   Presentation Tier      │  Browser UI: role-specific views
│   HTML / CSS / JS        │  (Owner, HR Head, Employee)
└────────────┬─────────────┘
             │ HTTP requests (REST/JSON)
┌────────────▼─────────────┐
│   Application Tier        │  Express Routers → Controllers → Services
│   - AuthController         │
│   - PayrollService          │  Business rules, validation,
│   - AttendanceService       │  RBAC enforcement (middleware)
│   - ContributionEngine      │
│   - ReportService           │
└────────────┬─────────────┘
             │ Sequelize (ORM)
┌────────────▼─────────────┐
│   Database Tier            │  MySQL: normalized relational schema
└─────────────────────────┘
```

Requests flow Presentation → Application → Database and results (computed
salaries, reports, payslips) flow back the same path.

### Role-based access control (cross-cutting)

A single `AuthMiddleware` resolves the current session's `role_id` and
gates controller actions:

| Action | Business Owner | HR Head | Employee |
|---|---|---|---|
| View own dashboard | ✅ | ✅ | ✅ |
| Manage users | ✅ (grant) | ✅ | ❌ |
| Manage employees/attendance/schedules | ❌ | ✅ | ❌ (read-own only) |
| Approve/reject leave, overtime, cash advance | ❌ | ✅ | ❌ |
| Submit requests | ❌ | ❌ | ✅ |
| Compute/submit payroll | ❌ | ✅ | ❌ |
| Approve/return payroll | ✅ | ❌ | ❌ |
| View/generate reports | ✅ | ✅ | ❌ |
| View own payslip | ❌ | ❌ | ✅ |

## Components and Interfaces

### AuthController / AuthService
- `login(credentials): Session`
- `requestPasswordReset(email): void` → sends OTP
- `verifyOtpAndReset(email, otp, newPassword): void`
- Enforces REQ001, REQ002, REQN007, REQN011.

### UserService
- `listUsers(filters): User[]`
- `createUser(data): User`
- `updateUser(id, data): User`
- `setActiveStatus(id, active: bool): void`
- `archiveUser(id): void`
- Implements REQ004–REQ008.

### EmployeeService
- `listEmployees(filters: {branch?, search?, dateRange?}): Employee[]`
- `createEmployee(data): Employee`
- `updateEmployee(id, data): Employee`
- `archiveEmployee(id): void`
- Implements REQ009–REQ017.

### ScheduleService
- `assignSchedule(employeeId, schedule): WorkSchedule`
- `updateSchedule(id, data): WorkSchedule`
- `archiveSchedule(id): void`
- `getCalendarView(month, year): CalendarEntry[]`
- `getHolidays(year): Holiday[]`
- Implements REQ025–REQ031.

### AttendanceService
- `parseDatFile(fileBuffer): RawPunch[]` — parses the biometric device's
  exported `.dat` file into raw punch records (`deviceEmployeeId`,
  `timestamp`, `inOutFlag`). Rejects the file outright (no partial import)
  if the format is unrecognized.
- `matchEmployees(rawPunches): {matched: RawPunch[], unmatched: RawPunch[]}`
  — resolves each punch's device employee identifier against the
  `employee` table (e.g., via a `device_employee_id` mapping column).
- `generateTimesheet(matchedPunches): AttendanceRecord[]` — pairs
  consecutive in/out punches per employee per day into `attendance` rows,
  skipping or flagging punches that would duplicate an existing record
  (REQ024).
- `importFromDatFile(fileBuffer, uploadedBy): ImportSummary` — orchestrates
  the three steps above in a transaction and returns a summary (parsed /
  matched / unmatched / duplicates-skipped counts) for the HR Head to
  review; unmatched punches are persisted to an `unmatched_punch` table
  for manual reconciliation rather than discarded.
- `getAttendanceFor(employeeId, dateRange): AttendanceRecord[]`
- `computeHours(attendanceRecord, schedule): {hoursWorked, late, undertime, overtime}`
- `flagIncomplete(): AttendanceRecord[]` — timesheet entries missing
  time-in/out.
- `adjustEntry(id, newValues, reason, adjustedBy): void` — writes to
  `attendance_adjustment`, never overwrites the imported punch data.
- Implements REQ018–REQ024 (revised: file-upload-driven import rather than
  a live device connection).

**Computation rule (late/undertime/overtime)** — unchanged:
```
expected_in, expected_out  ← from employee's active WorkSchedule
late          = max(0, actual_time_in - expected_in)
undertime     = max(0, expected_out - actual_time_out)   # if left early
hours_worked  = actual_time_out - actual_time_in - unpaid_break
overtime      = max(0, hours_worked - schedule.standard_hours)
```

### RequestService
- `submit(employeeId, type, payload): Request` (type ∈ {leave, overtime,
  cash_advance})
- `checkLeaveBalance(employeeId): number` — blocks submission per REQ078
  (4 paid sick leaves/year default).
- `updateOrCancel(requestId, data)` — only while `status = Pending`.
- `review(requestId, decision: Approved|Rejected, reviewerId): void`
- `archive(requestId): void`
- `listByStatus/type/employee(filters): Request[]`
- Implements REQ032–REQ046, REQ076–REQ080.

### SalaryService
- `getCurrentRate(employeeId): SalaryStructure`
- `createStructure(data): SalaryStructure`
- `updateRate(employeeId, newRate): void` — writes new row, keeps old as
  history (never mutates in place).
- `archiveStructure(id): void`
- Implements REQ053–REQ057.

### ContributionEngine
- `computeSSS(monthlySalary): {employeeShare, employerShare}` — bracket
  lookup against `sss_bracket`.
- `computePhilHealth(monthlySalary): amount` — current policy rate.
- `computePagIbig(monthlySalary): amount` — current policy rate.
- `getContributionRecords(employeeId): ContributionRecord[]`
- `lockRecord(id): void` / `unlockRecord(id): void`
- Implements REQ058–REQ064.

### PayrollService
- `computePayroll(periodStart, periodEnd, branchId?): PayrollRun`
  1. Pull attendance summary per employee (AttendanceService).
  2. Pull approved requests affecting pay (overtime, cash advances) for
     the period (RequestService).
  3. Pull current salary structure (SalaryService).
  4. Compute gross pay, overtime pay, deductions (late/undertime, cash
     advance) and contributions (ContributionEngine).
  5. Persist `payroll`, `payroll_earnings`, generate `payslip` rows.
- `compute13thMonthPay(employeeId, year): amount` — total basic salary
  paid over the year ÷ 12.
- `submitForApproval(payrollRunId): void` — HR Head → status `Pending
  Owner Approval`.
- `ownerReview(payrollRunId, decision: Approved|Returned, note?): void`
- Implements REQ047–REQ051.

**Payroll approval state machine:**
```
Draft → Computed → Pending Owner Approval → Approved (final)
                                       └──→ Returned → Computed (revise)
```

### ReportService
- `generate(type: Payroll|Attendance|Request|Contributions|ThirteenthMonth, params): Report`
- `print(reportId): PrintableDocument`
- `export(reportId, format: pdf|csv): File`
- Implements REQ065–REQ073.

### EmployeePortalController
Thin controller reusing `AttendanceService`, `RequestService`, and
`PayrollService`, always scoped to `session.employee_id`. Implements
REQ074–REQ082.

## Data Models

Entities below are an **implementation-oriented reconciliation**, not a
literal copy of the capstone schema. The source representations conflict;
see the [database schema audit](database-schema.md) and
[database documentation revision record](database-documentation-revision-log.md)
for the decisions that still need approval. Names below are illustrative
`snake_case` MySQL tables and include explicit implementation extensions
where requirements have no source table.

```
role(role_id PK, role_name)

users(user_id PK, employee_id FK -> employee, username, password_hash, role_id FK, is_active, created_at)

address(address_id PK, street, barangay_id FK, city_id FK, province_id FK)
province(province_id PK, province_name)
city(city_id PK, city_name, province_id FK)
barangay(barangay_id PK, barangay_name, city_id FK)

branch(branch_id PK, branch_name, location)

employee(employee_id PK, first_name, middle_initial, last_name, birthdate,
         contact_no, id_picture, address_id FK, branch_id FK, position,
         daily_rate, device_employee_id, is_active)

work_schedule(schedule_id PK, employee_id FK, working_days, rest_days,
              work_start_time, work_end_time, valid_from, valid_to, is_active)

holiday_calendar(holiday_id PK, holiday_date, description)

attendance(attendance_id PK, employee_id FK, date, time_in, time_out,
           source ENUM('dat_import','manual'), import_batch_id FK)

attendance_import_batch(import_batch_id PK, uploaded_by FK -> users,
                          file_name, uploaded_at,
                          records_parsed, records_matched,
                          records_unmatched, duplicates_skipped)

unmatched_punch(unmatched_id PK, import_batch_id FK, device_employee_id,
                 timestamp, in_out_flag, resolved BOOLEAN, resolved_to_employee_id FK)

attendance_adjustment(adjustment_id PK, attendance_id FK, employee_id FK,
                       adjusted_time_in, adjusted_time_out, reason,
                       adjusted_by FK -> users, adjusted_at)

request_type(request_type_id PK, type_name)   -- Leave, Overtime, Cash Advance

request(request_id PK, employee_id FK, request_type_id FK, request_date,
        details JSON, status ENUM('Pending','Approved','Rejected'),
        reviewed_by FK -> users, reviewed_at)

salary(salary_id PK, employee_id FK, basic_salary, daily_rate,
       effective_date, is_current)

deduction(deduction_id PK, deduction_type, amount, employee_id FK, payroll_id FK)
benefit(benefit_id PK, benefit_type, amount, employee_id FK, payroll_id FK)

sss_bracket(bracket_id PK, salary_from, salary_to, employee_share, employer_share)
philhealth_rate(rate_id PK, rate_percent, effective_date)
pagibig_rate(rate_id PK, rate_percent, effective_date)

payroll(payroll_id PK, employee_id FK, period_start, period_end,
        gross_pay, total_deductions, net_pay,
        status ENUM('Draft','Computed','Pending Owner Approval','Approved','Returned'),
        submitted_by FK -> users, approved_by FK -> users, approved_at)

payroll_earnings(earning_id PK, payroll_id FK, employee_id FK,
                  earning_type ENUM('basic','overtime','13th_month'), amount)

payslip(payslip_id PK, employee_id FK, payroll_id FK, net_pay, issued_at, file_path)

cash_transaction(transaction_id PK, employee_id FK,
                  transaction_type ENUM('cash_advance','deduction'), amount, transaction_date)

bank_details(bank_id PK, employee_id FK, bank_name, account_number)

log(log_id PK, employee_id FK, user_id FK, action, log_date)  -- audit trail
```

### Key relationships
- `employee 1—N attendance`, `employee 1—N work_schedule` (history via
  `valid_from`/`valid_to`).
- `employee.device_employee_id` maps an employee to the identifier used
  inside the biometric device's `.dat` export; `.dat` parsing resolves
  against this column.
- `attendance_import_batch 1—N attendance` and `1—N unmatched_punch` — every
  `.dat` upload is tracked as a batch so HR can see what was imported and
  reconcile unmatched punches after the fact.
- `employee 1—N request`, `request N—1 request_type`.
- `payroll 1—N payroll_earnings`, `payroll 1—1 payslip`.
- `salary` keeps history: updating a rate inserts a new row and flips
  `is_current`, never mutates the old row (supports REQ056).
- `attendance_adjustment` references the original `attendance` row —
  imported punch data is never overwritten, only annotated (audit-safe).

## Error Handling

- **Validation errors** (missing/invalid fields): return field-level
  errors to the Presentation tier; never partially persist a record.
- **Authorization errors**: any action outside the RBAC table above
  returns `403` and is written to `log`.
- **Invalid `.dat` file**: rejected outright at upload time with no
  partial import — the file must be re-exported/re-uploaded.
- **Duplicate punches on re-import** (REQ024): the import routine skips
  or flags punches that would duplicate an existing `attendance` row for
  the same employee/date, and reports the count in the import summary
  rather than silently overwriting data.
- **Unmatched punches**: punches whose device employee identifier doesn't
  resolve to a known `employee.device_employee_id` are stored in
  `unmatched_punch` for HR to manually reconcile — never dropped and
  never auto-assigned to the wrong employee.
- **Insufficient leave balance** (REQ078): reject at submission time with
  the employee's current balance shown.
- **Payroll approval race**: `payroll.status` transitions are guarded by
  a check constraint / application-level lock so a run can't be approved
  twice or edited once `Approved`.
- **Report/print/export failures**: surface a retry-safe error; report
  generation is idempotent (safe to re-run for the same period).

## Testing Strategy

- **Unit tests** on Services with no DB dependency where possible:
  `AttendanceService.computeHours`, `ContributionEngine.computeSSS/
  PhilHealth/PagIbig`, `PayrollService.compute13thMonthPay` — these are
  pure calculation functions and should be tested against known
  input/output pairs (including edge cases: exact schedule match, missing
  punch, zero salary, bracket boundaries).
- **Integration tests** for each Controller against a seeded test
  database: login/RBAC enforcement, request submit→approve→archive
  lifecycle, payroll compute→submit→approve/return lifecycle.
- **Performance checks** against the non-functional targets (REQN003–006):
  dashboard load, payroll computation, and report generation timing under
  representative data volume (three branches, full employee roster).
- **Manual/UAT scenarios** mirrored from the original documentation's
  screenshots (e.g., login, password reset, payroll submission/approval,
  employee payslip download) to confirm parity with the specified
  workflows.
