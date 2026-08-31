# Web-Based Payroll Management System for Light Diamond Enterprises

## Revised Capstone Final

**Version:** 1.1 (implementation-aligned revision)
**Date:** 2026-08-31
**Status:** Editable revised capstone document
**Source basis:** Original Capstone Final Submission, HR follow-up answers,
final-defense review, approved ADRs 0001–0003, requirements, design, technical
stack, canonical schema v1.1, implementation plan, and current Developer B
attendance/user-flow scaffold.

> This is a revised, editable successor prepared from the project
> documentation. It does not alter the original PDF submission, which remains
> immutable source evidence.

## Abstract

Light Diamond Enterprises currently performs multi-branch payroll and
attendance reconciliation manually through spreadsheets. The process is slow,
hard to audit, and vulnerable to inconsistent calculations, especially when
biometric attendance, employee transfers, government contributions, requests,
and payroll approval are handled separately.

The proposed Web-Based Payroll Management System (WBPMS) is a browser-based,
role-controlled information system that centralizes employee master data,
effective work schedules, biometric attendance imports, requests, payroll
computation, approvals, payslips, and reports. HR uploads the biometric
device's monthly legacy `.xls` daily-log export; the system validates the
document, preserves each raw punch as evidence, resolves the employee and
historical branch at the punch time, generates a daily timesheet, and makes
the resulting attendance available to payroll.

The implementation adopts frameworkless PHP 8.5, MySQL 8.4, PDO, Phinx,
PhpSpreadsheet, native server-rendered views, PHPUnit, and PHPStan. The design
uses explicit transactions, effective-dated records, immutable payroll and
attendance evidence, role-based access control, and audit logging to make
payroll processing reproducible and reviewable.

## 1. Background and Problem Statement

Light Diamond Enterprises needs a consistent way to manage payroll across its
operational branches. The current spreadsheet workflow takes roughly a week
per pay cycle and requires manual reconciliation of biometric attendance,
salary data, requests, deductions, and statutory contributions. This creates
four operational problems:

1. Manual calculations can be delayed, inconsistent, or difficult to verify.
2. Raw biometric data is not reliably connected to employee, schedule, and
   branch history.
3. Employees have no self-service access to their attendance, requests, or
   payslips.
4. Management approval and the reason for changes are not consistently
   captured as durable system evidence.

The system therefore replaces manual spreadsheet reconciliation with a
controlled web workflow while retaining HR review wherever the source data is
incomplete, unmatched, or exceptional.

## 2. Project Goal and Objectives

### Goal

Develop a web-based payroll management system that integrates biometric
attendance imports with employee, schedule, request, contribution, payroll,
approval, payslip, and report records for Light Diamond Enterprises.

### Objectives

1. Maintain centralized employee, branch, schedule, and biometric-enrollment
   records with effective dates.
2. Import and validate monthly legacy biometric `.xls` daily logs without a
   live device integration.
3. Generate daily attendance records and calculate worked hours, lateness,
   undertime, and overtime from the effective schedule.
4. Compute payroll from attendance, salary history, approved requests, and
   approved contribution policies.
5. Route payroll from HR preparation to Business Owner approval or return for
   revision.
6. Give employees secure access to only their own attendance, requests, and
   payslips.
7. Produce auditable payroll, attendance, request, contribution, and
   thirteenth-month reports.

## 3. Scope and Delimitations

### Included scope

- Authentication, password recovery, and role-based access control.
- Employee, branch, schedule, holiday, and biometric-device/enrollment
  management.
- Attendance import from an approved legacy `.xls` daily-log format.
- Leave, overtime, and cash-advance request workflow.
- Salary history, approved contribution policies, payroll computation, owner
  approval, payslips, and reports.
- Employee self-service views for attendance, requests, and payslips.
- Audit logging, safe validation errors, and automated testing.

### Delimitations

- The MVP has no mobile native application, live biometric device API, bank
  upload, ATM payroll integration, or multi-country payroll rules.
- The BDO process remains a printable preparation-list workflow: one weekly
  aggregate cheque and manually prepared employee deposit slips.
- Manual attendance adjustment is an auditable HR function; it never replaces
  the immutable imported punch evidence.
- Official branch display names and the final employee distribution are
  configurable master data, not hardcoded assumptions.

## 4. Users and Access Boundaries

| Role | Primary system responsibility |
|---|---|
| Business Owner | Reviews, approves, or returns payroll; sees authorized cross-branch summaries and reports. |
| HR Head | Manages people, schedules, attendance, requests, salary, policies, payroll preparation, and reports. |
| Employee | Views only personal attendance, requests, and payslips; submits personal requests. |

The Business Owner cannot operate HR attendance/payroll maintenance screens.
The HR Head cannot give final approval to their own payroll computation. An
employee’s portal queries are always scoped to the authenticated
`session.employee_id`; a URL parameter must never permit access to another
employee's attendance, requests, or payslip.

## 5. Functional Solution

### 5.1 Employee and schedule history

The system stores one employee identity and preserves its history through
effective-dated branch assignments, schedules, salary structures, and
biometric enrollments. A transfer closes the previous branch assignment and
opens a non-overlapping destination assignment. Historical attendance and
payroll are not rewritten. Attendance uses the branch assignment effective at
the punch timestamp, while payroll membership uses the assignment effective at
the start of the payroll period.

### 5.2 Attendance import and timesheet generation

Attendance is an HR-uploaded legacy OLE/BIFF `.xls` workbook, not a live device
feed. HR selects the registered device and the source year/month. The upload
boundary verifies file extension, OLE signature, SHA-256, a randomized private
temporary path, and resource limits before parsing.

The versioned parser is `LDE_XLS_DAILY_LOG_V1`. It requires one worksheet with
the columns `Dept`, `User ID`, `Name`, `Enroll ID`, then `MM/DD ddd` date
columns. It preserves a leading-zero `Enroll ID` as text, validates the date
headers against the HR-selected source period, rejects formulas and unsupported
structures, and expands every whitespace-separated `HH:mm` token. Each parsed
punch retains its source row, column, raw cell value, source department, user
ID, and name as immutable evidence.

After parsing, one transaction:

```text
create import batch
  → match device + Enroll ID + punch time to effective enrollment
  → resolve employee branch at that timestamp and validate device coverage
  → retain matched, unmatched, coverage-exception, and duplicate evidence
  → generate one attendance row per employee/date
  → link every raw punch to that attendance row
  → save branch-grouped import totals or roll back everything
```

One punch creates an incomplete attendance entry. Two punches use the earliest
as time-in and latest as time-out. More than two punches retain all evidence
and require HR review; intermediate punches are never silently discarded.
Duplicate files are detected by SHA-256 and duplicate raw punches by device,
enrollment code, and local timestamp. Unmatched enrollments and branch-coverage
exceptions remain available to HR rather than being automatically assigned.

### 5.3 Attendance calculation

For a complete entry, the effective schedule determines the expected start,
end, break, and standard minutes. Calculations use integer minutes:

```text
late minutes       = max(0, actual time-in − expected start)
undertime minutes  = max(0, expected end − actual time-out)
worked minutes     = actual time-out − actual time-in − break minutes
overtime minutes   = max(0, actual time-out − expected end)
```

Incomplete and multi-punch records remain visible in an HR review list. The
MVP supports HR adjustments only through an auditable adjustment record that
stores the actor, reason, old values, and new values.

### 5.4 Requests, payroll, and approval

Employees can submit leave, overtime, and cash-advance requests. Requests are
created as `Pending`; employees can update or cancel them only while pending.
HR approves or rejects them. Sick leave defaults to four paid days per year and
an insufficient balance blocks submission.

The recurring payroll period runs Friday through Thursday; Saturday is the
rest day. HR closes attendance on Thursday, the Business Owner reviews on
Friday morning, and approved funds/payslips are released Friday afternoon.
The initial Sunday-through-Thursday biometric period is historical transition
data and is not a second recurring calendar rule.

Payroll is derived—not manually entered—from attendance, salary history,
approved requests, and approved contribution policies. The payroll run is the
single state authority:

```text
Draft → Computed → PendingOwnerApproval → Approved
                          └────────────→ Returned → Computed
```

Approval is protected against duplicate approval, and approved payroll
calculation inputs and itemized results are immutable snapshots. Statutory
contribution logic is policy-versioned; the MVP uses only documented approved
fixture data and rejects unsupported policy combinations rather than inventing
brackets.

## 6. System Architecture and Technology

The solution follows a three-tier architecture:

```text
Browser (native PHP views, HTML/CSS, progressive JavaScript)
                         │ HTTP
                         ▼
public/index.php → router → thin controllers → application/domain services
                                                │
                                                ▼
                                      PDO repositories / transactions
                                                │
                                                ▼
                                         MySQL 8.4 / InnoDB
```

| Area | Approved implementation choice |
|---|---|
| Runtime | PHP 8.5, strict types, PSR-4 namespaces |
| Web style | Frameworkless front controller and explicit route table |
| Persistence | PDO prepared statements and explicit transactions; no ORM |
| Database | MySQL 8.4 LTS, InnoDB, `utf8mb4`, strict SQL mode |
| Migrations | Phinx migrations and seeders |
| XLS decoding | `phpoffice/phpspreadsheet`, explicit `Reader\Xls`, data-only mode |
| Authentication | `password_hash`, MySQL-backed native PHP sessions, CSRF-protected forms |
| Testing | PHPUnit 12, PHPStan, integration tests, browser smoke tests |
| Reporting | Print-ready HTML first; PDF/CSV through adapters |

Only `public/` is web-accessible. Uploads, payslips, logs, and secrets are
kept outside it. Cookies use `HttpOnly` and `SameSite=Lax`; production cookies
also use `Secure`. Business time is Asia/Manila, while stored instants use UTC.
Dates display as `MM/DD/YY` and time as 24-hour `HH:mm`.

## 7. Canonical Data Model and Integrity Rules

The original source models disagreed on important relationships. This revision
uses the approved canonical schema v1.1 as the migration authority.

Key entity groups are:

| Domain | Core entities |
|---|---|
| Identity and audit | role, users, sessions, password reset, audit logs |
| Organization | branch, attendance site, biometric device, device coverage |
| Employee history | employee, branch assignment, biometric enrollment, work schedule, holiday calendar |
| Attendance | import batch, raw biometric punch, attendance, attendance-punch lineage, adjustment, policy flag |
| Requests | request, leave/overtime/cash-advance details, leave entitlement and ledger |
| Payroll | salary history, contribution policies/brackets, payroll period/run/detail, earnings, deductions, contributions, payslip |
| Reporting/disbursement | report records, printable preparation list, cheque and deposit-slip records |

The most important data safeguards are:

- one generated attendance row per employee and date;
- exact lineage from every included raw punch to attendance;
- unique file checksum and unique device/enrollment/local timestamp punch
  evidence;
- non-overlapping effective branch, schedule, and enrollment periods;
- immutable historical payroll and attendance records;
- `RESTRICT` for historical parent deletion and archiving rather than hard
  deletion;
- audit records for significant create, update, approval, archive, access, and
  adjustment actions.

The complete typed table dictionary, foreign keys, checks, enums, and indexes
are maintained separately in **Canonical Database Schema v1.1** to keep this
capstone document readable while preserving migration-level precision.

## 8. Security, Quality, and Verification

The application uses authentication, role guards, CSRF protection, escaped
templates, prepared SQL, safe error envelopes, password hashing, private file
storage, and audit logs. Validation errors identify a stable error code and
safe workbook location where appropriate; they never expose a server path or
stack trace.

The minimum acceptance suite includes:

| Area | Required verification |
|---|---|
| Access control | Valid login, invalid-login handling, role denial, Employee A denied from Employee B data |
| XLS import | Valid workbook, leading-zero code, bad extension/signature, formula, invalid header/time, duplicate, unmatched, rollback |
| Attendance | On-time, late, undertime, overtime, one-punch incomplete, multi-punch review |
| Payroll | Golden gross/net cases, contribution boundary cases, period/branch uniqueness, immutable approval state |
| Persistence | Repeatable migrate/seed and transaction rollback |
| User journey | HR setup → import → review → payroll → owner approval → employee payslip access |

The performance targets are normal retrieval within one second and dashboard,
payroll computation, and report generation within five seconds. The supported
browsers are Chrome, Firefox, and Edge.

## 9. Implementation Status and Delivery Plan

The repository now contains an initial Developer B attendance and user-flow
scaffold: strict parser DTOs and errors, XLS fixture tests, attendance
timesheet calculations, transaction/repository contracts, employee setup
contracts, HR templates, employee portal templates, and an employee-ownership
guard. It is intentionally separated from the shared database, authentication,
upload-boundary, and payroll foundation so each integration responsibility is
explicit.

The P0 delivery sequence is:

1. Scaffold the shared PHP runtime, environment, migrations, seeders,
   authentication, RBAC, transaction helper, and CI checks.
2. Wire employee, branch, schedule, device, and enrollment persistence to the
   attendance setup forms.
3. Connect the secure upload boundary to the parser, then complete matching,
   raw-punch persistence, attendance generation, and HR review.
4. Integrate the generated attendance read model with payroll calculation,
   owner approval, and employee payslip access.
5. Perform clean-database, three-role, RBAC, browser, and acceptance testing.

## 10. Conclusion

WBPMS provides Light Diamond Enterprises with a practical path from manual,
spreadsheet-driven payroll to a controlled, auditable workflow. The revised
design resolves the prior ambiguity around branch/device topology, attendance
lineage, effective-dated history, payroll membership, and implementation
technology. It keeps HR judgment for exceptions while making routine imports,
calculations, approvals, and employee self-service consistent and traceable.

## Document References

- [Product overview](../product.md)
- [Requirements and acceptance criteria](../requirements.md)
- [System design](../design.md)
- [Technology stack](../tech.md)
- [Implementation tasks](../tasks.md)
- [Five-day MVP roadmap](../five-day-development-roadmap.md)
- [Canonical database schema v1.1](Canonical-Database-Schema-v1.1.md)
- [Frameworkless PHP and strict XLS parser ADR](../adr/0003-frameworkless-php-and-xls-parser.md)
- [Developer B start record](../developer-b-worklog.md)
