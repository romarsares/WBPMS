# Web-Based Payroll Management System (WBPMS)
### Light Diamond Enterprises — Project Documentation

> **Authors:** Jiane Arielle B. Gamboa · Almairah D. Maindan  
> **Institution:** STI College Tacurong — BS Information Technology  
> **Adviser:** Nicole Haylynn G. Mancao, LPT  
> **Date:** April 2026

---

## Table of Contents

1. [Project Context](#1-project-context)
2. [Problem Statement](#2-problem-statement)
3. [Objectives](#3-objectives)
4. [Scope and Limitations](#4-scope-and-limitations)
5. [System Users and Roles](#5-system-users-and-roles)
6. [Architecture Overview](#6-architecture-overview)
7. [Technology Stack](#7-technology-stack)
8. [Database Schema](#8-database-schema)
9. [Module Specifications](#9-module-specifications)
10. [Business Rules](#10-business-rules)
11. [Payroll Computation Logic](#11-payroll-computation-logic)
12. [Government Contribution Computation](#12-government-contribution-computation)
13. [Approval Workflows](#13-approval-workflows)
14. [Security Model](#14-security-model)
15. [Non-Functional Requirements](#15-non-functional-requirements)
16. [Known Limitations and Planned Enhancements](#16-known-limitations-and-planned-enhancements)
17. [Development Setup](#17-development-setup)
18. [Demo Credentials](#18-demo-credentials)

---

## 1. Project Context

**Light Diamond Enterprises** is a retail and supply business established on
March 26, 2007, by owner Mr. Rene Joey Montefrio Ga-an. The main office is
located at Purok Katipunan, Barangay Reyes, Banga, South Cotabato. A second
branch dedicated to automotive supplies operates along the National Highway,
Barangay Libertad, Surallah, South Cotabato.

The enterprise employs **30 workers** across both branches:

| Branch | Headcount | Positions |
|---|---|---|
| Banga (main) | 24 | Store In-charge, Sales Clerks, Cashier, Checkers, Encoders, Drivers, Laborers, Secretary, HR Head, Warehouse In-charges |
| Surallah | 6 | Sales Clerks, Cashier, Checker, Encoder |

### Current Payroll Process

The company follows a **weekly payroll cycle** with a cut-off from Sunday to
Friday (or Friday to Thursday for subsequent weeks), with salary released every
Friday. The existing process is partially manual:

1. Employees clock in and out on a biometric fingerprint scanner.
2. Every Thursday afternoon, the HR Head downloads attendance logs from the
   biometric device via USB or network connection.
3. The HR Head reviews each entry, checks for missing time-ins/outs against
   CCTV footage, and corrects discrepancies in the raw export.
4. Verified attendance data is manually encoded into a Microsoft Excel payroll
   spreadsheet.
5. The HR Head computes gross pay, overtime, late deductions, government
   contributions (SSS, PhilHealth, Pag-IBIG), and cash advance repayments
   using Excel formulas.
6. The Business Owner issues a company cheque for the total payroll amount.
7. The HR Head prepares individual cash transaction slips and deposits salaries
   into employee BDO Network Bank accounts every Friday afternoon.
8. A new Excel file is created each payroll cycle for record-keeping.

---

## 2. Problem Statement

The existing process presents five key operational challenges:

1. **Branch logistics** — Surallah employees must physically travel to the Banga
   office to submit leave or overtime request letters, creating delays and costs.
2. **Data integrity risk** — Attendance records are managed by a single HR Head.
   Personal conflicts could allow manipulation of time logs without detection.
3. **No real-time tardiness monitoring** — Late arrivals are only tallied at
   payroll time, making it difficult to enforce the company's disciplinary
   policy (three consecutive lates → written memorandum; extended absences →
   grounds for termination).
4. **Manual bank preparation** — Without an automated payroll disbursement system,
   the HR Head manually prepares individual cash transaction slips for all 30
   employees every week.
5. **Fragmented records** — One Excel file per payroll cycle makes historical
   data retrieval slow and cumbersome.

---

## 3. Objectives

### General Objective

Develop a Web-Based Payroll Management System for Light Diamond Enterprises that
automates payroll computation, centralizes employee records, and provides
role-based access to payroll information.

### Specific Objectives

**Backend Modules**

| Module | Objective |
|---|---|
| Authentication & Authorization | Validate credentials, manage secure sessions, enforce role-based access (BusinessOwner / HRHead / Employee) |
| User Management | Manage accounts, roles, status, and secure access |
| Employee & Organization Management | Store employee profiles, branch assignments, biometric enrollment, and attendance sites |
| Work Schedule & Holiday | Manage schedules, workdays, times, grace periods, OT rules, and holiday calendar |
| Attendance Import & Processing | Validate uploaded legacy `.xls` biometric logs, extract and match punch data, generate one attendance record per employee per day |
| Attendance Computation | Compute worked hours, late minutes, undertime, overtime, and incomplete-attendance status |
| Request Management | Store leave/overtime/cash-advance requests; support two-stage (HR → Owner) approval workflow |
| Salary Management | Store effective-dated daily rate history per employee |
| Benefits & Contributions | Manage SSS brackets, PhilHealth rate, Pag-IBIG rate; compute employee and employer shares |
| Payroll Processing | Generate branch-based weekly payroll combining attendance, requests, salary, and contributions |
| Payroll Approval | Route payroll from HR Head → Business Owner; support approve or return-for-revision |
| Payslip | Generate employee payslip records from approved payroll runs |
| Reports | Generate payroll, attendance, request, contribution, and 13th-month-pay reports |
| Dashboard & Notifications | Role-specific summaries, attendance alerts, pending requests and payroll status |
| Database, Audit & Security | MySQL persistence, audit logs, CSRF protection, password hashing, prepared SQL, role restrictions |

**Front-End Modules**

| Module | Objective |
|---|---|
| Login & Authentication | Sign in with username and password; role-based dashboard redirect |
| Role-Based Dashboard | Separate views for Owner, HR Head, and Employee |
| User Management UI | Owner manages user accounts and roles |
| Employee Management UI | HR Head manages employee records and branch assignments |
| Attendance Management UI | HR Head views computed attendance; imports biometric `.xls` files |
| Work Schedule UI | HR Head manages schedules and holiday calendar |
| Request Management UI | Admin views and processes employee requests |
| Salary Management UI | HR Head views and manages salary structures |
| Benefits & Contributions UI | HR Head views and manages government contribution records |
| Payroll Management UI | HR Head creates, computes, reviews, and submits payroll runs |
| Reports UI | HR Head and Owner view, print, and export reports |
| Employee Portal | Employee views own attendance, requests, and payslips; submits requests |

---

## 4. Scope and Limitations

### In Scope

| Module | Description |
|---|---|
| Dashboard | Role-based summaries — employees per branch, on-leave count, pending requests, payroll status, attendance alerts |
| User Management | Create, update, activate, deactivate, archive user accounts; role assignment |
| Employee Management | Full CRUD on employee records, branch assignments, document management |
| Work Schedule | Calendar-based schedule management; holiday calendar integration |
| Attendance | Biometric `.xls` import and timesheet generation; manual adjustment; policy flag evaluation |
| Request Management | Leave, overtime, cash advance — submission, two-stage approval, tracking |
| Payroll | Weekly payroll computation per branch; gross pay, deductions, contributions, net pay; approval workflow |
| Salary | Effective-dated daily rate records per employee |
| Benefits & Deductions | SSS, PhilHealth, Pag-IBIG computation and contribution records |
| Reports | Payroll summaries, attendance reports, request reports, government contributions, 13th-month pay; print-ready HTML |
| Employee Self-Service | Attendance viewing, request submission, payslip access |

### Out of Scope

- Integration with banking systems for automatic salary deposits
- Online remittance to government agencies (SSS, PhilHealth, Pag-IBIG)
- Recruitment, hiring, and performance evaluation
- Accounting and financial reporting modules
- Inventory and sales management
- Full Human Resource Management (HRM) system
- Mobile native apps (web-responsive desktop/laptop focus)

### Known Constraints

- Relies on the accuracy and availability of the biometric device's `.xls` export
- Requires stable internet connectivity for web access
- Income tax computation is designed in the schema but not yet implemented (employees currently below threshold)
- Rest-day holiday pay detection (higher pay multiplier) is pending implementation

---

## 5. System Users and Roles

Three roles are hardcoded in the `role` table and enforced on every route.

### Business Owner

- Final approver of all completed payroll runs (approve or return for revision)
- Final approver or canceller of all employee requests (leave, overtime, cash advance)
- Views payroll lists, disbursement summaries, timesheet printouts
- Views attendance frequency reports and all system reports
- Creates and manages user accounts (the only role with this ability)
- **Note:** The Owner's user account has no linked employee record and does not appear in payroll

### HR Head

- Full employee management: create, edit, archive, rehire, transfer, document upload
- Work schedule management and assignment to employees
- Biometric attendance import, batch approval/cancellation, manual adjustment
- Attendance policy flag evaluation and HR notice issuance
- Request pre-approval (stage 1 of 2-stage workflow) and creation on behalf of employees
- Salary rate management
- Contribution policy management and record locking
- Payroll period and run creation; payroll computation and submission
- Manual payroll adjustments
- All printable payroll documents and reports
- Settings: job positions, payroll periods, holidays
- Can view and edit users but cannot create or archive them

### Employee

- Read-only self-service portal scoped strictly to their own records
- View own attendance (time-in, time-out, hours, late, undertime, overtime)
- Submit and cancel own leave, overtime, and cash advance requests
- View and acknowledge HR-issued policy notices
- View and print own approved payslips

---

## 6. Architecture Overview

WBPMS is a **frameworkless PHP 8.2 application** following a three-tier,
MVC-inspired layered architecture. No full-stack framework (Laravel, Symfony,
etc.) is used.

```
Browser
   │
   │ HTTP
   ▼
public/index.php          ← sole web-accessible entry point
   │
   ▼
bootstrap/app.php         ← env load, session wiring, router instantiation
   │
   ▼
Router::handle()          ← method+path match, CSRF check, RBAC check
   │
   ▼
Controller                ← thin HTTP layer (parse input, call services, render)
   │
   ▼
Application Service       ← business logic, PDO transactions
   │           │
   │           ▼
   │      Domain classes  ← pure calculations (TimesheetGenerator,
   │                         HolidayPayCalculator, etc.)
   ▼
Infrastructure/Persistence ← PDO repositories, MySQL
```

### Request Lifecycle

1. `public/index.php` defines `APP_ROOT`, loads the Composer autoloader, registers a global exception handler, and includes `bootstrap/app.php`.
2. `bootstrap/app.php` loads `.env` (via `vlucas/phpdotenv`), sets `Asia/Manila` timezone, wires a `DatabaseSessionHandler` (MySQL-backed PHP sessions), starts the session, instantiates the `Router`, and loads `routes/web.php`.
3. `Router` iterates registered routes, matches by HTTP method + path regex, runs `CsrfMiddleware::verify()` for mutating methods, and calls `AuthMiddleware::requireRoles()` for protected routes.
4. `AuthMiddleware` re-fetches the session identity from the database on **every request**, ensuring role/status changes take effect immediately.

### Directory Structure

```
app/
  Application/         ← application services (orchestrate transactions)
    Attendance/
    EmployeePortal/
    EmployeeSetup/
  Domain/              ← pure domain classes (no HTTP/DB dependencies)
    Attendance/
    Payroll/
  Http/
    Controllers/       ← thin HTTP handlers
    Middleware/        ← AuthMiddleware, CsrfMiddleware
    Routing/           ← Router
    View/              ← ViewRenderer, Formatter
  Infrastructure/
    Database/          ← PDO Connection
    Mail/              ← Mailer
    Persistence/       ← PDO repositories
bootstrap/
config/
database/
  migrations/          ← 21 Phinx migrations
  seeds/
public/                ← ONLY web-accessible directory
  index.php
resources/views/       ← server-rendered PHP/HTML templates
routes/
  web.php              ← all 80+ routes
storage/private/       ← uploads, logs, generated files (NOT web-accessible)
tests/
composer.json
phinx.php
phpunit.xml
```

---

## 7. Technology Stack

| Area | Choice |
|---|---|
| Runtime | PHP 8.2 (strict types, enums, named arguments) |
| Web Framework | None — project-owned `Router` + front controller |
| Database | MySQL 8.x — InnoDB, `utf8mb4_unicode_ci` |
| Session Storage | MySQL-backed `DatabaseSessionHandler` |
| Migrations | Phinx 0.16 (`phinx.php` config) |
| XLS Parsing | `phpoffice/phpspreadsheet` 3.3 |
| Environment | `vlucas/phpdotenv` 5.6 |
| Testing | PHPUnit 11.0 |
| Static Analysis | PHPStan 1.12 |
| Email | Project-owned `Mailer` (SMTP or log driver via `.env`) |
| Deployment | XAMPP/Apache with `.htaccess` rewrite, or PHP built-in server |
| Reports/PDF | Print-ready HTML (Dompdf integration planned) |

### Environment Variables (`.env`)

| Variable | Purpose |
|---|---|
| `APP_ENV` | `development` or `production` |
| `APP_BASE_URL` | Base URL of the application |
| `APP_KEY` | 32-character secret key for session signing |
| `DB_HOST` | MySQL host |
| `DB_PORT` | MySQL port (default 3306) |
| `DB_NAME` | Database name (`wbpms`) |
| `DB_USER` | Database user |
| `DB_PASSWORD` | Database password |
| `MAIL_DRIVER` | `log` (development) or `smtp` (production) |
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | SMTP port |
| `MAIL_FROM_ADDRESS` | Sender address |

---

## 8. Database Schema

The schema is implemented across **21 Phinx migrations**. All tables use InnoDB
with `utf8mb4_unicode_ci` collation.

### Identity & Access (Migration 001)

| Table | Key Columns | Notes |
|---|---|---|
| `role` | `role_id`, `role_name` | Values: `BusinessOwner`, `HRHead`, `Employee` |
| `users` | `user_id`, `employee_id` (nullable), `role_id`, `username`, `account_email`, `password_hash`, `status`, `requires_password_change`, `password_reset_token`, `password_reset_expires_at` | `employee_id` NULL for Business Owner |
| `sessions` | `session_id` (PK), `expires_at`, `data` | MySQL-backed PHP sessions |
| `audit_logs` | `log_id`, `user_id`, `event_type`, `action_performed`, `table_affected`, `record_id`, `ip_address`, `user_agent` | Immutable audit trail |

### Organization & Employee (Migration 002)

| Table | Key Columns | Notes |
|---|---|---|
| `branch` | `branch_id`, `branch_code` (UQ), `branch_name`, `location`, `status` | |
| `attendance_site` | `site_id`, `site_code` (UQ), `site_name`, `timezone` | Physical biometric device locations |
| `biometric_device` | `device_id`, `site_id`, `device_code` (UQ), `serial_number` (UQ), `file_format`, `status` | |
| `employee` | `employee_id`, `employee_number` (UQ, immutable), `employee_type` (Regular/Contractual), `first_name`, `middle_initial`, `last_name`, `email`, `contact_number`, `birthdate`, `hire_date`, `id_picture`, `address_id`, `status`, `sss_number`, `philhealth_number`, `pagibig_number`, `tin_number` | |
| `employee_branch_assignment` | `branch_assignment_id`, `employee_id`, `branch_id`, `effective_from`, `effective_to` | Effective-dated history |
| `employee_biometric_enrollment` | `enrollment_id`, `employee_id`, `device_id`, `device_employee_code`, `effective_from`, `effective_to` | Maps device code → employee |
| `work_schedule` | `schedule_id`, `working_days` (JSON), `rest_days` (JSON), `work_start_time`, `work_end_time`, `standard_minutes`, `effective_from`, `effective_to` | |
| `holiday_calendar` | `holiday_id`, `holiday_date` (UQ), `description`, `holiday_type` (Regular/Special/SpecialNonWorking), `pay_multiplier`, `status` | |

### Attendance Import (Migration 003)

| Table | Key Columns | Notes |
|---|---|---|
| `attendance_import_batch` | `import_batch_id`, `device_id`, `uploaded_by`, `file_checksum` (SHA-256 UQ), `source_year`, `source_month`, `status` (Processing/Completed/Rejected/Approved/Cancelled) | |
| `biometric_punch` | `punch_id`, `import_batch_id`, `device_employee_code`, `source_local_at`, `match_status` | Immutable raw punch evidence |
| `attendance` | `attendance_id`, `employee_id`, `attendance_date`, `time_in`, `time_out`, `hours_worked_minutes`, `late_minutes`, `undertime_minutes`, `overtime_minutes`, `status` (Complete/Incomplete/ReviewRequired/Approved), `source` (xls_import/manual) | UQ (employee_id, attendance_date) |
| `attendance_adjustment` | `adjustment_id`, `attendance_id`, `adjusted_by`, `old_time_in/out`, `new_time_in/out`, `reason` | Append-only |
| `attendance_policy_flag` | `flag_id`, `employee_id`, `flag_type`, `triggering_date`, `status` | |

### Requests, Leave, Cash Advance (Migration 004)

| Table | Key Columns | Notes |
|---|---|---|
| `request` | `request_id`, `employee_id`, `request_type_id`, `reason`, `status` (Pending/HRApproved/Approved/Rejected/Cancelled/Returned) | Two-stage approval |
| `leave_request_detail` | 1:1 with request; `leave_type`, `start_date`, `end_date`, `days_requested` | |
| `overtime_request_detail` | 1:1 with request; `overtime_date`, `start_time`, `end_time`, `requested_minutes` | |
| `cash_advance_request_detail` | 1:1 with request; `amount` | |
| `leave_entitlement` | `employee_id`, `leave_type`, `leave_year`, `entitled_days` (default 4) | 4 paid sick days/year |
| `leave_ledger` | Append-only; entry types: Grant/Usage/Adjustment/Reversal | Balance = SUM(days_delta) |
| `cash_advance_history` | `history_id`, `request_id` (UQ), `original_amount`, `remaining_balance`, `status` (Active/Paid/Cancelled) | Created on Owner approval |

### Salary, Payroll, Contributions (Migration 005+)

| Table | Key Columns | Notes |
|---|---|---|
| `salary` | `salary_id`, `employee_id`, `daily_rate`, `effective_from`, `effective_to`, `status` | UQ (employee_id, effective_from) |
| `payroll_policy_version` | `policy_id`, `eemr_days_per_year` (313), `late_rate_per_minute` (₱1.00), `rounding_mode` (HalfUp) | Versioned policy rules |
| `contribution_policy_version` | Versioned contribution policy; SSS brackets / PhilHealth rate / Pag-IBIG rate reference this | |
| `sss_bracket` | `salary_from`, `salary_to`, `employee_share`, `employer_share` | Tiered bracket lookup |
| `philhealth_rate` | `rate_decimal` (0.04 = 4%), `employee_share_decimal` (0.02 = 2%), `basis_floor`, `basis_ceiling` | One row per policy |
| `pagibig_rate` | `employee_fixed_amount`, `employer_fixed_amount`, or percentage-based | ₱200 fixed (demo) |
| `payroll_period` | `period_start` (Friday), `period_end` (Thursday), `pay_date` (next Friday), `status` | DB CHECK: 7-day span enforced |
| `payroll_run` | `payroll_run_id`, `payroll_period_id`, `branch_id`, `status` (Draft/Computed/PendingOwnerApproval/Approved/Returned/Cancelled) | UQ (period, branch) |
| `payroll` | `payroll_id`, `payroll_run_id`, `employee_id`, `daily_rate_snapshot`, `gross_pay`, `total_deductions`, `net_pay` | UQ (period, employee); immutable after approval |
| `payroll_earnings` | `earning_type` (Basic/Overtime/RegularHoliday/SpecialHoliday/ThirteenthMonth/ManualAdjustment), `quantity`, `unit_rate`, `multiplier`, `amount`, `calculation_details` (JSON) | Itemized earning lines |
| `deduction` | `deduction_type` (Late/Undertime/CashAdvance/SSS/PhilHealth/PagIBIG/IncomeTax/ManualAdjustment/Absence), `amount`, `calculation_details` (JSON) | Itemized deduction lines |
| `contribution_record` | `contribution_type` (SSS/PhilHealth/PagIBIG), `eemr_basis`, `employee_share`, `employer_share`, `status` (Computed/Locked) | UQ (payroll_id, contribution_type) |
| `payslip` | `payslip_id`, `payroll_id` (UQ), `employee_id` | One per approved payroll row |
| `payroll_adjustment` | Append-only manual earning/deduction adjustments | Migration 012 |

---

## 9. Module Specifications

### Authentication Module

- Login via email + password; `password_hash()` / `password_verify()` (bcrypt)
- Session identity re-fetched from DB on every request (live RBAC)
- Forgot Password: `bin2hex(random_bytes(32))` token, SHA-256 hash stored, 1-hour expiry, one-time use
- First-login / forced password change enforced before accessing any other module
- All login attempts (success and failure) logged to `audit_logs`

### User Management Module

- BusinessOwner: full CRUD — create, edit, activate/deactivate, archive, reset password
- HRHead: can view and edit users only
- User accounts can exist without a linked employee record (Owner role)

### Employee Management Module

- CRUD: name, contact, position, contract type (Regular/Contractual), daily rate, branch
- Effective-dated branch assignments — history preserved on transfer
- **Archive**: validates no open payroll, pending requests, or active cash advances; closes all active relationships; deactivates linked user; creates `employee_employment_episode` + `employee_lifecycle_event`
- **Rehire**: validates Archived status; creates new branch/schedule/enrollment/salary rows; optionally re-activates user with temporary password
- Document management: upload, view, replace, verify, archive scanned employee files

### Attendance Import Module

- Upload biometric `.xls` export (LDE XLS Daily Log V1 format)
- SHA-256 duplicate file detection before any processing
- PhpSpreadsheet parses the workbook; `LdeXlsDailyLogParser` extracts punches
- `device_employee_code` matched to `employee_biometric_enrollment` (effective-dated)
- Unmatched punches retained in `biometric_punch` for HR review
- `TimesheetGenerator`: first punch = time-in, last = time-out; computes worked/late/undertime/overtime minutes; flags `INCOMPLETE` (single punch) and `MULTI_PUNCH_REVIEW` (>2 punches)
- Entire operation in a single DB transaction — failure rolls back completely
- HR approves or cancels the import batch; only **Approved** batches are payroll-eligible

### Request Management Module

Two-stage approval workflow:

```
Employee submits → Pending
HR Head approves → HRApproved
Owner approves   → Approved  (side effects applied)
Owner returns    → Returned  (back to HR for revision)
HR/Owner cancels → Cancelled
```

**Side effects on Owner Approval:**
- **Leave**: leave balance checked; `leave_ledger` debited with `Usage` entry
- **Cash Advance**: `cash_advance_history` row created (`Active`, `remaining_balance = amount`)
- **Overtime**: stored; used in payroll computation

### Payroll Processing Module

See [Section 11 — Payroll Computation Logic](#11-payroll-computation-logic) for full details.

Payroll run lifecycle:

```
Draft → [HR computes] → Computed → [HR submits] → PendingOwnerApproval
     → [Owner approves] → Approved   (immutable; payslips finalized)
     → [Owner returns]  → Returned   (HR can recompute and resubmit)
HR can cancel at Draft / Computed / Returned
```

### Reports Module

Available to HRHead and BusinessOwner:

| Report | Description |
|---|---|
| Payroll Summary | Gross pay, deductions, net pay per branch and period |
| Employee Payroll List | Per-employee breakdown for a selected run |
| Attendance Report | Time-in, time-out, worked hours, late/undertime for a date range |
| Request Report | Leave, overtime, cash advance summaries by status |
| Government Contributions | SSS, PhilHealth, Pag-IBIG employee and employer shares |
| 13th Month Pay | Basic pay total ÷ 12 per employee for a calendar year |
| Disbursement Summary | Bank preparation list for a payroll run |
| Timesheet | Printable attendance summary per payroll run |

---

## 10. Business Rules

| Rule | Detail |
|---|---|
| Payroll period | Friday to Thursday (6 calendar days); pay date = following Friday |
| First-week exception | First salary covers Sunday–Thursday (5 days) |
| Daily rate basis | Gross = days present × daily rate |
| Late penalty | ₱1.00 per minute of tardiness |
| Undertime penalty | Per-minute rate (daily_rate ÷ standard_minutes) per undertime minute |
| Overtime eligibility | Only hours backed by both attendance AND an approved Overtime request are paid |
| Regular overtime multiplier | 1.25 × hourly rate |
| Cash advance deduction | ₱500 per week until balance is fully settled |
| Maximum cash advance | ₱2,000 per request |
| Paid sick leave | 4 days per year; balance checked before leave is approved |
| Vacation/incentive leave | No-work, no-pay |
| Contribution cutoff | Government contributions deducted on the last-Friday pay date of each month only |
| EEMR formula | `(daily_rate × 313) ÷ 12` |
| Payroll immutability | Once a run is Approved, all payroll rows and their earnings/deductions are locked |
| Approved row protection | Recomputation of an Approved run is blocked at the application layer |
| Disciplinary policy — tardiness | 3 consecutive late arrivals → written memorandum; further tardiness → 1-week suspension |
| Disciplinary policy — AWOL | 2 weeks non-attendance → AWOL flag; 3 consecutive absences without notice → grounds for termination |
| Discipline enforcement | System flags only — no automatic suspension or termination; HR issues notice after review |

---

## 11. Payroll Computation Logic

`PayrollService::computeRun()` executes inside a database transaction with an
optimistic `FOR UPDATE` lock.

### Step-by-Step

1. **Validate run state** — Only `Draft`, `Computed`, or `Returned` runs can be recomputed. Runs with manual adjustments cannot be recomputed.
2. **Resolve policies** — Load active `payroll_policy_version` (late rate, EEMR days, rounding mode) and `contribution_policy_version` (SSS brackets, PhilHealth rate, Pag-IBIG rate).
3. **Select eligible employees** — Active employees with a valid branch assignment, active salary, and optional schedule assignment effective on `period_start`.
4. **Lock and clear** — Acquire `FOR UPDATE` lock; delete existing earnings, deductions, contribution records, payslips, and payroll rows for the run.
5. **Per-employee computation:**

   ```
   Basic Pay       = days_with_valid_attendance × daily_rate
   Paid Leave      = approved_leave_days × daily_rate
   Overtime Pay    = approved_OT_minutes × minute_rate × 1.25
   Holiday Premium = (holiday_multiplier - 1.0) × daily_rate  [per holiday day]
   ─────────────────────────────────────────────────────────
   Gross Pay       = Basic + PaidLeave + Overtime + HolidayPremium

   Late Deduction      = total_late_minutes × ₱1.00
   Undertime Deduction = total_undertime_minutes × minute_rate
   Cash Advance        = min(₱500, remaining_balance)  [if active obligation]
   SSS (employee)      = bracket lookup on EEMR
   PhilHealth          = EEMR × 2%
   Pag-IBIG            = ₱200 fixed  (or rate-based)
   ─────────────────────────────────────────────────────────
   Total Deductions    = Late + Undertime + CashAdvance + SSS + PhilHealth + PagIBIG
   Net Pay             = max(0, Gross - TotalDeductions)
   ```

6. **Holiday pay rules** (via `HolidayPayCalculator`):

   | Holiday Type | Absent | Worked | Rest-Day Worked | OT Multiplier |
   |---|---|---|---|---|
   | Regular Holiday | 100% (₱1.00 × rate) | 200% | 260% | holiday_hourly × 1.30 |
   | Special Working Holiday | 0% | 100% | 130% | holiday_hourly × 1.30 |
   | Special Non-Working Holiday | 0% | 130% | 150% | holiday_hourly × 1.30 |

   > **Note:** Rest-day detection is hardcoded to `false` in the current implementation — the higher rest-day multipliers are not yet applied. This is a planned enhancement.

7. **Contributions applied only on last-Friday pay date** — `isMonthlyContributionCutoff()` checks if `pay_date` is the last Friday in its calendar month.
8. **Audit trail** — Every earning and deduction line stores a `calculation_details` JSON snapshot of all inputs used to derive the amount.
9. **Run totals** — Aggregate gross/deductions/net across all employees; update `payroll_run`.

### 13th Month Pay

```
13th_month = SUM(payroll_earnings.amount WHERE earning_type = 'Basic'
                 AND payroll_run.status = 'Approved'
                 AND year(pay_date) = :year) ÷ 12
```

Filterable by branch and employee.

---

## 12. Government Contribution Computation

### EEMR (Estimated Equivalent Monthly Rate)

```
EEMR = (daily_rate × 313) ÷ 12
```

313 is the standard number of working days per year under Philippine labor law,
stored as a configurable value in `payroll_policy_version.eemr_days_per_year`.

### SSS

- Tiered bracket lookup against `sss_bracket` table
- `salary_from ≤ EEMR ≤ salary_to` → use that bracket's `employee_share` and `employer_share`
- If EEMR exceeds all brackets, the last bracket's share is applied

### PhilHealth

```
Employee share = EEMR × employee_share_decimal  (currently 2%)
Employer share = EEMR × (rate_decimal - employee_share_decimal)  (currently 2%)
Total          = EEMR × rate_decimal  (currently 4%)
```

Optional `basis_floor` and `basis_ceiling` cap the EEMR basis.

### Pag-IBIG

**Fixed model (current demo fixture):**

```
Employee share = ₱200.00
Employer share = ₱200.00
```

**Rate model (alternative):**

```
Share = min(EEMR, basis_ceiling) × rate_decimal
```

Both employee and employer shares are stored in `contribution_record`.
Only the employee share appears as a `deduction` row affecting net pay.

---

## 13. Approval Workflows

### Payroll Approval

```
HR Head creates run (Draft)
    │
    ▼ HR Head triggers compute
Computed
    │
    ▼ HR Head submits
PendingOwnerApproval
    │
    ├──▶ Owner approves ──▶ Approved (immutable; payslips finalized)
    │
    └──▶ Owner returns  ──▶ Returned (HR edits, recomputes, resubmits)

HR Head can cancel at: Draft / Computed / Returned
```

Optimistic locking (`FOR UPDATE`) prevents concurrent status overwrites.

### Request Approval

```
Employee submits ──▶ Pending
                        │
                        ▼ HR Head pre-approves
                    HRApproved
                        │
                        ├──▶ Owner approves ──▶ Approved (side effects applied)
                        │
                        └──▶ Owner returns  ──▶ Returned (back to HR)

HR Head can cancel at: Pending / HRApproved
Owner can cancel at:   HRApproved
```

**Side effects on final Owner approval:**
- Leave → debit `leave_ledger`; check entitlement balance
- Cash Advance → create `cash_advance_history` row with `remaining_balance`
- Overtime → stored for payroll computation; no immediate financial effect

---

## 14. Security Model

| Control | Implementation |
|---|---|
| Password storage | `password_hash()` with bcrypt; `password_needs_rehash()` on login |
| CSRF protection | Token in session; validated on all POST/PUT/PATCH/DELETE requests via `CsrfMiddleware` |
| SQL injection prevention | PDO named parameters throughout; no string interpolation in SQL |
| Role enforcement | `AuthMiddleware::requireRoles()` on every protected route; roles array per route in `web.php` |
| Live RBAC | Session identity re-fetched from DB on each request |
| Session security | MySQL-backed; `HttpOnly` + `SameSite=Lax` + `Secure` (production) cookies; 30-min idle / 12-hr absolute expiry |
| Password reset | Cryptographic token (`random_bytes(32)`); SHA-256 hash stored; 1-hour expiry; one-time use |
| File uploads | Extension validation, OLE signature check, SHA-256 checksum; randomized temp paths outside `public/`; deleted in `finally` block |
| Audit trail | `audit_logs` table — immutable; records every significant action with user ID, IP, user agent, and UUID request ID |
| Payroll immutability | Approved payroll rows blocked from modification at application layer (ADR-0002) |

---

## 15. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Data retrieval | ≤ 1 second under normal load |
| Dashboard load, payroll computation, report generation | ≤ 5 seconds |
| Authentication & authorization | Every protected operation; no bypass |
| Language | English |
| Date format | `MM/DD/YY` |
| Time format | 24-hour |
| Browser support | Chrome, Firefox, Edge |
| Password hashing | Bcrypt (PHP default) |
| Session handling | Secure, MySQL-backed, expiry-enforced |
| Biometric import atomicity | Invalid file must never create a partial import |
| Rounding mode | Half-up to 2 decimal places on all monetary values |

---

## 16. Known Limitations and Planned Enhancements

| Item | Status | Notes |
|---|---|---|
| Rest-day holiday pay | Pending | `isRestDay` hardcoded to `false` in `computeRun()`; higher multipliers (260%, 130%, 150%) not yet applied |
| Income tax deduction | Pending | Schema and policy table ready; `IncomeTax` deduction type exists; no computation implemented (employees below threshold) |
| Report export (PDF/XLSX) | Planned | Export button exists but renders "coming soon"; no Dompdf integration yet |
| Bank disbursement module | Schema only | `bank_disbursement` table created in migration 006; no routes or controller |
| Payslip PDF download | Planned | Print-ready HTML exists; no PDF generation library integrated |
| Mobile responsiveness | Partial | Web-accessible but not fully optimized for mobile |
| Official contribution tables | Pending | Demo fixtures used; SSS brackets and PhilHealth/Pag-IBIG rates need update to current official tables before production |
| Payroll StubController | Legacy | `StubController` and `UserManagementController` still exist but are not wired to live routes |

---

## 17. Development Setup

### Prerequisites

- PHP 8.2+
- MySQL 8.x
- Composer 2
- Apache with `mod_rewrite` (XAMPP recommended for Windows)

### Installation

```bash
# 1. Clone / extract the project to your web root
#    e.g. C:\xampp\htdocs\wbpms

# 2. Install dependencies
composer install

# 3. Copy and configure environment
cp .env.example .env
# Edit .env: set DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, APP_KEY

# 4. Create the database
mysql -u root -e "CREATE DATABASE wbpms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Run migrations
vendor/bin/phinx migrate -c phinx.php

# 6. Seed demo data
vendor/bin/phinx seed:run -c phinx.php

# 7. Start local server (development only)
composer serve
# OR configure Apache virtual host pointing to public/
```

### Apache Configuration (XAMPP)

Add to `httpd-vhosts.conf` or the relevant VirtualHost:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/wbpms/public"
    ServerName wbpms.local
    FallbackResource /index.php
    <Directory "C:/xampp/htdocs/wbpms/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Common Commands

```bash
composer serve          # Start PHP built-in dev server on :8000
composer migrate        # Run pending migrations
composer seed           # Run seeders
composer test           # Run PHPUnit test suite
composer analyse        # Run PHPStan static analysis
```

---

## 18. Demo Credentials

These accounts are created by the seeders. **Never use in production.**

| Role | Email | Password |
|---|---|---|
| Business Owner | `owner@demo.test` | `owner-demo-pass` |
| HR Head | `hrhead@demo.test` | `hrhead-demo-pass` |
| Employee | `employee@demo.test` | `employee-demo-pass` |

---

*This document reflects the system as implemented in the current codebase. For the
original capstone proposal and design artifacts, see `docs/` and `Capstone 2.docx`.*
