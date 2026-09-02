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
