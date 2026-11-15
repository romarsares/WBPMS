# Design Document

## Overview

This design implements the requirements in `requirements.md` as a
three-tier **Node.js (Express + TypeScript) / MySQL (Sequelize)** web
application. Business logic (attendance computation, payroll calculation,
contribution computation) lives in a Service layer between thin
Express controllers/routers and the database (via Sequelize models), so
calculation rules stay testable and independent of the UI.

The operational rules introduced below are traced to the supplemental
[`HR follow-up answers`](../../../docs/capstone_files/Follow-up-Questions-with-Answers-from-HR-1.pdf)
and [`Final Defense Reviewer`](../../../docs/capstone_files/Final-Defense-Reviewer-1.pdf).
Where a database consequence is inferred rather than stated directly, it
is labeled as a canonical inference or extension.

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
- `requestPasswordReset(accountEmail): void` → stores a hashed, expiring OTP
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
  Overlapping assignments are rejected.
- `archiveEmployee(id): void`
- Implements REQ009–REQ017.

### Branch and Biometric Device Services
- `BranchService.create/update/archive` manages configurable branch master data.
- `BiometricDeviceService.register(data): BiometricDevice` registers a device
  at an attendance site with its file format and timezone.
- `configureBranchCoverage(deviceId, branchIds, effectivePeriod): void` supports
  one device serving several branches and one branch using several devices.
- `enrollEmployee(employeeId, deviceId, deviceEmployeeCode,
  effectivePeriod): EmployeeBiometricEnrollment` rejects overlapping reuse of
  the same code on the same device.

### ScheduleService
- `assignSchedule(employeeId, schedule): WorkSchedule`
- `updateSchedule(id, data): WorkSchedule`
- `archiveSchedule(id): void`
- `getCalendarView(month, year): CalendarEntry[]`
- `getHolidays(year): Holiday[]`
- Implements REQ025–REQ031.

### AttendanceService
- `parseXlsDailyLog(fileBuffer, sourceYear, sourceMonth): ParsedWorkbook` — validates the OLE/BIFF
  `.xls` workbook, required identity headers, `MM/DD ddd` date columns, and
  space-separated `HH:mm` tokens. It also verifies that every header month and
  weekday agrees with the selected source year/month. It expands every token into an immutable
  raw punch while retaining workbook row/column and raw-cell evidence.
- `matchEmployees(deviceId, rawPunches): {matched: RawPunch[], unmatched:
  RawPunch[], coverageExceptions: RawPunch[]}` — resolves source device +
  employee code + punch timestamp against effective biometric enrollments,
  then validates the employee's effective branch against device coverage.
- `generateTimesheet(matchedPunches): AttendanceRecord[]` — groups punches
  per employee/day: one is incomplete, two use earliest/latest, and more
  than two preserve every punch and create an HR-review flag. It never
  silently discards intermediate punches.
- `importFromXlsWorkbook(deviceId, sourceYear, sourceMonth, fileBuffer,
  uploadedBy): ImportSummary` — loads
  device/site/format context and orchestrates
  the three steps above in a transaction and returns a summary (parsed /
  matched / unmatched / duplicates-skipped / incomplete / multi-punch
  counts) for the HR Head to review. Unmatched punches remain in
  `biometric_punch` with `match_status = 'unmatched'` for manual
  reconciliation rather than being discarded.
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
expected_in, expected_out  ← from employee's effective WorkSchedule
late_minutes       = max(0, actual_time_in - expected_in)
late_amount        = late_minutes × PHP 1.00
undertime_minutes  = max(0, expected_out - actual_time_out)   # if left early
worked_minutes     = actual_time_out - actual_time_in - break_minutes
overtime_minutes   = max(0, worked_minutes - schedule.standard_minutes)
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
- Loads an Approved, effective `contribution_policy_version`; the MVP ships only the explicitly
  labeled ADR-0001 demo fixture and rejects unsupported EEMR/policy inputs
  instead of inventing bracket boundaries.
- `computeEemr(dailyRate): number` — computes the contribution basis as
  `(dailyRate × 313) ÷ 12`. The supplemental calculation sheet calls this
  the Estimated Equivalent Monthly Rate (EEMR) and uses it as Monthly Basic
  Salary; variable weekly earnings are not the SSS/PhilHealth basis.
- `computeSSS(eemr): {employeeShare, employerShare}` — bracket lookup
  against `sss_bracket`.
- `computePhilHealth(eemr): {employeeShare, employerShare}` — current
  effective policy rate.
- `computePagIbig(effectivePolicy): {employeeShare, employerShare}` —
  current effective fixed amount/rate/cap; the supplemental sheet does not
  establish EEMR as the Pag-IBIG basis.
- `isContributionDeductionRun(payDate): boolean` — true only when
  `payDate` is the last Friday of the month.
- `getContributionRecords(employeeId): ContributionRecord[]`
- `lockRecord(id): void` / `unlockRecord(id): void`
- Implements REQ058–REQ064.

### PayrollService
- `previewPayroll(payrollPeriodId, branchId): PayrollPreview` — shows eligible
  employees and estimated totals without persistence.
- `generatePayrollRun(payrollPeriodId, branchId, generatedBy): PayrollRun` —
  creates one run for the selected branch and period. Membership uses the
  branch assignment effective on the period start; an employee can occur in
  only one branch run for that period.
  1. Pull attendance summary per eligible employee (AttendanceService).
  2. Pull approved requests affecting pay (overtime, cash advances) for
     the period (RequestService).
  3. Pull current salary structure (SalaryService).
  4. Compute gross pay and the documented pay multipliers: overtime at
     125%, regular-holiday work at 200%, and special-holiday work at 130%.
     Do not compound holiday and overtime multipliers until HR confirms
     that separate rule.
  5. Compute deductions (late/undertime and cash advance). Add SSS,
     PhilHealth, and Pag-IBIG employee shares only when the pay date is the
     last Friday of the month; persist employer shares for reporting.
  6. Apply zero income-tax withholding only while the employee's projected
     annual taxable income is within the currently stated ₱250,000
     threshold. Keep the threshold configurable.
  7. Persist the selected salary/rate snapshot and itemized calculation inputs
     on `payroll`, `payroll_earnings`, `deduction`, and `contribution_record`,
     then generate the unique `payslip` row.
- `compute13thMonthPay(employeeId, year): amount` — total basic salary
  paid over the year ÷ 12.
- `submitForApproval(payrollRunId): void` — HR Head → stored status
  `PendingOwnerApproval` (display label “Pending Owner Approval”).
- `ownerReview(payrollRunId, decision: Approved|Returned, note?): void`
- Implements REQ047–REQ051.

**Weekly operating calendar:** the recurring pay period is Friday through
Thursday, with Saturday as the rest day and six scheduled working days. HR
exports and closes attendance on Thursday; the Owner reviews Friday morning;
approved funds and payslips are released Friday afternoon. The documented
first biometric period (Sunday through Thursday, five days) is migration
history, not a second calendar rule.

### DisbursementService
- `prepareDepositSlipList(payrollPeriodId): DepositSlipPreparationList` —
  printable rows containing employee name, personal BDO account, and exact
  net pay for manual transcription to bank slips.
- `recordWeeklyCheque(payrollPeriodId, chequeNumber, totalAmount): DisbursementBatch`
  — one pay-to-cash cheque across every Approved branch run in the weekly period.
- `recordDepositSlip(batchId, payrollId, bankDetailsId): DepositSlip` — one
  manually prepared deposit slip per employee.
- The current workflow is BDO-only and has no ATM payroll, bank API, or
  bank-upload file. REQ073 is therefore a preparation-list/report
  requirement, not electronic-transfer integration.

### AttendancePolicyService
- `evaluateDisciplinaryFlags(employeeId): AttendancePolicyFlag[]` — raises
  HR-review alerts for three consecutive lates, three tardiness memoranda,
  two weeks without reporting, or three consecutive unexcused/unnotified
  absences.
- The service never suspends or terminates an employee automatically; HR
  records the reviewed action and supporting reason in the audit log.

**Payroll approval state machine:**
```
Draft → Computed → PendingOwnerApproval → Approved (final)
                                      └──→ Returned → Computed (revise)
```

### ReportService
- `generate(type: Payroll|Attendance|Request|Contributions|ThirteenthMonth, params): Report`
- `print(reportId): PrintableDocument`
- `export(reportId, format: pdf|csv): File`
- `generateDepositSlipPreparationList(payrollRunId): PrintableDocument`
  — operational replacement for the previously assumed bank-transfer file.
- Implements REQ065–REQ073.

### EmployeePortalController
Thin controller reusing `AttendanceService`, `RequestService`, and
`PayrollService`, always scoped to `session.employee_id`. Implements
REQ074–REQ082.

## Data Models

> **Status: accepted development baseline, amended by ADR-0002.** ADR-0001
> approves the domain model and ADR-0002 supplies the integrity corrections
> required for migrations. The authoritative column types, nullability,
> defaults, keys, checks, and indexes are in
> [`Canonical-Database-Schema-v1.1.md`](../../../docs/capstone_files/Canonical-Database-Schema-v1.1.md).
> This model applies the recommendations
> in `database-schema.md` §6 (use
> Figure 163/164 as the operational base, union the employee fields,
> use header+child rows for payroll) instead of silently blending sources.
> Production master data and statutory certification remain outside this
> approval. Every table is labeled with its provenance:
> - **[Fig163/164]** — taken from the Logical/Physical Database Model
>   diagrams, treated as the richer operational source.
> - **[Table85/86]** — taken from the Final Relation / Data Dictionary.
> - **[canonical correction]** — a source typo/inconsistency fixed per
>   `database-schema.md` §1 and §5.
> - **[canonical baseline decision]** — a documented merge or extension
>   accepted for MVP development by ADR-0001.
> - **[extension]** — not in any source table; added to satisfy a
>   requirement (REQ ID cited) or a post-documentation feature change.

```
role(role_id PK, role_name UNIQUE)                             -- [Fig163/164]

users(user_id PK, employee_id FK -> employee NULL UNIQUE,
      username UNIQUE, account_email UNIQUE, password_hash, role_id FK, status)
      -- [Fig163/164] for shape; `password` renamed `password_hash` as an
      -- [extension] — REQN011 requires hashed storage, source just says
      -- "password" with no stated hashing.
      -- employee_id is nullable so the non-salaried Business Owner can be
      -- a role-bearing user without a fabricated employee/payroll record
      -- [canonical inference from Supplemental HR answer, p. 3].

password_reset_challenge(challenge_id PK, user_id FK, otp_hash, expires_at,
                         attempts_remaining, consumed_at NULL, created_at)
                         -- [extension: REQ002; OTP values are never stored raw]

sessions(session_id PK, expires_at, data)                       -- [extension: ADR-0001 MySQL-persisted sessions]

branch(branch_id PK, branch_code UNIQUE, branch_name, location, status)
                                                               -- [Fig163/164 + configurable master-data extension]

attendance_site(site_id PK, site_code UNIQUE, site_name, address, timezone,
                status)                                       -- [extension: physical attendance location]

biometric_device(device_id PK, site_id FK, device_code UNIQUE, device_name,
                 serial_number, file_format, timezone, status,
                 installed_at, retired_at)                     -- [extension: registered import source]

biometric_device_branch(device_branch_id PK, device_id FK, branch_id FK,
                        effective_from, effective_to, status)  -- [extension: effective N—M coverage]

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
         position, contract_review_date)
         -- [canonical baseline decision]:
         --   employee_number, employee_type, email, hire_date,
         --     status, philhealth_number, pagibig_number, tin_number  ← [Fig163/164 only]
         --   middle_initial, id_picture, address_id                  ← [Table85/86 only]
         --   first_name, last_name, contact_number, birthdate        ← both sources agree
         --   position                                                ← [extension: required by
         --                                                              REQ009's "position" column,
         --                                                              present in neither source]
         -- Fig163/164's direct branch_id is normalized into effective-dated
         -- employee_branch_assignment so transfers retain history.
         -- employee_type is constrained to Regular|Contractual;
         -- contractual employees have a six-month evaluation lifecycle
         -- [Supplemental HR answer, p. 2].
         -- daily_rate is intentionally NOT stored here — REQ009 lists it as
         -- a display column, but both sources place the rate on `salary`,
         -- keyed by effective_date; the employee list should join current
         -- salary rather than duplicate the rate on employee.

employment_contract_review(review_id PK, employee_id FK, review_due_date,
                           outcome ENUM('Regularized','Renewed','Separated'),
                           effective_date, next_review_date NULL,
                           reviewed_by FK -> users NULL, notes, created_at)
                           -- [extension: auditable contractual outcomes]

employee_branch_assignment(branch_assignment_id PK, employee_id FK, branch_id FK,
                           effective_from, effective_to, transfer_reason,
                           transferred_by FK -> users, created_at)
                           -- [extension: permanent effective-dated transfers]
                           -- exactly one assignment may cover an employee/date

employee_biometric_enrollment(enrollment_id PK, employee_id FK, device_id FK,
                              device_employee_code, effective_from, effective_to,
                              status)
                              -- [extension: device-scoped identity]; unique
                              -- device+code over non-overlapping periods

work_schedule(schedule_id PK, employee_id FK, working_days, rest_days,
              break_minutes, work_start_time, work_end_time, standard_minutes,
              effective_from, effective_to, status)              -- [Fig163/164 + ADR-0002]

holiday_calendar(holiday_id PK, holiday_date, description,
                 holiday_type ENUM('Regular','Special'), pay_multiplier,
                 status)
                 -- [extension: REQ030 plus supplemental formulas]
                 -- Regular=2.00; Special=1.30 when work is performed.

attendance(attendance_id PK, employee_id FK, branch_assignment_id FK,
           schedule_id FK, attendance_date,
           time_in NULL, time_out NULL, hours_worked_minutes, late_minutes,
           undertime_minutes, overtime_minutes, status,
           source ENUM('xls_import','manual'), import_batch_id FK NULL)
           -- shape is [Fig163/164]; `source` and `import_batch_id` are
           -- [extension: .xls workbook import feature, ADR-0001]
           -- unique employee+attendance_date defines the MVP daily grain.

attendance_import_batch(import_batch_id PK, device_id FK, uploaded_by FK -> users,
                        file_name, file_checksum UNIQUE, source_year,
                        source_month, parser_version, status, uploaded_at,
                        completed_at NULL, records_parsed, records_matched,
                        records_unmatched, duplicates_skipped, incomplete_days,
                        multi_punch_days)                       -- [extension: .xls import]

biometric_punch(punch_id PK, import_batch_id FK, device_id FK,
                employee_id FK NULL, branch_assignment_id FK NULL,
                device_employee_code, source_local_at, punched_at_utc,
                punch_type NULL,
                device_transaction_id NULL, match_status, raw_record,
                source_department, source_user_id, source_employee_name,
                source_workbook_row, source_date_column,
                resolved_by FK -> users NULL, resolved_at NULL)
                -- [extension: immutable raw punch/audit staging]
                -- ADR-0001's `.xls` source has neither punch type nor
                -- transaction ID; both remain nullable for future adapters.

attendance_punch(attendance_id PK/FK, punch_id PK/FK UNIQUE, evidence_role,
                 created_at)
                 -- [extension: exact raw-punch lineage for each timesheet row]

attendance_adjustment(adjustment_id PK, attendance_id FK, adjusted_by FK -> users,
                       adjustment_type, old_time_in, new_time_in,
                       old_time_out, new_time_out, reason, adjustment_date)
                       -- [Fig163/164] — richer than Table85's employee/date-keyed
                       -- version; adopted per §6 recommendation.

attendance_policy_flag(flag_id PK, employee_id FK, flag_type,
                       triggering_date, status, reviewed_by FK -> users,
                       reviewed_at, action_taken, notes)
                       -- [extension: supplemental HR attendance policy]

request_type(request_type_id PK, type_name UNIQUE, status)      -- [Table85/86 + ADR-0002]

request(request_id PK, employee_id FK, request_type_id FK, reason, status,
        submitted_at, reviewed_by FK -> users NULL, reviewed_at NULL,
        review_notes, archived_by FK -> users NULL, archived_at NULL)
        -- [Fig163/164 + ADR-0002]: decision status is separate from archive
        -- metadata; type-specific fields are normalized below.

leave_request_detail(request_id PK/FK, leave_type, start_date, end_date,
                     days_requested)
overtime_request_detail(request_id PK/FK, overtime_date, start_time, end_time,
                        requested_minutes)
cash_advance_request_detail(request_id PK/FK, amount)

leave_entitlement(entitlement_id PK, employee_id FK, leave_type, leave_year,
                  entitled_days)
leave_ledger(entry_id PK, entitlement_id FK, request_id FK NULL UNIQUE,
             entry_type, days_delta, recorded_by FK -> users NULL, notes,
             created_at)
             -- [extensions: REQ078; append-only balance authority]

salary(salary_id PK, employee_id FK, daily_rate, effective_from, effective_to,
       status, created_by FK -> users NULL)                    -- [Fig163/164 + ADR-0002]
       -- effective periods preserve history and select one authoritative rate.

-- `benefit(benefit_id, benefit_type, amount)` remains a literal
-- Table85/95 artifact but is excluded from the canonical model: it has no
-- FK and the supplemental DFD has no Benefits store. Government
-- contributions are deductions; any future bonus/benefit feature must be
-- separately specified rather than inferred from this orphan table.

payroll_policy_version(policy_id PK, policy_code, version, effective_from,
                       effective_to, eemr_days_per_year,
                       annual_tax_threshold, late_rate_per_minute,
                       rounding_mode, demo_only, status,
                       approved_by FK -> users NULL, approved_at NULL)

contribution_policy_version(contribution_policy_id PK, policy_code, version,
                            effective_from, effective_to, demo_only, status,
                            approved_by FK -> users NULL, approved_at NULL)

sss_bracket(bracket_id PK, contribution_policy_id FK, salary_from, salary_to,
            employee_share, employer_share, effective_from, effective_to)
philhealth_rate(rate_id PK, contribution_policy_id FK UNIQUE, rate_decimal,
                basis_floor, basis_ceiling)
pagibig_rate(rate_id PK, contribution_policy_id FK UNIQUE, rate_decimal,
             basis_ceiling, employee_fixed_amount, employer_fixed_amount)

contribution_record(contribution_id PK, payroll_id FK, deduction_id FK UNIQUE,
                    contribution_policy_id FK,
                    contribution_type ENUM('SSS','PhilHealth','PagIBIG'),
                    eemr_basis, employee_share, employer_share,
                    deduction_date, calculation_details, status,
                    locked_at, locked_by FK -> users NULL)
                    -- [extension: REQ058-064 + supplemental monthly cadence]
                    -- unique payroll+contribution_type prevents double posting.

payroll_period(payroll_period_id PK, period_start, period_end, pay_date, status)
               -- [extension: concrete cutoff shared by branch runs]
               -- Friday start, Thursday end, immediately-following-Friday pay.

payroll_run(payroll_run_id PK, payroll_period_id FK, branch_id FK,
            payroll_policy_id FK,
            status ENUM('Draft','Computed','PendingOwnerApproval','Approved','Returned'),
            gross_pay, total_deductions, net_pay,
            computed_by FK -> users, computed_at, submitted_by FK -> users,
            submitted_at, reviewed_by FK -> users, reviewed_at, return_reason,
            lock_version)
            -- [extension: branch transaction]; unique period+branch

payroll(payroll_id PK, payroll_run_id FK, payroll_period_id FK,
        employee_id FK, branch_assignment_id FK, salary_id FK,
        daily_rate_snapshot, gross_pay, total_deductions, net_pay)  -- [Fig163/164 + ADR-0002]
        -- replaces Table85/Table92's (salary_id, deduction_id) shape per
        -- §6 recommendation #4: payroll is a header row; earnings and
        -- deductions are child rows keyed by payroll_id. Unique
        -- payroll_period_id+employee_id prevents cross-branch double payment.
        -- payroll_run is the only approval/status authority. A composite FK
        -- guarantees payroll.payroll_period_id equals the parent run's period.

payroll_earnings(earning_id PK, payroll_id FK, earning_type, description,
                 source_attendance_id FK NULL, source_request_id FK NULL,
                 quantity, unit_rate, multiplier, amount, calculation_details)
                 -- [Fig163/164 + ADR-0002: auditable calculation snapshot]

deduction(deduction_id PK, payroll_id FK, deduction_type, description,
          source_attendance_id FK NULL, quantity, unit_rate, amount,
          calculation_details)
          -- Government shares post only on the last-Friday payroll.

payslip(payslip_id PK, payroll_id FK UNIQUE, issue_date, file_path,
        generated_by FK -> users NULL, generated_at, content_hash)  -- [Fig163/164 + ADR-0002]
        -- drops Table85/Table96's redundant employee_id (reachable via payroll_id)

bank_details(bank_id PK, employee_id FK, bank_name, account_name,
             account_number, account_type, effective_from, effective_to,
             status, created_at)  -- [Fig163/164 + ADR-0002]

disbursement_batch(batch_id PK, payroll_period_id FK UNIQUE,
                   bank_name, cheque_number, cheque_total, status,
                   prepared_by FK -> users, submitted_at)
                   -- [canonical extension from Supplemental HR answer,
                   -- pp. 2-3: one aggregate pay-to-cash cheque per week]

deposit_slip(slip_id PK, batch_id FK, payroll_id FK UNIQUE, bank_id FK,
             amount, status, prepared_at)
             -- [canonical extension: one manual BDO deposit slip per employee]

-- Fig163/164's `cash_transaction(payroll_id, bank_id, ...)` is retained in
-- the source audit but superseded here because it cannot represent one
-- cheque funding many employee deposit slips.

cash_advance_history(history_id PK, request_id FK UNIQUE, employee_id FK,
                     original_amount, remaining_balance, status, approved_at)
                      -- [Table85/97 lineage + ADR-0002 normalized obligation]
                      -- Relationship to `request` (a cash-advance-type request):
                      -- an Approved cash-advance `request` is expected to create
                      -- one obligation row for repayment tracking.

cash_advance_repayment(repayment_id PK, history_id FK, payroll_id FK,
                       deduction_id FK UNIQUE, amount, created_at)
                       -- one child row per weekly repayment

audit_logs(log_id PK, user_id FK -> users NULL, event_type, action_performed,
           table_affected, record_id, attempted_identifier, request_id,
           ip_address, user_agent, action_at, description)  -- [Fig163/164 + ADR-0002]
        -- Table85 instead used (Log_in, employee_id, action, log_date) keyed
        -- to employee_id. Adopted the Fig163/164 version per §6 since not
        -- every audited action is authenticated (for example failed login);
        -- `Log_in` corrected to `log_id` per §1.
```

### Key relationships
- `employee 1—N employee_branch_assignment N—1 branch`; exactly one assignment
  covers an employee/date. This history-preserving extension supersedes direct
  `employee.branch_id` for canonical implementation.
- `employee 1—0..1 users` (nullable unique `employee_id` on `users`); the
  Business Owner is a user without an employee row and is excluded from
  payroll.
- `employee 1—N attendance`, `employee 1—N work_schedule` (history via
  `effective_from`/`effective_to`).
- `attendance_site 1—N biometric_device`; `biometric_device N—M branch` via
  effective-dated `biometric_device_branch`, so counts are never hardcoded.
- `employee N—M biometric_device` through effective-dated
  `employee_biometric_enrollment`; matching uses device + code + punch time.
- `attendance_import_batch N—1 biometric_device` and `1—N biometric_punch`;
  `attendance N—M biometric_punch` through `attendance_punch`; generated
  attendance preserves the effective employee branch assignment and exact
  source evidence.
- `employee 1—N request`, `request N—1 request_type`, with exactly one matching
  leave/overtime/cash-advance detail row. Leave balance is derived from the
  append-only `leave_ledger`.
- `payroll_period 1—N payroll_run`, one per selected branch; each
  `payroll_run 1—N payroll`, and each employee occurs once per period based on
  the assignment effective at period start.
- `payroll 1—N payroll_earnings`, `payroll 1—N deduction`, `payroll 1—1 payslip`,
  and `payroll 1—N contribution_record`.
- `payroll_period 1—0..1 disbursement_batch 1—N deposit_slip`; each slip references one
  employee payroll row and that employee's BDO bank details.
- `salary` keeps history via `effective_from`/`effective_to` rather than mutating
  rows in place (supports REQ055–REQ057).
- `attendance_adjustment` references the original `attendance` row —
  imported punch data is never overwritten, only annotated (audit-safe).
- `employee 1—N attendance_policy_flag`; review metadata records HR's
  decision without automating suspension or termination.
- The orphan source `benefit` table has no canonical relationship and is
  not implemented; government contributions are `deduction` rows backed by
  auditable `contribution_record` calculations.
- `payroll_run.status` is the sole approval authority. Employee payroll rows
  snapshot their salary and calculation inputs but carry no approval state.
- Every effective-dated write locks its employee/device/policy parent and
  rejects overlaps using the half-open interval rules in ADR-0002.

## Error Handling

- **Validation errors** (missing/invalid fields): return field-level
  errors to the Presentation tier; never partially persist a record.
- **Authorization errors**: any action outside the RBAC table above
  returns `403` and is written to `audit_logs`.
- **Invalid `.xls` workbook**: rejected outright at upload time with no
  partial import when the signature, headers, date columns, or time tokens
  violate ADR-0001's workbook contract.
- **Duplicate punches on re-import** (REQ024): the import routine skips
  duplicate file checksums or device+code+local-timestamp records and reports the count
  rather than silently overwriting data.
- **Unmatched punches**: punches whose device employee identifier doesn't
  resolve to an effective `employee_biometric_enrollment` remain in
  `biometric_punch` for HR to reconcile — never dropped and
  never auto-assigned to the wrong employee.
- **Coverage exception**: a matched punch whose effective employee branch is
  outside device coverage is retained and flagged, not posted automatically.
- **Transfer boundary**: matching uses punch time; payroll membership uses the
  period start, preventing mid-period transfers from splitting/duplicating pay.
- **Duplicate branch run**: unique period+branch and period+employee safeguards
  prevent repeated generation and cross-branch double payment.
- **Insufficient leave balance** (REQ078): reject at submission time with
  the employee's current balance shown.
- **Payroll approval race**: `payroll_run.status` transitions are guarded by
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
  lifecycle, shared-device multi-branch import, permanent transfer with late
  historical upload, and branch payroll compute→submit→approve/return lifecycle.
- **Performance checks** against the non-functional targets (REQN003–006):
  dashboard load, payroll computation, and report generation timing under
  representative data volume (all configured company branches and the full
  declared employee roster).
- **Manual/UAT scenarios** mirrored from the original documentation's
  screenshots (e.g., login, password reset, payroll submission/approval,
  employee payslip download) to confirm parity with the specified
  workflows.
