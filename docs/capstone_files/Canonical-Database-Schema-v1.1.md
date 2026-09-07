# WBPMS Canonical Database Schema v1.1 — Capstone Addendum

## Document control

- **Status:** Approved implementation dictionary
- **Date:** 2026-08-31
- **Decision record:** [ADR-0002](../adr/0002-schema-integrity-corrections.md)
- **Source evidence:** `Capstone 1 Final Submission.docx.pdf`,
  `Follow-up-Questions-with-Answers-from-HR-1.pdf`, and
  `Final-Defense-Reviewer-1.pdf`

The PDFs in this directory are immutable evidence. This addendum applies the
accepted corrections without rewriting or obscuring the original source.

## Global relational conventions

- MySQL 8.4/InnoDB, `utf8mb4`, strict SQL mode.
- Identifiers are `BIGINT UNSIGNED AUTO_INCREMENT` unless a natural identifier
  is explicitly stated.
- Money is `DECIMAL(12,2)` for employee amounts and `DECIMAL(15,2)` for
  company aggregates. Values are non-negative unless a ledger delta explicitly
  permits a sign.
- Business dates use `DATE`; local wall-clock values use `DATETIME`; stored
  instants use UTC `DATETIME(0)` and are converted at the application boundary.
- Every mutable master/transaction table has `created_at` and `updated_at`
  timestamps. Append-only evidence and ledger tables have `created_at` only.
- Historical domain parents use `ON DELETE RESTRICT`. Optional actor/auditor
  references use `ON DELETE SET NULL`. The application archives records rather
  than hard-deleting them.
- Effective periods are half-open: `effective_from` is inclusive and
  `effective_to` is exclusive/NULL. A check requires
  `effective_to IS NULL OR effective_to > effective_from`.
- Every effective-dated table also has a stored generated `current_guard`
  column (`1` when `effective_to IS NULL`, otherwise NULL) and a unique key on
  its owner/business key plus `current_guard`. This enforces at most one open
  row; the transactional overlap rule below covers closed historical ranges.
- Controlled enums below are stored exactly as written. Application labels may
  contain spaces, but database values do not.

## Canonical table dictionary

The following compact definitions are authoritative for migrations. `PK`,
`FK`, `UQ`, `NN`, and `NULL` mean primary key, foreign key, unique, not-null,
and nullable respectively.

### Identity, access, and audit

- `role`: `role_id BIGINT UNSIGNED PK`; `role_name VARCHAR(30) NN UQ`;
  timestamps.
- `users`: `user_id BIGINT UNSIGNED PK`; `employee_id BIGINT UNSIGNED NULL UQ
  FK employee`; `role_id BIGINT UNSIGNED NN FK role`; `username VARCHAR(50) NN
  UQ`; `account_email VARCHAR(254) NN UQ`; `password_hash VARCHAR(255) NN`;
  `status ENUM('Active','Inactive','Archived') NN DEFAULT 'Active'`;
  `created_at`, `updated_at`.
- `password_reset_challenge`: `challenge_id BIGINT UNSIGNED PK`; `user_id
  BIGINT UNSIGNED NN FK users`; `otp_hash VARCHAR(255) NN`; `expires_at
  DATETIME(0) NN`; `attempts_remaining TINYINT UNSIGNED NN`; `consumed_at
  DATETIME(0) NULL`; `created_at`; index `(user_id, expires_at)`.
- `sessions`: `session_id VARCHAR(128) PK`; `expires_at BIGINT UNSIGNED NN`;
  `data MEDIUMTEXT NN`; index `expires_at`. This table is migration-owned even
  when the project-owned PHP `SessionHandlerInterface` creates or manages
  session rows at runtime.
- `audit_logs`: `log_id BIGINT UNSIGNED PK`; `user_id BIGINT UNSIGNED NULL FK
  users`; `event_type VARCHAR(50) NN`; `action_performed VARCHAR(100) NN`;
  `table_affected VARCHAR(64) NULL`; `record_id BIGINT UNSIGNED NULL`;
  `attempted_identifier VARCHAR(254) NULL`; `request_id CHAR(36) NULL`;
  `ip_address VARCHAR(45) NULL`; `user_agent VARCHAR(500) NULL`; `description
  VARCHAR(1000) NULL`; `action_at DATETIME(0) NN`; `created_at`; indexes
  `(user_id, action_at)`, `(event_type, action_at)`, and `request_id`.

### Organization and employee master data

- `branch`: `branch_id BIGINT UNSIGNED PK`; `branch_code VARCHAR(20) NN UQ`;
  `branch_name VARCHAR(100) NN`; `location VARCHAR(200) NULL`; `status
  ENUM('Active','Inactive','Archived') NN`; timestamps.
- `attendance_site`: `site_id BIGINT UNSIGNED PK`; `site_code VARCHAR(20) NN
  UQ`; `site_name VARCHAR(100) NN`; `address VARCHAR(200) NULL`; `timezone
  VARCHAR(50) NN DEFAULT 'Asia/Manila'`; `status ENUM('Active','Inactive') NN`;
  timestamps.
- `biometric_device`: `device_id BIGINT UNSIGNED PK`; `site_id BIGINT UNSIGNED
  NN FK attendance_site`; `device_code VARCHAR(30) NN UQ`; `device_name
  VARCHAR(100) NULL`; `serial_number VARCHAR(100) NULL UQ`; `file_format
  VARCHAR(50) NN`; `timezone VARCHAR(50) NN`; `status
  ENUM('Active','Inactive','Retired') NN`; `installed_at DATE NULL`;
  `retired_at DATE NULL`; timestamps.
- `biometric_device_branch`: `device_branch_id BIGINT UNSIGNED PK`; `device_id
  BIGINT UNSIGNED NN FK biometric_device`; `branch_id BIGINT UNSIGNED NN FK
  branch`; `effective_from DATE NN`; `effective_to DATE NULL`; `status
  ENUM('Active','Inactive') NN`; timestamps; UQ
  `(device_id, branch_id, effective_from)`.
- `province`: `province_id BIGINT UNSIGNED PK`; `province_name VARCHAR(100) NN
  UQ`; timestamps.
- `city`: `city_id BIGINT UNSIGNED PK`; `city_name VARCHAR(100) NN`; timestamps;
  index `city_name`.
- `barangay`: `barangay_id BIGINT UNSIGNED PK`; `barangay_name VARCHAR(100) NN`;
  timestamps; index `barangay_name`.
- `address`: `address_id BIGINT UNSIGNED PK`; `street VARCHAR(150) NULL`;
  `province_id BIGINT UNSIGNED NULL FK province`; `city_id BIGINT UNSIGNED NULL
  FK city`; `barangay_id BIGINT UNSIGNED NULL FK barangay`; timestamps. The flat
  geography decision is retained.
- `employee`: `employee_id BIGINT UNSIGNED PK`; `employee_number VARCHAR(30) NN
  UQ immutable`; `employee_type ENUM('Regular','Contractual') NN`; `first_name
  VARCHAR(100) NN`; `middle_initial VARCHAR(5) NULL`; `last_name VARCHAR(100)
  NN`; `email VARCHAR(254) NULL UQ`; `contact_number VARCHAR(30) NULL`;
  `birthdate DATE NULL`; `hire_date DATE NN`; `id_picture VARCHAR(500) NULL`;
  `address_id BIGINT UNSIGNED NULL FK address`; `status
  ENUM('Active','Inactive','Separated','Archived') NN`; `sss_number
  VARCHAR(30) NULL`; `philhealth_number VARCHAR(30) NULL`; `pagibig_number
  VARCHAR(30) NULL`; `tin_number VARCHAR(30) NULL`; `position VARCHAR(100) NN`;
  `contract_review_date DATE NULL`; timestamps.
- `employment_contract_review`: `review_id BIGINT UNSIGNED PK`; `employee_id
  BIGINT UNSIGNED NN FK employee`; `review_due_date DATE NN`; `outcome
  ENUM('Regularized','Renewed','Separated') NN`; `effective_date DATE NN`;
  `next_review_date DATE NULL`; `reviewed_by BIGINT UNSIGNED NULL FK users`;
  `notes VARCHAR(1000) NULL`; `created_at`; UQ `(employee_id, effective_date)`.
- `employee_branch_assignment`: `branch_assignment_id BIGINT UNSIGNED PK`;
  `employee_id BIGINT UNSIGNED NN FK employee`; `branch_id BIGINT UNSIGNED NN
  FK branch`; `effective_from DATE NN`; `effective_to DATE NULL`;
  `transfer_reason VARCHAR(255) NULL`; `transferred_by BIGINT UNSIGNED NULL FK
  users`; `created_at`; UQ `(employee_id, effective_from)`.
- `employee_biometric_enrollment`: `enrollment_id BIGINT UNSIGNED PK`;
  `employee_id BIGINT UNSIGNED NN FK employee`; `device_id BIGINT UNSIGNED NN
  FK biometric_device`; `device_employee_code VARCHAR(50) NN`;
  `effective_from DATE NN`; `effective_to DATE NULL`; `status
  ENUM('Active','Inactive') NN`; timestamps; UQ
  `(device_id, device_employee_code, effective_from)`.
- `work_schedule`: `schedule_id BIGINT UNSIGNED PK`; `employee_id BIGINT
  UNSIGNED NN FK employee`; `working_days JSON NN`; `rest_days JSON NN`;
  `break_minutes SMALLINT UNSIGNED NN`; `work_start_time TIME NN`;
  `work_end_time TIME NN`; `standard_minutes SMALLINT UNSIGNED NN`;
  `effective_from DATE NN`; `effective_to DATE NULL`; `status
  ENUM('Active','Archived') NN`; timestamps; UQ `(employee_id, effective_from)`.
- `holiday_calendar`: `holiday_id BIGINT UNSIGNED PK`; `holiday_date DATE NN
  UQ`; `description VARCHAR(100) NN`; `holiday_type
  ENUM('Regular','Special') NN`; `pay_multiplier DECIMAL(4,2) NN`; `status
  ENUM('Active','Inactive') NN`; timestamps. Check: Regular implies `2.00` and
  Special implies `1.30`.

### Attendance import and timesheets

- `attendance_import_batch`: `import_batch_id BIGINT UNSIGNED PK`; `device_id
  BIGINT UNSIGNED NN FK biometric_device`; `uploaded_by BIGINT UNSIGNED NN FK
  users`; `file_name VARCHAR(255) NN`; `file_checksum CHAR(64) NN UQ`;
  `source_year SMALLINT UNSIGNED NN`; `source_month TINYINT UNSIGNED NN`;
  `parser_version VARCHAR(50) NN`; `status
  ENUM('Processing','Completed','Rejected') NN`; `records_parsed INT UNSIGNED
  NN DEFAULT 0`; `records_matched INT UNSIGNED NN DEFAULT 0`;
  `records_unmatched INT UNSIGNED NN DEFAULT 0`; `duplicates_skipped INT
  UNSIGNED NN DEFAULT 0`; `incomplete_days INT UNSIGNED NN DEFAULT 0`;
  `multi_punch_days INT UNSIGNED NN DEFAULT 0`; `uploaded_at DATETIME(0) NN`;
  `completed_at DATETIME(0) NULL`; check month `BETWEEN 1 AND 12`.
- `biometric_punch`: `punch_id BIGINT UNSIGNED PK`; `import_batch_id BIGINT
  UNSIGNED NN FK attendance_import_batch`; `device_id BIGINT UNSIGNED NN FK
  biometric_device`; `employee_id BIGINT UNSIGNED NULL FK employee`;
  `branch_assignment_id BIGINT UNSIGNED NULL FK employee_branch_assignment`;
  `device_employee_code VARCHAR(50) NN`; `source_local_at DATETIME(0) NN`;
  `punched_at_utc DATETIME(0) NN`; `punch_type ENUM('In','Out','Unknown') NULL`;
  `device_transaction_id VARCHAR(50) NULL`; `match_status
  ENUM('matched','unmatched','coverage_exception','duplicate') NN`;
  `source_department VARCHAR(100) NULL`; `source_user_id VARCHAR(50) NULL`;
  `source_employee_name VARCHAR(150) NULL`; `raw_record TEXT NN`;
  `source_workbook_row INT UNSIGNED NN`; `source_date_column VARCHAR(20) NN`;
  `resolved_by BIGINT UNSIGNED NULL FK users`; `resolved_at DATETIME(0) NULL`;
  `created_at`; UQ
  `(device_id, device_employee_code, source_local_at)`.

### PHP parser implementation note (ADR-0003)

The schema is implementation-stack neutral. The accepted frameworkless PHP
implementation uses a project-owned `SessionHandlerInterface` against
`sessions`, PDO repositories, and Phinx migrations. The legacy workbook adapter
persisted in `attendance_import_batch.parser_version` is
`LDE_XLS_DAILY_LOG_V1`; it decodes only the approved OLE/BIFF `.xls` matrix,
emits immutable raw punches, and leaves matching/persistence to the transactional
attendance import service. This is an implementation extension, not a change to
the approved data model.
- `attendance`: `attendance_id BIGINT UNSIGNED PK`; `employee_id BIGINT
  UNSIGNED NN FK employee`; `branch_assignment_id BIGINT UNSIGNED NN FK
  employee_branch_assignment`; `schedule_id BIGINT UNSIGNED NN FK
  work_schedule`; `attendance_date DATE NN`; `time_in TIME NULL`; `time_out
  TIME NULL`; `hours_worked_minutes SMALLINT UNSIGNED NN DEFAULT 0`;
  `late_minutes SMALLINT UNSIGNED NN DEFAULT 0`; `undertime_minutes SMALLINT
  UNSIGNED NN DEFAULT 0`; `overtime_minutes SMALLINT UNSIGNED NN DEFAULT 0`;
  `status ENUM('Complete','Incomplete','ReviewRequired','Approved') NN`;
  `source ENUM('xls_import','manual') NN`; `import_batch_id BIGINT UNSIGNED NULL
  FK attendance_import_batch`; timestamps; UQ `(employee_id, attendance_date)`.
  Check: imported rows require `import_batch_id`; manual rows require it NULL.
- `attendance_punch`: `attendance_id BIGINT UNSIGNED NN FK attendance`;
  `punch_id BIGINT UNSIGNED NN UQ FK biometric_punch`; `evidence_role
  ENUM('TimeIn','Intermediate','TimeOut','Unclassified') NN`; `created_at`; PK
  `(attendance_id, punch_id)`.
- `attendance_adjustment`: `adjustment_id BIGINT UNSIGNED PK`; `attendance_id
  BIGINT UNSIGNED NN FK attendance`; `adjusted_by BIGINT UNSIGNED NULL FK
  users`; `old_time_in TIME NULL`; `new_time_in TIME NULL`; `old_time_out TIME
  NULL`; `new_time_out TIME NULL`; `adjustment_type VARCHAR(50) NN`; `reason
  VARCHAR(1000) NN`; `adjustment_at DATETIME(0) NN`; `created_at`.
- `attendance_policy_flag`: `flag_id BIGINT UNSIGNED PK`; `employee_id BIGINT
  UNSIGNED NN FK employee`; `flag_type ENUM('ConsecutiveLate',
  'TardinessMemoCap','TwoWeekAbsence','ConsecutiveAWOL') NN`; `triggering_date
  DATE NN`; `status ENUM('Pending','Reviewed','Closed') NN`; `reviewed_by
  BIGINT UNSIGNED NULL FK users`; `reviewed_at DATETIME(0) NULL`; `action_taken
  VARCHAR(255) NULL`; `notes TEXT NULL`; timestamps; UQ
  `(employee_id, flag_type, triggering_date)`.

### Requests, leave, and cash advances

- `request_type`: `request_type_id BIGINT UNSIGNED PK`; `type_name
  ENUM('Leave','Overtime','CashAdvance') NN UQ`; `status
  ENUM('Active','Inactive') NN`; timestamps.
- `request`: `request_id BIGINT UNSIGNED PK`; `employee_id BIGINT UNSIGNED NN
  FK employee`; `request_type_id BIGINT UNSIGNED NN FK request_type`; `reason
  VARCHAR(1000) NN`; `status
  ENUM('Pending','Approved','Rejected','Cancelled') NN`; `submitted_at
  DATETIME(0) NN`; `reviewed_by BIGINT UNSIGNED NULL FK users`; `reviewed_at
  DATETIME(0) NULL`; `review_notes VARCHAR(1000) NULL`; `archived_by BIGINT
  UNSIGNED NULL FK users`; `archived_at DATETIME(0) NULL`; timestamps.
- `leave_request_detail`: `request_id BIGINT UNSIGNED PK FK request`;
  `leave_type ENUM('Sick') NN`; `start_date DATE NN`; `end_date DATE NN`;
  `days_requested DECIMAL(5,2) NN`; check positive days/end order.
- `overtime_request_detail`: `request_id BIGINT UNSIGNED PK FK request`;
  `overtime_date DATE NN`; `start_time TIME NN`; `end_time TIME NN`;
  `requested_minutes SMALLINT UNSIGNED NN`; check positive minutes.
- `cash_advance_request_detail`: `request_id BIGINT UNSIGNED PK FK request`;
  `amount DECIMAL(12,2) NN`; check positive amount.
- `leave_entitlement`: `entitlement_id BIGINT UNSIGNED PK`; `employee_id
  BIGINT UNSIGNED NN FK employee`; `leave_type ENUM('Sick') NN`; `leave_year
  SMALLINT UNSIGNED NN`; `entitled_days DECIMAL(5,2) NN DEFAULT 4.00`;
  timestamps; UQ `(employee_id, leave_type, leave_year)`.
- `leave_ledger`: `entry_id BIGINT UNSIGNED PK`; `entitlement_id BIGINT
  UNSIGNED NN FK leave_entitlement`; `request_id BIGINT UNSIGNED NULL UQ FK
  request`; `entry_type ENUM('Grant','Usage','Adjustment','Reversal') NN`;
  `days_delta DECIMAL(5,2) NN`; `recorded_by BIGINT UNSIGNED NULL FK users`;
  `notes VARCHAR(500) NULL`; `created_at`.
- `cash_advance_history`: `history_id BIGINT UNSIGNED PK`; `request_id BIGINT
  UNSIGNED NN UQ FK request`; `employee_id BIGINT UNSIGNED NN FK employee`;
  `original_amount DECIMAL(12,2) NN`; `remaining_balance DECIMAL(12,2) NN`;
  `status ENUM('Active','Paid','Cancelled') NN`; `approved_at DATETIME(0) NN`;
  timestamps; checks for non-negative balance not exceeding original amount.
- `cash_advance_repayment`: `repayment_id BIGINT UNSIGNED PK`; `history_id
  BIGINT UNSIGNED NN FK cash_advance_history`; `payroll_id BIGINT UNSIGNED NN
  FK payroll`; `deduction_id BIGINT UNSIGNED NN UQ FK deduction`; `amount
  DECIMAL(12,2) NN`; `created_at`; UQ `(history_id, payroll_id)`.

### Salary, policy, payroll, and contributions

- `salary`: `salary_id BIGINT UNSIGNED PK`; `employee_id BIGINT UNSIGNED NN FK
  employee`; `daily_rate DECIMAL(12,2) NN`; `effective_from DATE NN`;
  `effective_to DATE NULL`; `status ENUM('Active','Archived') NN`; `created_by
  BIGINT UNSIGNED NULL FK users`; timestamps; UQ `(employee_id, effective_from)`.
- `payroll_policy_version`: `policy_id BIGINT UNSIGNED PK`; `policy_code
  VARCHAR(50) NN`; `version VARCHAR(30) NN`; `effective_from DATE NN`;
  `effective_to DATE NULL`; `eemr_days_per_year SMALLINT UNSIGNED NN DEFAULT
  313`; `annual_tax_threshold DECIMAL(12,2) NN`; `late_rate_per_minute
  DECIMAL(12,2) NN DEFAULT 1.00`; `rounding_mode ENUM('HalfUp') NN`;
  `demo_only BOOLEAN NN`; `status ENUM('Draft','Approved','Retired') NN`;
  `approved_by BIGINT UNSIGNED NULL FK users`; `approved_at DATETIME(0) NULL`;
  timestamps; UQ `(policy_code, version)`.
- `contribution_policy_version`: `contribution_policy_id BIGINT UNSIGNED PK`;
  `policy_code VARCHAR(50) NN`; `version VARCHAR(30) NN`; `effective_from DATE
  NN`; `effective_to DATE NULL`; `demo_only BOOLEAN NN`; `status
  ENUM('Draft','Approved','Retired') NN`; `approved_by BIGINT UNSIGNED NULL FK
  users`; `approved_at DATETIME(0) NULL`; timestamps; UQ `(policy_code, version)`.
- `sss_bracket`: `bracket_id BIGINT UNSIGNED PK`; `contribution_policy_id
  BIGINT UNSIGNED NN FK contribution_policy_version`; `salary_from
  DECIMAL(12,2) NN`; `salary_to DECIMAL(12,2) NULL`; `employee_share
  DECIMAL(12,2) NN`; `employer_share DECIMAL(12,2) NN`; `effective_from DATE
  NN`; `effective_to DATE NULL`; timestamps; UQ
  `(contribution_policy_id, salary_from)`.
- `philhealth_rate`: `rate_id BIGINT UNSIGNED PK`; `contribution_policy_id
  BIGINT UNSIGNED NN UQ FK contribution_policy_version`; `rate_decimal
  DECIMAL(7,6) NN`; `basis_floor DECIMAL(12,2) NULL`; `basis_ceiling
  DECIMAL(12,2) NULL`; `employee_share_decimal DECIMAL(7,6) NN`; timestamps.
- `pagibig_rate`: `rate_id BIGINT UNSIGNED PK`; `contribution_policy_id BIGINT
  UNSIGNED NN UQ FK contribution_policy_version`; `rate_decimal DECIMAL(7,6)
  NULL`; `basis_ceiling DECIMAL(12,2) NULL`; `employee_fixed_amount
  DECIMAL(12,2) NULL`; `employer_fixed_amount DECIMAL(12,2) NULL`; timestamps.
  A check requires either an approved rate/cap combination or both fixed
  amounts, supporting the documented demo fixture without inventing policy.
- `payroll_period`: `payroll_period_id BIGINT UNSIGNED PK`; `period_start DATE
  NN UQ`; `period_end DATE NN`; `pay_date DATE NN UQ`; `status
  ENUM('Open','AttendanceClosed','Disbursed','Closed') NN`; timestamps. Checks:
  start Friday, end Thursday, `DATEDIFF(period_end,period_start)=6`, and
  `pay_date=DATE_ADD(period_end, INTERVAL 1 DAY)`.
- `payroll_run`: `payroll_run_id BIGINT UNSIGNED PK`; `payroll_period_id
  BIGINT UNSIGNED NN FK payroll_period`; `branch_id BIGINT UNSIGNED NN FK
  branch`; `payroll_policy_id BIGINT UNSIGNED NN FK payroll_policy_version`;
  `status ENUM('Draft','Computed','PendingOwnerApproval','Approved','Returned')
  NN`; `gross_pay DECIMAL(15,2) NULL`; `total_deductions DECIMAL(15,2) NULL`;
  `net_pay DECIMAL(15,2) NULL`; `computed_by BIGINT UNSIGNED NULL FK users`;
  `computed_at DATETIME(0) NULL`; `submitted_by BIGINT UNSIGNED NULL FK users`;
  `submitted_at DATETIME(0) NULL`; `reviewed_by BIGINT UNSIGNED NULL FK users`;
  `reviewed_at DATETIME(0) NULL`; `return_reason VARCHAR(500) NULL`;
  `lock_version INT UNSIGNED NN DEFAULT 0`; timestamps; UQ
  `(payroll_period_id, branch_id)` and UQ `(payroll_run_id, payroll_period_id)`.
  Status/timestamp checks require a reason for Returned and review metadata for
  Approved/Returned.
- `payroll`: `payroll_id BIGINT UNSIGNED PK`; `payroll_run_id BIGINT UNSIGNED
  NN`; `payroll_period_id BIGINT UNSIGNED NN`; `employee_id BIGINT UNSIGNED NN
  FK employee`; `branch_assignment_id BIGINT UNSIGNED NN FK
  employee_branch_assignment`; `salary_id BIGINT UNSIGNED NN FK salary`;
  `daily_rate_snapshot DECIMAL(12,2) NN`; `gross_pay DECIMAL(12,2) NN`;
  `total_deductions DECIMAL(12,2) NN`; `net_pay DECIMAL(12,2) NN`; `created_at`;
  composite FK `(payroll_run_id,payroll_period_id)` to payroll_run and UQ
  `(payroll_period_id,employee_id)`. There is no duplicated approval status or
  `total_benefits`.
- `payroll_earnings`: `earning_id BIGINT UNSIGNED PK`; `payroll_id BIGINT
  UNSIGNED NN FK payroll`; `earning_type ENUM('Basic','Overtime',
  'RegularHoliday','SpecialHoliday','ThirteenthMonth') NN`; `description
  VARCHAR(255) NN`; `source_attendance_id BIGINT UNSIGNED NULL FK attendance`;
  `source_request_id BIGINT UNSIGNED NULL FK request`; `quantity
  DECIMAL(12,4) NN`; `unit_rate DECIMAL(12,4) NN`; `multiplier DECIMAL(8,4) NN`;
  `amount DECIMAL(12,2) NN`; `calculation_details JSON NN`; `created_at`.
- `deduction`: `deduction_id BIGINT UNSIGNED PK`; `payroll_id BIGINT UNSIGNED
  NN FK payroll`; `deduction_type ENUM('Late','Undertime','CashAdvance','SSS',
  'PhilHealth','PagIBIG','IncomeTax') NN`; `description VARCHAR(255) NN`;
  `source_attendance_id BIGINT UNSIGNED NULL FK attendance`; `quantity
  DECIMAL(12,4) NN`; `unit_rate DECIMAL(12,4) NN`; `amount DECIMAL(12,2) NN`;
  `calculation_details JSON NN`; `created_at`.
- `contribution_record`: `contribution_id BIGINT UNSIGNED PK`; `payroll_id
  BIGINT UNSIGNED NN FK payroll`; `deduction_id BIGINT UNSIGNED NN UQ FK
  deduction`; `contribution_policy_id BIGINT UNSIGNED NN FK
  contribution_policy_version`; `contribution_type
  ENUM('SSS','PhilHealth','PagIBIG') NN`; `eemr_basis DECIMAL(12,2) NN`;
  `employee_share DECIMAL(12,2) NN`; `employer_share DECIMAL(12,2) NN`;
  `deduction_date DATE NN`; `calculation_details JSON NN`; `status
  ENUM('Computed','Locked') NN`; `locked_at DATETIME(0) NULL`; `locked_by
  BIGINT UNSIGNED NULL FK users`; `created_at`; UQ
  `(payroll_id, contribution_type)`.
- `payslip`: `payslip_id BIGINT UNSIGNED PK`; `payroll_id BIGINT UNSIGNED NN UQ
  FK payroll`; `issue_date DATE NN`; `file_path VARCHAR(500) NULL`;
  `generated_by BIGINT UNSIGNED NULL FK users`; `generated_at DATETIME(0) NN`;
  `content_hash CHAR(64) NULL`.

### Bank disbursement

- `bank_details`: `bank_id BIGINT UNSIGNED PK`; `employee_id BIGINT UNSIGNED NN
  FK employee`; `bank_name VARCHAR(100) NN`; `account_name VARCHAR(150) NN`;
  `account_number VARCHAR(50) NN`; `account_type VARCHAR(50) NULL`;
  `effective_from DATE NN`; `effective_to DATE NULL`; `status
  ENUM('Active','Inactive') NN`; timestamps; UQ `(bank_name, account_number,
  effective_from)`.
- `disbursement_batch`: `batch_id BIGINT UNSIGNED PK`; `payroll_period_id
  BIGINT UNSIGNED NN UQ FK payroll_period`; `bank_name VARCHAR(100) NN DEFAULT
  'BDO'`; `cheque_number VARCHAR(50) NULL UQ`; `cheque_total DECIMAL(15,2) NN`;
  `status ENUM('Prepared','Issued','Reconciled') NN`; `prepared_by BIGINT
  UNSIGNED NULL FK users`; `submitted_at DATETIME(0) NULL`; timestamps.
- `deposit_slip`: `slip_id BIGINT UNSIGNED PK`; `batch_id BIGINT UNSIGNED NN FK
  disbursement_batch`; `payroll_id BIGINT UNSIGNED NN UQ FK payroll`; `bank_id
  BIGINT UNSIGNED NN FK bank_details`; `amount DECIMAL(12,2) NN`; `status
  ENUM('Pending','Prepared','Deposited') NN`; `prepared_at DATETIME(0) NULL`;
  timestamps. The service verifies the bank account belongs to the payroll
  employee, the payroll belongs to the batch period, and amount equals net pay.

## Cross-row integrity rules

The following rules require transactional service validation in addition to
the listed database keys/checks:

1. Lock the employee/device/policy parent key before inserting or closing an
   effective period; reject any intersecting half-open interval.
2. Resolve attendance branch and schedule using `attendance_date`; resolve
   biometric identity using `source_local_at`; resolve payroll membership using
   `payroll_period.period_start`.
3. A `payroll.branch_assignment_id` must belong to its employee and to the
   run's branch on period start. Its `salary_id` must be effective on that date.
4. Approved payroll runs and their payroll, earning, deduction, contribution,
   and payslip rows are immutable.
5. Payroll totals equal the sum of detail lines; contribution employee shares
   equal their linked deduction amounts; disbursement totals equal included net
   pay and deposit-slip amounts.
6. Overtime earning requires both attendance evidence and an Approved overtime
   request. Holiday and overtime multipliers are not compounded.
7. The contribution policy must be Approved, effective on `pay_date`, and able
   to resolve the EEMR; unsupported inputs fail visibly.
8. A request has exactly one detail row matching its request type. Archival
   requires a resolved/cancelled status.
9. One current active BDO account is selected per employee. Effective bank
   histories may not overlap for the same bank/account use case.

## Required automated schema tests

- PK/UQ/FK/not-null/check constraint tests for every migration.
- Temporal overlap and concurrent-transfer tests.
- Workbook year/month/weekday validation and transaction rollback tests.
- Attendance grain and raw-punch lineage tests.
- Payroll period/run/detail consistency and double-pay rejection tests.
- Calculation snapshot and aggregate reconciliation tests.
- Contribution-to-deduction and cash-advance-to-repayment reconciliation tests.
- Failed-login audit, password-reset expiry/consumption, and session-expiry tests.
- Single weekly cheque, single payslip, and employee bank ownership tests.
