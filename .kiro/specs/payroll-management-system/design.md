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
- `transferEmployee(employeeId, destinationBranchId, effectiveDate, reason,
  transferredBy): EmployeeBranchAssignment` — closes the prior assignment,
  creates the new permanent assignment, and retains the employee/user identity.
- `archiveEmployee(id): void`
- Implements REQ009–REQ017.

### Branch and Biometric Device Services
- Branch master data, attendance sites, devices, and effective device-to-branch
  coverage are configurable; counts are not application constants.
- `enrollEmployee(employeeId, deviceId, deviceEmployeeCode,
  effectivePeriod): EmployeeBiometricEnrollment` rejects overlapping reuse of
  a device-scoped code.

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
- `matchEmployees(deviceId, rawPunches): {matched: RawPunch[], unmatched:
  RawPunch[], coverageExceptions: RawPunch[]}` — resolves device + employee
  code + punch time against effective enrollment and branch coverage.
- `generateTimesheet(matchedPunches): AttendanceRecord[]` — pairs
  consecutive in/out punches per employee per day into `attendance` rows,
  skipping or flagging punches that would duplicate an existing record
  (REQ024).
- `importFromDatFile(deviceId, fileBuffer, uploadedBy): ImportSummary` — loads
  device/site/format context and orchestrates
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
- `previewPayroll(payrollPeriodId, branchId): PayrollPreview`
- `generatePayrollRun(payrollPeriodId, branchId, generatedBy): PayrollRun` —
  creates one run per selected period+branch. Employee membership uses the
  assignment effective on period start and is unique across branch runs.
  1. Pull attendance summary per eligible employee (AttendanceService).
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

> **Status: draft canonical model — not yet approved.** This section
> now applies the recommendations in `database-schema.md` §6 (use
> Figure 163/164 as the operational base, union the employee fields,
> use header+child rows for payroll) instead of silently blending
> sources. However, the
> [revision log](database-documentation-revision-log.md)'s approval
> checklist is still unchecked — nothing here is authorized for a
> production migration until that checklist is signed off. Every table
> is labeled with its provenance:
> - **[Fig163/164]** — taken from the Logical/Physical Database Model
>   diagrams, treated as the richer operational source.
> - **[Table85/86]** — taken from the Final Relation / Data Dictionary.
> - **[canonical correction]** — a source typo/inconsistency fixed per
>   `database-schema.md` §1 and §5.
> - **[union decision — pending approval]** — fields merged from more
>   than one conflicting source representation.
> - **[extension]** — not in any source table; added to satisfy a
>   requirement (REQ ID cited) or a post-documentation feature change.

```
role(role_id PK, role_name)                                    -- [Fig163/164]

users(user_id PK, employee_id FK -> employee UNIQUE, username, password_hash,
      role_id FK, status)
      -- [Fig163/164] for shape; `password` renamed `password_hash` as an
      -- [extension] — REQN011 requires hashed storage, source just says
      -- "password" with no stated hashing.

branch(branch_id PK, branch_code UNIQUE, branch_name, location, status)

attendance_site(site_id PK, site_code UNIQUE, site_name, address, timezone, status)
biometric_device(device_id PK, site_id FK, device_code UNIQUE, device_name,
                 serial_number, file_format, timezone, status)
biometric_device_branch(device_branch_id PK, device_id FK, branch_id FK,
                        effective_from, effective_to, status)

address(address_id PK, street, barangay_id FK, city_id FK, province_id FK)  -- [Table85/86]
province(province_id PK, province_name)                        -- [Table85/86]
city(city_id PK, city_name)                                    -- [Table85/86, canonical correction: Table 85 called this `description`, Table 89 calls it `city_name`]
barangay(barangay_id PK, barangay_name)                         -- [Table85/86, canonical correction: sourced from mislabeled "Table 90"]
-- NOTE: no `city.province_id` / `barangay.city_id` hierarchy is added here.
-- The source only ever puts all three IDs directly on `address` — see
-- database-schema.md §4 "Relationships present only in other source
-- sections." A strict geography hierarchy is an available EXTENSION
-- (§7) but is intentionally NOT adopted by default. Decide explicitly
-- before adding those FKs.

employee(employee_id PK, employee_number UNIQUE, employee_type,
         first_name, middle_initial, last_name, email UNIQUE, contact_number,
         birthdate, hire_date, id_picture, address_id FK, status,
         philhealth_number, pagibig_number, tin_number,
         position)
         -- [union decision — pending approval]:
         --   employee_number, employee_type, email, hire_date,
         --     status, philhealth_number, pagibig_number, tin_number  ← [Fig163/164 only]
         --   middle_initial, id_picture, address_id                  ← [Table85/86 only]
         --   first_name, last_name, contact_number, birthdate        ← both sources agree
         --   position                                                ← [extension: required by
         --                                                              REQ009's "position" column,
         --                                                              present in neither source]
         -- Direct branch/device IDs are normalized into effective-dated
         -- assignment/enrollment tables for transfer history.
         -- daily_rate is intentionally NOT stored here — REQ009 lists it as
         -- a display column, but both sources place the rate on `salary`,
         -- keyed by effective_date; the employee list should join current
         -- salary rather than duplicate the rate on employee.

employee_branch_assignment(branch_assignment_id PK, employee_id FK, branch_id FK,
                           effective_from, effective_to, transfer_reason,
                           transferred_by FK -> users, created_at)
employee_biometric_enrollment(enrollment_id PK, employee_id FK, device_id FK,
                              device_employee_code, effective_from, effective_to,
                              status)

work_schedule(schedule_id PK, employee_id FK, working_days, rest_days,
              break_duration, work_start_time, work_end_time,
              effective_start_date, effective_end_date, status)  -- [Fig163/164]

holiday_calendar(holiday_id PK, holiday_date, description)      -- [extension: REQ030, no source table]

attendance(attendance_id PK, employee_id FK, branch_assignment_id FK,
           schedule_id FK, attendance_date,
           time_in, time_out, hours_worked, late_minutes, undertime_minutes,
           overtime_hours, status,
           source ENUM('dat_import','manual'), import_batch_id FK)
           -- shape is [Fig163/164]; `source` and `import_batch_id` are
           -- [extension: .dat file upload feature, post-documentation]

attendance_import_batch(import_batch_id PK, device_id FK, uploaded_by FK -> users,
                          file_name, file_checksum UNIQUE, uploaded_at, records_parsed,
                          records_matched, records_unmatched,
                          duplicates_skipped)                    -- [extension: .dat import feature]

biometric_punch(punch_id PK, import_batch_id FK, device_id FK,
                employee_id FK NULL, branch_assignment_id FK NULL,
                device_employee_code, punched_at, punch_type,
                device_transaction_id NULL, match_status, raw_record, source_line)

attendance_adjustment(adjustment_id PK, attendance_id FK, adjusted_by FK -> users,
                       adjustment_type, old_time_in, new_time_in,
                       old_time_out, new_time_out, reason, adjustment_date)
                       -- [Fig163/164] — richer than Table85's employee/date-keyed
                       -- version; adopted per §6 recommendation.

request_type(request_type_id PK, type_name)                     -- [Table85/86, canonical correction: `Requeest_type_id` typo]

request(request_id PK, employee_id FK, request_type_id FK, start_date, end_date,
        amount, reason, status, approved_by FK -> users, approved_date)
        -- [Fig163/164] — richer than Table85's request_date-only version;
        -- adopted per §6. A `Pending`/`Approved`/`Rejected` status enum is
        -- assumed; source lists `status` as a bare varchar.

salary(salary_id PK, employee_id FK, daily_rate, effective_date, status)  -- [Fig163/164]
        -- replaces Table85's simpler (salary_id, basic_salary) shape;
        -- effective_date + status is how salary history/"current rate"
        -- (REQ055–REQ057) is modeled, rather than a separate is_current flag.

deduction(deduction_id PK, payroll_id FK, deduction_type, description, amount)  -- [Fig163/164]
        -- NOTE: figure model keys deductions to `payroll_id` only, not
        -- `employee_id` (employee is reachable via payroll). Table85's
        -- version had no `payroll_id` at all. Adopted per §6 recommendation
        -- #4 (child rows keyed by payroll_id).

benefit(benefit_id PK, benefit_type, amount)                     -- [Table85/86 only]
        -- CONFLICT NOT YET RESOLVED: this table exists in Table 85/Table 95
        -- but has no equivalent in Figures 163–164 at all (database-schema.md
        -- §5 issue 3) — no FK to employee or payroll is defined in ANY
        -- source. Decide whether benefits become payroll-header fields
        -- (there's already a `payroll.total_benefits`, see below) or a
        -- proper child table before implementing REQ058/REQ063 government
        -- benefit tracking.

sss_bracket(bracket_id PK, salary_from, salary_to, employee_share, employer_share)  -- [extension: REQ061-062]
philhealth_rate(rate_id PK, rate_percent, effective_date)         -- [extension: REQ063-064]
pagibig_rate(rate_id PK, rate_percent, effective_date)            -- [extension: REQ063-064]

payroll_period(payroll_period_id PK, period_start, period_end, pay_date, status)
payroll_run(payroll_run_id PK, payroll_period_id FK, branch_id FK, status,
            total_salary, total_deductions, total_benefits, net_pay,
            computed_by FK -> users, submitted_by FK -> users,
            approved_by FK -> users, approved_at, return_reason)

payroll(payroll_id PK, payroll_run_id FK, payroll_period_id FK,
        employee_id FK, branch_assignment_id FK,
        total_salary, total_deductions, total_benefits, net_pay, status,
        approved_by FK -> users, approved_date, remarks)  -- [Fig163/164]
        -- replaces Table85/Table92's (salary_id, deduction_id) shape per
        -- §6 recommendation #4: payroll is a header row; earnings and
        -- deductions are child rows keyed by payroll_id.

payroll_earnings(earning_id PK, payroll_id FK, earning_type, description, amount)  -- [Fig163/164]
        -- NOTE: keyed by payroll_id only, no employee_id (matches
        -- deduction's shape above; employee reachable via payroll).
        -- Table85 misspelled this entity `PAYROLL_LEARNINGS` and keyed it
        -- by employee_id instead — corrected per §1/§5.

payslip(payslip_id PK, payroll_id FK, issue_date, file_path, generated_by FK -> users)  -- [Fig163/164]
        -- drops Table85/Table96's redundant employee_id (reachable via payroll_id)

cash_transaction(transaction_id PK, payroll_id FK, bank_id FK, amount,
                  transaction_date, reference_no, transfer_type, status)  -- [Fig163/164]
        -- NOTE: keyed to payroll_id + bank_id, NOT employee_id directly —
        -- differs from Table85's employee_id-keyed version. Adopted per §6.

bank_details(bank_id PK, employee_id FK, bank_name, account_name,
             account_number, account_type, created_at)  -- [Fig163/164]

cash_advance_history(history_id PK, employee_id FK, payroll_id FK, advance_date,
                      amount, deducted_amount, remaining_balance, status, remarks)
                      -- [Table85/97 shape, present in Fig163 but absent from Fig164]
                      -- Relationship to `request` (a cash-advance-type request):
                      -- an Approved cash-advance `request` is expected to create
                      -- one `cash_advance_history` row for repayment tracking.
                      -- This link is a DECISION, not stated by any source —
                      -- confirm with the approval checklist before building.

audit_logs(log_id PK, user_id FK -> users, action_performed, table_affected,
           record_id, action_date, description)  -- [Fig163/164]
        -- Table85 instead used (Log_in, employee_id, action, log_date) keyed
        -- to employee_id. Adopted the Fig163/164 version per §6 since not
        -- every audited action is employee-initiated (HR/Owner users too);
        -- `Log_in` corrected to `log_id` per §1.
```

### Key relationships
- `employee 1—N employee_branch_assignment N—1 branch`; exactly one assignment
  covers an employee/date and preserves permanent transfer history.
- `employee 1—1 users` (unique `employee_id` on `users`).
- `employee 1—N attendance`, `employee 1—N work_schedule` (history via
  `effective_start_date`/`effective_end_date`).
- `attendance_site 1—N biometric_device`; devices and branches have effective
  N—M coverage through `biometric_device_branch`.
- Employees and devices map through effective `employee_biometric_enrollment`;
  imports store immutable `biometric_punch` rows.
- `payroll_period 1—N payroll_run`, one per selected branch; each employee
  occurs once per period using the cutoff-start branch.
- `employee 1—N request`, `request N—1 request_type`.
- `payroll 1—N payroll_earnings`, `payroll 1—N deduction`, `payroll 1—1 payslip`,
  `payroll 1—N cash_transaction`.
- `salary` keeps history via `effective_date`/`status` rather than mutating
  rows in place (supports REQ055–REQ057).
- `attendance_adjustment` references the original `attendance` row —
  imported punch data is never overwritten, only annotated (audit-safe).
- `benefit` has **no** confirmed FK to any other table — flagged above as
  an unresolved conflict, not a relationship to build against yet.

## Error Handling

- **Validation errors** (missing/invalid fields): return field-level
  errors to the Presentation tier; never partially persist a record.
- **Authorization errors**: any action outside the RBAC table above
  returns `403` and is written to `audit_logs`.
- **Invalid `.dat` file**: rejected outright at upload time with no
  partial import — the file must be re-exported/re-uploaded.
- **Duplicate punches on re-import** (REQ024): the import routine skips
  device transaction or device+code+timestamp+punch-type duplicates
  rather than silently overwriting data.
- **Unmatched punches**: punches whose device employee identifier doesn't
  resolve to an effective enrollment remain as `biometric_punch` rows for HR
  reconciliation — never dropped and
  never auto-assigned to the wrong employee.
- **Coverage/transfer controls**: out-of-coverage punches are flagged; matching
  uses punch time while payroll branch membership uses period start.
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
  representative data volume (all company branches — see `product.md` for
  the unresolved branch-count note — and the full employee roster).
- **Manual/UAT scenarios** mirrored from the original documentation's
  screenshots (e.g., login, password reset, payroll submission/approval,
  employee payslip download) to confirm parity with the specified
  workflows.
