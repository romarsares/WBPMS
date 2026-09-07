# Capstone 1 Final Submission — Revision Guide

**Prepared by:** Development Team  
**Date:** September 1, 2026  
**For:** Jiane Arielle B. Gamboa & Almairah D. Maindan  
**Document being revised:** Capstone 1 Final Submission.docx

---

## Overview

This guide lists every section and page in your original capstone document that
needs to be updated to match the actual implemented system. Changes are grouped
by priority so you know what to fix first.

---

## Priority 1 — Must Fix (Factual Errors)

These are factual mistakes that contradict how the system actually works.
Fix these before anything else.

---

### 1. Payroll Cut-Off Period
**Pages:** 3–4 (Project Context), 8 (Purpose and Description)

**Original text says:**
> "cut-off period from Sunday to Friday"
> "an employee's very first salary covers a five-day period from Sunday to Thursday.
> For all subsequent weeks, the salary covers a six-day period from Friday of the
> previous week up to Thursday of the current week."

**What to change:**
The recurring payroll period is **Friday to Thursday** (6 days). Saturday is the
rest day. Salary is released every Friday. The "Sunday to Thursday" first-week
period is historical transition data from when the company started — it is not
the recurring rule used in the system.

**Corrected text:**
> The recurring payroll period runs from Friday to Thursday, with Saturday as the
> rest day. Salary is released every Friday. The system enforces this period
> structure for all payroll computations.

---

### 2. Branch Count and Topology
**Pages:** 2–3 (Project Context)

**Original text says:**
> "main office is located at... Banga... another branch... Surallah...
> Banga branch employs 24 workers... Surallah branch operates with 6 employees."

**What to change:**
The Banga location has **two distinct operational branches** (Construction
Supplies and Auto Supplies), not one. Combined with Surallah, there are
**3 branches** in total served by **2 biometric devices**. The exact branch
names, employee count, and device assignments are configurable master data in
the system — they are not hardcoded values.

**Add this note:**
> Note: The Banga site houses two separate operational branches served by one
> biometric device. The system supports any number of branches, attendance sites,
> and biometric devices configured by authorized HR users. The figures above
> reflect the company's current setup at the time of this study.

---

### 3. Attendance Retrieval Method
**Pages:** 3 (Project Context), 7 (Purpose and Description), 11 (Objectives),
13 (Scope), 71 (Figure 4 caption)

**Original text says:**
> "The attendance logs are downloaded either through a USB connection or through
> the device's network connection."
> "Biometric Integration – the system retrieves employee time-in and time-out
> records from the biometric device."

**What to change:**
The system does **not** connect live to the biometric device. There is no USB
download or network sync. Instead, HR exports the monthly daily-log file from
the device (a `.xls` file) and **uploads it manually** through the system.
The system then parses, validates, and imports the attendance data.

**Corrected text (use everywhere attendance retrieval is described):**
> The HR Head exports the biometric device's monthly attendance log as an `.xls`
> file and uploads it through the system. The system validates the file format,
> parses each employee's time records, and generates a daily attendance timesheet
> automatically.

---

### 4. Role Names
**Pages:** 7–8 (Purpose and Description), 10–12 (Objectives), 13–15 (Scope),
44 (Table 4 — Peopleware), 303–307 (Role and Users normalization tables)

**Original text uses:** `Administrator`

**Correct role names are:**
| Wrong | Correct |
|---|---|
| Administrator | `BusinessOwner` or `HRHead` (depending on context) |
| Employee | `Employee` ✓ (already correct) |

**Rule:** Use `BusinessOwner` when referring to the owner's approval and
monitoring functions. Use `HRHead` when referring to payroll, attendance, and
employee management functions.

---

### 5. Technology Stack
**Pages:** 41–43 (Tables 2–3), 45 (Figure 2 Network Design), 69–70 (Tables 5–6)

**Original text says:** SmartASP.NET hosting, Firebase for real-time sync,
Bootstrap UI framework, Laravel framework.

**What the system actually uses:**

| Original (Wrong) | Actual (Correct) |
|---|---|
| SmartASP.NET hosting | Apache / XAMPP for local deployment |
| Firebase (real-time sync) | MySQL 8.4 with PDO (no Firebase at all) |
| Bootstrap | Custom CSS (no Bootstrap) |
| Laravel | Frameworkless PHP 8.5 (no Laravel, no framework) |
| CodeIgniter / MVC framework | Explicit route table, thin controllers, no framework |

**Update Table 3 (Software Requirements for Running the Web, page 43):**

| Software | Requirement |
|---|---|
| Operating System | Windows 10 or higher |
| Browser | Chrome, Edge, Firefox |
| Web Server | Apache via XAMPP |
| Database | MySQL 8.4 |

**Update Table 6 (Software Requirements for Developing the Web, page 70):**

| Software | Requirements |
|---|---|
| Operating System | Microsoft Windows 10 or higher |
| Database | MySQL 8.4 |
| Web Technologies | HTML, CSS, JavaScript |
| Programming Language | PHP 8.5 |
| IDE | Visual Studio Code |
| Web Browser | Google Chrome / Microsoft Edge |
| Migration Tool | Phinx |
| Testing | PHPUnit 12 |

---

### 6. Network Design Description
**Page 45 (Figure 2 caption/description)**

**Original text says:**
> "the system communicates with cloud-based services, particularly Firebase,
> which supports real-time data synchronization"

**What to change:**
Remove all mention of Firebase. The system uses a local MySQL database accessed
through PHP PDO. There is no cloud service or real-time sync.

**Corrected text:**
> All devices are interconnected via a router. The system stores all data in a
> MySQL database accessed through the web server. The biometric device exports a
> monthly `.xls` attendance file that HR uploads through the system interface.

---

## Priority 2 — Should Fix (Wrong Data Types in Data Dictionary)

These are technical errors in the Data Dictionary (pages 330–341). The column
types listed do not match the actual database.

**Apply these corrections to ALL data dictionary tables (Tables 86–97):**

| Wrong | Correct |
|---|---|
| `Int` for ID columns | `BIGINT UNSIGNED` |
| `Float` for money/amounts | `DECIMAL(12,2)` |
| `Varchar(10)` for time columns | `TIME` |
| `Varchar(20)` for status | `ENUM(...)` with the allowed values listed |
| `Nchar(11)` for contact number | `VARCHAR(30)` |

---

### Table 86 — Employee (Page 330)
Add these missing columns that exist in the actual system:

| Column | Type | Description |
|---|---|---|
| `employee_number` | `VARCHAR(30)` | Unique employee ID code, immutable |
| `employee_type` | `ENUM('Regular','Contractual')` | Employment classification |
| `position` | `VARCHAR(100)` | Job position/title |
| `hire_date` | `DATE` | Date of hire |
| `status` | `ENUM('Active','Inactive','Separated','Archived')` | Employment status |
| `philhealth_number` | `VARCHAR(30)` | PhilHealth ID, nullable |
| `pagibig_number` | `VARCHAR(30)` | Pag-IBIG ID, nullable |
| `tin_number` | `VARCHAR(30)` | Tax ID, nullable |

---

### Table 91 — Attendance (Page 335)
The attendance table in the actual system has more columns than listed.

**Correct the existing columns:**
- `time_in` — change type from `Varchar(10)` to `TIME`
- `time_out` — change type from `Varchar(10)` to `TIME`

**Add these missing columns:**

| Column | Type | Description |
|---|---|---|
| `branch_assignment_id` | `BIGINT UNSIGNED` | Branch at time of attendance |
| `schedule_id` | `BIGINT UNSIGNED` | Work schedule used for calculation |
| `hours_worked_minutes` | `SMALLINT UNSIGNED` | Total minutes worked |
| `late_minutes` | `SMALLINT UNSIGNED` | Minutes arrived late |
| `undertime_minutes` | `SMALLINT UNSIGNED` | Minutes left early |
| `overtime_minutes` | `SMALLINT UNSIGNED` | Minutes worked beyond schedule |
| `status` | `ENUM('Complete','Incomplete','ReviewRequired','Approved')` | Attendance status |
| `source` | `ENUM('xls_import','manual')` | How the record was created |
| `import_batch_id` | `BIGINT UNSIGNED` | Links to the upload batch, nullable |

---

### Table 92 — Payroll (Page 336)
The actual payroll table has more columns for immutability and snapshots.

**Add these missing columns:**

| Column | Type | Description |
|---|---|---|
| `payroll_run_id` | `BIGINT UNSIGNED` | Links to the payroll run |
| `payroll_period_id` | `BIGINT UNSIGNED` | Links to the pay period |
| `branch_assignment_id` | `BIGINT UNSIGNED` | Branch assignment snapshot |
| `daily_rate_snapshot` | `DECIMAL(12,2)` | Rate at time of payroll (immutable) |
| `gross_pay` | `DECIMAL(12,2)` | Total before deductions |
| `total_deductions` | `DECIMAL(12,2)` | Sum of all deductions |
| `net_pay` | `DECIMAL(12,2)` | Final amount |

---

### Table 93 — Salary (Page 337)
**Original:** only `salary_id` and `basic_salary Float`

**Correct columns:**

| Column | Type | Description |
|---|---|---|
| `salary_id` | `BIGINT UNSIGNED` | Primary key |
| `employee_id` | `BIGINT UNSIGNED` | FK to employee |
| `daily_rate` | `DECIMAL(12,2)` | Daily wage rate (not monthly basic salary) |
| `effective_from` | `DATE` | When this rate takes effect |
| `effective_to` | `DATE` | When this rate ends, nullable |
| `status` | `ENUM('Active','Archived')` | Rate status |
| `created_by` | `BIGINT UNSIGNED` | FK to users, nullable |

> Note: The system uses **daily rate**, not a basic monthly salary. This matches
> the company's daily-wage payroll structure.

---

### Table 95 — Benefits (Page 339)
The separate `benefits_tbl` does not exist in the actual system. Government
contributions (SSS, PhilHealth, Pag-IBIG) are handled through these tables
instead:

- `contribution_policy_version` — stores the policy and rates
- `sss_bracket` — SSS salary brackets
- `philhealth_rate` — PhilHealth rate per policy
- `pagibig_rate` — Pag-IBIG rate per policy
- `contribution_record` — per-employee per-payroll contribution record

**Update:** Replace Table 95 with a description of the contribution tables above.

---

### Table 97 — Cash Advance History (Page 341)
The actual system tracks cash advances through the request workflow, not a
standalone cash transaction table.

**Actual tables used:**
- `request` — the cash advance request record
- `cash_advance_request_detail` — amount and reason
- `cash_advance_history` — approved amount, remaining balance, status
- `cash_advance_repayment` — weekly deductions linked to payroll

---

## Priority 3 — Needs Redrawing (ERD Diagrams)

These items require a drawing tool such as draw.io, Lucidchart, or
dbdiagram.io. They cannot be updated by editing text alone.

---

### Figure 163 — Logical Database Model (Page 328)
### Figure 164 — Physical Database Model (Page 329)

The original ERD shows approximately 15 tables. The actual implemented schema
has **30+ tables**. The diagrams need to be completely redrawn.

**New tables to add to the ERD:**

| Group | Tables to Add |
|---|---|
| Organization | `attendance_site`, `biometric_device`, `biometric_device_branch` |
| Employee History | `employee_branch_assignment`, `employee_biometric_enrollment`, `employment_contract_review` |
| Attendance Import | `attendance_import_batch`, `biometric_punch`, `attendance_punch`, `attendance_adjustment`, `attendance_policy_flag` |
| Requests | `request_type`, `request`, `leave_request_detail`, `overtime_request_detail`, `cash_advance_request_detail`, `leave_entitlement`, `leave_ledger`, `cash_advance_history`, `cash_advance_repayment` |
| Payroll | `payroll_policy_version`, `contribution_policy_version`, `sss_bracket`, `philhealth_rate`, `pagibig_rate`, `payroll_period`, `payroll_run`, `payroll`, `payroll_earnings`, `deduction`, `contribution_record`, `payslip` |
| Bank | `bank_details`, `disbursement_batch`, `deposit_slip` |
| System | `role`, `users`, `sessions`, `audit_logs`, `password_reset_challenge` |

---

## Priority 4 — Table 85 Final Relation (Page 327)

The Final Relation table is missing most of the actual tables. Here is the
complete corrected list to replace the original:

| Table | Key Attributes |
|---|---|
| `role` | role_id, role_name |
| `users` | user_id, employee_id, role_id, username, account_email, password_hash, status |
| `sessions` | session_id, expires_at, data |
| `audit_logs` | log_id, user_id (nullable), event_type, action_performed, ip_address, action_at |
| `password_reset_challenge` | challenge_id, user_id, otp_hash, expires_at, attempts_remaining |
| `branch` | branch_id, branch_code, branch_name, location, status |
| `attendance_site` | site_id, site_code, site_name, timezone |
| `biometric_device` | device_id, site_id, device_code, file_format, timezone, status |
| `biometric_device_branch` | device_branch_id, device_id, branch_id, effective_from, effective_to |
| `employee` | employee_id, employee_number, employee_type, first_name, last_name, position, hire_date, status, address_id |
| `address` | address_id, street, province_id, city_id, barangay_id |
| `province` | province_id, province_name |
| `city` | city_id, city_name |
| `barangay` | barangay_id, barangay_name |
| `employee_branch_assignment` | branch_assignment_id, employee_id, branch_id, effective_from, effective_to |
| `employee_biometric_enrollment` | enrollment_id, employee_id, device_id, device_employee_code, effective_from, effective_to |
| `work_schedule` | schedule_id, employee_id, working_days, rest_days, work_start_time, work_end_time, effective_from, effective_to |
| `holiday_calendar` | holiday_id, holiday_date, description, holiday_type, pay_multiplier |
| `attendance_import_batch` | import_batch_id, device_id, uploaded_by, file_checksum, source_year, source_month, parser_version, status |
| `biometric_punch` | punch_id, import_batch_id, device_id, employee_id, device_employee_code, source_local_at, match_status |
| `attendance` | attendance_id, employee_id, branch_assignment_id, schedule_id, attendance_date, time_in, time_out, hours_worked_minutes, late_minutes, undertime_minutes, overtime_minutes, status, source |
| `attendance_punch` | attendance_id, punch_id, evidence_role |
| `attendance_adjustment` | adjustment_id, attendance_id, adjusted_by, old_time_in, new_time_in, old_time_out, new_time_out, reason |
| `request_type` | request_type_id, type_name |
| `request` | request_id, employee_id, request_type_id, reason, status, submitted_at, reviewed_by |
| `leave_request_detail` | request_id, leave_type, start_date, end_date, days_requested |
| `overtime_request_detail` | request_id, overtime_date, start_time, end_time, requested_minutes |
| `cash_advance_request_detail` | request_id, amount |
| `leave_entitlement` | entitlement_id, employee_id, leave_type, leave_year, entitled_days |
| `leave_ledger` | entry_id, entitlement_id, request_id, entry_type, days_delta |
| `cash_advance_history` | history_id, request_id, employee_id, original_amount, remaining_balance, status |
| `salary` | salary_id, employee_id, daily_rate, effective_from, effective_to, status |
| `payroll_policy_version` | policy_id, policy_code, version, effective_from, eemr_days_per_year, status |
| `contribution_policy_version` | contribution_policy_id, policy_code, version, effective_from, status |
| `sss_bracket` | bracket_id, contribution_policy_id, salary_from, salary_to, employee_share, employer_share |
| `philhealth_rate` | rate_id, contribution_policy_id, rate_decimal, employee_share_decimal |
| `pagibig_rate` | rate_id, contribution_policy_id, employee_fixed_amount, employer_fixed_amount |
| `payroll_period` | payroll_period_id, period_start, period_end, pay_date, status |
| `payroll_run` | payroll_run_id, payroll_period_id, branch_id, payroll_policy_id, status, gross_pay, net_pay |
| `payroll` | payroll_id, payroll_run_id, employee_id, branch_assignment_id, salary_id, daily_rate_snapshot, gross_pay, total_deductions, net_pay |
| `payroll_earnings` | earning_id, payroll_id, earning_type, description, amount |
| `deduction` | deduction_id, payroll_id, deduction_type, description, amount |
| `contribution_record` | contribution_id, payroll_id, deduction_id, contribution_type, employee_share, employer_share |
| `payslip` | payslip_id, payroll_id, issue_date, file_path, generated_at |
| `bank_details` | bank_id, employee_id, bank_name, account_number, effective_from, effective_to |
| `disbursement_batch` | batch_id, payroll_period_id, bank_name, cheque_number, cheque_total, status |
| `deposit_slip` | slip_id, batch_id, payroll_id, bank_id, amount, status |

---

## Role Normalization Tables — Corrected Sample Data

Replace the sample data in **Tables 41–44 (pages 303–305)** and
**Tables 45–48 (pages 305–307)** with the following:

**Role — 1NF/2NF/3NF:**

| role_id | role_name |
|---|---|
| 1 | BusinessOwner |
| 2 | HRHead |
| 3 | Employee |

**Users — 1NF/2NF/3NF:**

| user_id | employee_id | username | password_hash | role_id |
|---|---|---|---|---|
| 1 | NULL | owner01 | (hashed) | 1 |
| 2 | NULL | hrhead01 | (hashed) | 2 |
| 3 | 1 | emp001 | (hashed) | 3 |

> Note: The Business Owner account has `employee_id = NULL` because the
> owner does not draw an employee salary and does not need an employee profile.
> Passwords are stored as hashed values using `password_hash()` — never
> plaintext.

---

## Items That Do NOT Need Changing

The following sections are fine and do not require updates:

- Title Page, Endorsement Form, Approval Sheet
- Table of Contents, List of Figures, List of Tables
- Review of Related Literature (pages 15–38) — academic references are unaffected
- Conceptual Framework (Figure 1, page 36) — IPO model is still valid
- Scrum Methodology description (pages 47–48)
- Calendar of Activities and Gantt Chart (pages 49–68)
- All Requirements Documentation screenshots (pages 92–288) — UI wireframes
- Activity Diagrams and Use Case Diagrams (pages 225–288)
- References (pages 342–345)
- Appendices — Resource Persons, Personal Technical Vitae

---

## Quick Checklist

Use this to track your progress:

- [ ] Fix payroll cut-off period (pages 3–4, 8)
- [ ] Add 3-branch / 2-device topology note (pages 2–3)
- [ ] Replace live biometric sync with XLS upload everywhere (pages 3, 7, 11, 13, 71)
- [ ] Replace "Administrator" with correct role names (pages 7–8, 10–15, 44, 303–307)
- [ ] Update Table 3 — remove SmartASP.NET, add XAMPP/Apache (page 43)
- [ ] Update Table 6 — remove Bootstrap/Laravel, add PHP 8.5/Phinx/PHPUnit (page 70)
- [ ] Update Figure 2 Network Design description — remove Firebase (page 45)
- [ ] Correct data types in all Data Dictionary tables (pages 330–341)
- [ ] Add missing columns to Employee, Attendance, Payroll, Salary tables (pages 330–337)
- [ ] Replace Benefits table with Contribution tables description (page 339)
- [ ] Update Final Relation Table 85 with all actual tables (page 327)
- [ ] Update Role normalization sample data (pages 303–305)
- [ ] Update Users normalization sample data (pages 305–307)
- [ ] Redraw Logical Database Model — Figure 163 (page 328) *(requires draw.io)*
- [ ] Redraw Physical Database Model — Figure 164 (page 329) *(requires draw.io)*


---

# Implementation Change Log
## Post-Submission System Updates (September 2026)

**Prepared by:** Development Team  
**Date:** September 7, 2026  
**Purpose:** Documents every system-level change made after the original
Capstone 1 Final Submission. Each entry identifies what changed in the
codebase, which capstone section is affected, and what the revised text
should say. Use this alongside the Priority 1–4 revisions above.

---

## Module 6 — Attendance Management

### ATTN-001 — Payroll Period Pattern Changed to Sunday–Friday

**Capstone sections affected:**
Pages 3–4 (Project Context), requirements documentation, any diagram showing
the payroll calendar.

**What changed:**
The original system used a Friday-to-Thursday (6-day) payroll period enforced
by database CHECK constraints. A new `cutoff_pattern` column was added to
`payroll_period` (migration `20260904000010`) to support both patterns:

| Pattern | Start day | End day | Length | Pay date |
|---|---|---|---|---|
| `LegacyFridayThursday` | Friday | Thursday | 6 days | Friday after end |
| `SundayFriday` (new default) | Sunday | Friday | 5 days | Same as end (Friday) |

Existing periods retain the `LegacyFridayThursday` label. All new periods
use `SundayFriday`. The database enforces each pattern's rules with a single
composite CHECK constraint.

**What to update in the capstone:**
> The standard payroll period runs from Sunday to Friday. Saturday is the
> rest day. Salary is released every Friday, which is also the last day of
> the period. The system validates this structure in the database and enforces
> it on all new payroll periods.

---

### ATTN-002 — Attendance Import Supports Weekly Incremental Uploads

**Capstone sections affected:**
Pages 7, 11, 13 (anywhere attendance upload is described), the system workflow
diagrams.

**What changed:**
The original design assumed HR uploads one complete monthly XLS file at
the end of the month. The actual operational workflow is weekly: the HR Head
exports the biometric device's `.xls` file at the end of each payroll week
(when the file contains all data accumulated so far) and uploads it. This
means:

- Each weekly upload re-includes all days from earlier in the same month.
- Punches already in the database (from a prior upload) are silently skipped
  as duplicates — `isDuplicatePunch()` checks `biometric_punch` by
  `(device_id, device_employee_code, source_local_at)`.
- Days already fully captured are silently skipped in `saveGeneratedAttendance`.
- An **Incomplete** attendance row from the prior week's upload (missing a
  time-out) is **updated in-place** when the new upload supplies both punches.
  This handles the "boundary day" case where the last day of the prior period
  only had a time-in when the previous file was uploaded.

**What to update in the capstone:**
> The HR Head exports the biometric device's monthly attendance log as an
> `.xls` file and uploads it through the system at the end of each payroll
> week. The system parses the file, skips punches already recorded from prior
> uploads of the same month, and adds only the new days' attendance. If a
> prior import left an incomplete day record (only time-in captured), and the
> new upload provides both time-in and time-out for that day, the system
> automatically completes the record.

---

### ATTN-003 — Attendance Import Batch Lifecycle Extended

**Capstone sections affected:**
Data Dictionary (pages 330–341), ERD (pages 328–329), any description of
the attendance upload workflow.

**What changed:**
Migration `20260907000015` extended `attendance_import_batch.status` from
`Processing | Completed | Rejected` to:

```
Processing | Draft | Approved | Completed | Rejected | Cancelled
```

New columns added to `attendance_import_batch`:
`approved_by`, `approved_at`, `cancelled_by`, `cancelled_at`,
`cancellation_reason`

`attendance.status` was also extended to include `Cancelled`.

**Lifecycle flow:**
1. `Processing` — created when HR uploads the file
2. `Draft` — import completed, awaiting HR review; **not visible to payroll**
3. `Approved` — HR has reviewed and approved; **payroll uses these rows**
4. `Cancelled` — HR cancelled the import; rows excluded from payroll
5. `Completed` / `Rejected` — legacy terminal states

**Cancellation behavior:**
When a batch is cancelled via `POST /hr/attendance/import/{id}/cancel`:
- The system checks that the batch is not already used by payroll
- Sets `attendance.status = 'Cancelled'` for all rows in that batch
- Deletes all `biometric_punch` rows belonging to that batch, so the same
  XLS file can be cleanly re-uploaded after cancellation without duplicate
  punch errors

**Re-upload after cancellation:**
When a cancelled file's SHA-256 checksum is re-uploaded, `createImportBatch`
deletes the stale `biometric_punch`, `attendance`, and `attendance_import_batch`
rows (in that FK-safe cascade order) before creating a fresh batch.

**What to add to the Data Dictionary for `attendance_import_batch`:**

| Column | Type | Description |
|---|---|---|
| `approved_by` | `BIGINT UNSIGNED NULL` | FK → users; HR user who approved |
| `approved_at` | `DATETIME NULL` | When the batch was approved |
| `cancelled_by` | `BIGINT UNSIGNED NULL` | FK → users; HR user who cancelled |
| `cancelled_at` | `DATETIME NULL` | When the batch was cancelled |
| `cancellation_reason` | `VARCHAR(255) NULL` | Reason for cancellation |

---

### ATTN-004 — Attendance Filter Uses Month-First Cascade

**Capstone sections affected:**
UI screenshots (pages 92–288 if the attendance filter is shown), system
description.

**What changed:**
The attendance index (`/hr/attendance`) has two filter modes: "By Month" and
"By Cut-off Period." In the cut-off mode, a **Month selector** now appears
before the period dropdown. Selecting a month filters the period dropdown via
JavaScript to show only the payroll weeks that start in that month. This
prevents HR from accidentally selecting a period from the wrong month when
there are many periods in the dropdown.

---

## Module 8 — Payroll Management

### PAYR-001 — Payroll Run Can Be Cancelled and Replaced

**Capstone sections affected:**
Pages 8 (Purpose), requirements for payroll workflow, state diagrams if any
show payroll run lifecycle, Data Dictionary for `payroll_run`.

**What changed:**
Migration `20260907000014` added a `Cancelled` status to `payroll_run` and
introduced the `active_run_marker` mechanism:

| Column | Type | Purpose |
|---|---|---|
| `status` | ENUM (now includes `Cancelled`) | Run lifecycle state |
| `active_run_marker` | `TINYINT UNSIGNED NULL DEFAULT 1` | Set to 1 for active runs, NULL on cancellation |
| `cancellation_reason` | `VARCHAR(1000) NULL` | Required when cancelling |
| `cancelled_by` | `BIGINT UNSIGNED NULL` | FK → users |
| `cancelled_at` | `DATETIME NULL` | Cancellation timestamp |

The unique index `uq_active_run_period_branch (payroll_period_id, branch_id, active_run_marker)`
replaces the old `uq_run_period_branch`. Because MySQL treats NULL values as
distinct in unique indexes, cancelled runs (`marker=NULL`) do not block new
runs for the same period and branch.

**Cancellation cascade:**
When a payroll run is cancelled, its child records are deleted in this order:
`contribution_record` → `deduction` → `payroll_earnings` → `payslip` → `payroll`.
The `payroll_run` row itself is kept for history but excluded from all active
payroll operations.

**What to add to the capstone payroll workflow description:**
> An unapproved payroll run (Draft, Computed, or Returned) may be cancelled
> by the HR Head with a required reason. Cancelling the run removes its
> computed payroll records and releases the period/branch slot so a corrected
> run can be created. Approved payroll runs are immutable and cannot be
> cancelled.

**Updated `payroll_run.status` ENUM to document:**
`Draft | Computed | PendingOwnerApproval | Approved | Returned | Cancelled`

---

### PAYR-002 — Payroll Computation Eligibility Requires a Salary Record

**Capstone sections affected:**
Pages 8, 11, any description of which employees appear in a payroll run.

**What changed:**
The payroll eligibility query (`PayrollService::computeRun`) uses an INNER
JOIN on the `salary` table. An employee will only appear in a payroll run if
they have an **active salary record** (`salary.status = 'Active'`) with
`effective_from ≤ period_start`.

This means:
- Employees with attendance records but no salary record configured in the
  system will **not appear** in the computed payroll.
- HR must set up a daily rate in **Manage Salary** for each employee before
  their first payroll run.

**What to add to the capstone:**
> Before computing payroll, the HR Head must ensure that each employee has
> an active salary record with a daily rate effective from on or before the
> payroll period start date. Employees without a salary record are
> automatically excluded from the payroll computation even if they have
> attendance records.

---

### PAYR-003 — Duplicate Payroll Guard Added

**Capstone sections affected:**
Payroll computation description.

**What changed:**
`PayrollService::assertEmployeesAreNotAlreadyPaidInAnotherRun()` was
implemented as a guard that runs before the payroll transaction opens.
It checks whether any employee in the current run already has a payroll row
in a **different, non-cancelled run** for the same `payroll_period_id`.

If a conflict is found, computation is blocked with:
> "One or more employees in this run have already been paid under a different
> active payroll run for the same period. Cancel or resolve that run before
> computing this one."

This prevents the `uq_payroll_period_employee` unique constraint violation
that would otherwise surface as a raw database error.

---

### PAYR-004 — Payroll Period Form Uses Month-First Cascade

**Capstone sections affected:**
UI screenshots for the "New Payroll Run" form, system description.

**What changed:**
The "New Payroll Run" form (`/hr/payroll/create`) now shows a **Month**
dropdown before the payroll period dropdown. Selecting a month filters the
payroll period options to only show cut-offs whose `period_start` falls in
that month. This prevents selecting the wrong week when many periods exist.

---

### PAYR-005 — Government Contributions Deducted on Last Friday of Month Only

**Capstone sections affected:**
Pages 8, 11, payroll calculation description.

**What changed (confirmed implemented):**
`PayrollService::isMonthlyContributionCutoff()` checks whether the
`pay_date` of a payroll run is the **last Friday of its calendar month**
(Asia/Manila timezone). SSS, PhilHealth, and Pag-IBIG employee deductions
are only computed for that one run per month. All other weekly runs for the
same month compute payroll without government contribution deductions.

**EEMR basis:**
Government contributions use the employee's Estimated Equivalent Monthly
Rate calculated as `(daily_rate × 313) ÷ 12`. This is not the variable
weekly earnings — it is the stable monthly basis per the Final Defense
Reviewer guidance.

---

## Schema Changes Reference — Updated Table Additions

### Updated `payroll_run` Data Dictionary Entry

Add these columns (not in original capstone):

| Column | Type | Description |
|---|---|---|
| `active_run_marker` | `TINYINT UNSIGNED NULL` | 1 = active, NULL = cancelled; enforces one active run per period/branch |
| `cancellation_reason` | `VARCHAR(1000) NULL` | Required reason when cancelling |
| `cancelled_by` | `BIGINT UNSIGNED NULL` | FK → users; who cancelled |
| `cancelled_at` | `DATETIME NULL` | When cancelled |

Update `status` ENUM to:
`'Draft' | 'Computed' | 'PendingOwnerApproval' | 'Approved' | 'Returned' | 'Cancelled'`

---

### New Table: `payroll_adjustment` (migration 20260906000012)

Not in original capstone. Add to Table 85 (Final Relation) and ERD.

| Column | Type | Description |
|---|---|---|
| `adjustment_id` | `BIGINT UNSIGNED` PK | Auto-increment |
| `payroll_run_id` | `BIGINT UNSIGNED` FK | The run being adjusted |
| `payroll_id` | `BIGINT UNSIGNED` FK | The employee payroll row |
| `earning_id` | `BIGINT UNSIGNED NULL` FK | Target earning line, if earning adjustment |
| `deduction_id` | `BIGINT UNSIGNED NULL` FK | Target deduction line, if deduction adjustment |
| `adjustment_type` | `ENUM('earning','deduction')` | Type of adjustment |
| `amount_before` | `DECIMAL(12,2)` | Original amount |
| `amount_after` | `DECIMAL(12,2)` | Adjusted amount |
| `reason` | `VARCHAR(500)` | HR explanation |
| `adjusted_by` | `BIGINT UNSIGNED NULL` FK | HR user who made the change |
| `adjusted_at` | `DATETIME` | When the adjustment was made |

> Note: A payroll run with manual adjustments cannot be recomputed. HR must
> review the adjusted amounts before submitting for approval.

---

### New Table: `employee_document` (migration 20260907000013)

Not in original capstone. Add to Table 85 (Final Relation) and ERD.

| Column | Type | Description |
|---|---|---|
| `document_id` | `BIGINT UNSIGNED` PK | Auto-increment |
| `employee_id` | `BIGINT UNSIGNED` FK | Owner employee |
| `document_type` | ENUM | `IDPhoto \| EmploymentContract \| GovernmentID \| TaxForm \| BankProof \| SeparationDocument \| RehireDocument \| Other` |
| `original_filename` | `VARCHAR(255)` | Client-side filename (evidence) |
| `stored_path` | `VARCHAR(500)` | Server path relative to APP_ROOT (never public) |
| `sha256` | `CHAR(64)` | File checksum |
| `mime_type` | `VARCHAR(100)` | Validated MIME type |
| `file_size_bytes` | `BIGINT UNSIGNED` | File size |
| `status` | `ENUM('Current','Superseded','Archived')` | Document lifecycle |
| `replaces_document_id` | `BIGINT UNSIGNED NULL` FK | Self-reference for version chain |
| `verified_by` | `BIGINT UNSIGNED NULL` FK | HR user who verified |
| `verified_at` | `DATETIME NULL` | Verification timestamp |
| `uploaded_by` | `BIGINT UNSIGNED NULL` FK | HR user who uploaded |
| `notes` | `VARCHAR(500) NULL` | Optional notes |

---

### New Tables: `employee_employment_episode` and `employee_lifecycle_event` (migration 20260903000009)

Not in original capstone. Add to Table 85 and ERD.

**`employee_employment_episode`** — tracks continuous employment windows:
- `episode_id`, `employee_id` FK, `start_date`, `end_date NULL`, `start_reason ENUM('Hire','Rehire')`

**`employee_lifecycle_event`** — append-only audit of archive/rehire actions:
- `event_id`, `employee_id` FK, `event_type ENUM('Archive','Rehire')`, `event_date`, `reason`, `performed_by` FK → users

---

### New Table: `job_position` (migration 20260901000007)

Not in original capstone.

- `position_id`, `position_title VARCHAR(100) UNIQUE`, `status ENUM('Active','Inactive')`
- Provides a managed dropdown for the employee form. `employee.position` remains a VARCHAR
  snapshot for historical immutability.

---

### Updated `work_schedule` Table (migration 20260902000007)

The original `work_schedule` was per-employee (had `employee_id` FK). It was
refactored to become a **reusable schedule template** — `employee_id` was
removed and `schedule_name VARCHAR(100) UNIQUE` was added.

New columns added: `grace_minutes`, `overtime_allowed`, `break_start_time`,
`break_end_time`, `notes`.

A new table `employee_schedule_assignment` was created to replace the direct
FK (effective-dated, UQ on `(employee_id, effective_from)`).

**Update the capstone Data Dictionary for `work_schedule`:**
- Remove `employee_id` FK
- Add `schedule_name VARCHAR(100) UNIQUE`
- Note that employee-to-schedule mapping is now in `employee_schedule_assignment`

---

### Updated `users` Table (migration 20260903000008)

Add column: `requires_password_change BOOLEAN NOT NULL DEFAULT FALSE`

Set to `TRUE` for auto-provisioned employee accounts. The system forces a
password change on first login before any other module is accessible.

---

### Updated `employee` Table (migration 20260907000014 — add_sss_number)

Add column: `sss_number VARCHAR(30) NULL` — SSS ID number, alongside the
existing `philhealth_number`, `pagibig_number`, and `tin_number`.

---

## Updated Quick Checklist (Additions)

Add these items to the checklist from Priority 1–4:

- [ ] Update payroll period description — change to Sunday–Friday with Friday pay date (pages 3–4, 8)
- [ ] Update attendance upload workflow — add weekly incremental upload + boundary-day completion (pages 7, 11, 13)
- [ ] Update `attendance_import_batch` Data Dictionary — add 5 new columns + expanded status ENUM (page 335)
- [ ] Update `attendance.status` ENUM — add `Cancelled` value (page 335)
- [ ] Update payroll workflow — add Cancelled state, active_run_marker, replacement run flow (pages 8, 11)
- [ ] Update `payroll_run` Data Dictionary — add 4 new columns + expanded status ENUM (page 336)
- [ ] Add note that employees without a salary record are excluded from payroll computation (page 8, 11)
- [ ] Add `payroll_adjustment` to Table 85, ERD, and Data Dictionary (page 327–329)
- [ ] Add `employee_document` to Table 85, ERD, and Data Dictionary (page 327–329)
- [ ] Add `employee_employment_episode` and `employee_lifecycle_event` to Table 85 and ERD (page 327–329)
- [ ] Add `job_position` to Table 85 and ERD (page 327–329)
- [ ] Update `work_schedule` — remove `employee_id`, add `schedule_name`; add `employee_schedule_assignment` (page 327–329)
- [ ] Add `requires_password_change` to `users` Data Dictionary (page 330)
- [ ] Add `sss_number` to `employee` Data Dictionary (page 330)
- [ ] Add note about SSS, PhilHealth, Pag-IBIG deducted only on last Friday of month (payroll calculation section)
