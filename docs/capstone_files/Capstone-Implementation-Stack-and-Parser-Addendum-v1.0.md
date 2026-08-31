# Capstone Implementation Stack and Attendance Parser Addendum v1.0

**Status:** Accepted implementation correction

**Date:** 2026-08-31

**Applies to:** the Web-Based Payroll Management System (WBPMS) capstone
proposal and its canonical schema addendum

## Purpose

The original capstone identifies PHP as the server-side direction. This
addendum records the concrete PHP implementation baseline and the verified
biometric attendance-import design needed to implement the proposal without
changing its approved business rules, database model, or source evidence.

The original PDF submission, final-defense review, HR follow-up answers, and
sample biometric workbooks remain immutable source evidence. This addendum and
the linked ADRs document implementation-level corrections and extensions.

## Accepted PHP implementation baseline

| Area | Accepted implementation |
|---|---|
| Server-side runtime | PHP 8.5 with Composer 2 |
| Application style | Frameworkless PHP: front controller, explicit routes, thin controllers, application/domain services, repositories, and native PHP templates |
| Framework/ORM | No Laravel, no full-stack framework, and no ORM |
| Database access | PDO MySQL prepared statements with explicit transactions |
| Schema delivery | MySQL 8.4 plus standalone Phinx migrations and seeders |
| Authentication | `password_hash()`/`password_verify()`; native PHP sessions stored in MySQL by a project-owned `SessionHandlerInterface` |
| Browser protection | `HttpOnly`, `SameSite=Lax`, and production `Secure` cookies; CSRF tokens for state-changing requests |
| Testing and analysis | PHPUnit 12 and PHPStan |
| Presentation | Escaped native PHP templates with small progressive JavaScript enhancements |
| Deployment | Nginx or Apache with PHP-FPM and `public/` as the sole web document root |

This is frameworkless, not dependency-free. Composer packages are allowed only
for specialized concerns that would be risky to reimplement, including legacy
Excel decoding, migrations, PDF rendering, and email delivery.

## Verified biometric workbook parser

The supplied daily logs are OLE/BIFF `.xls` workbooks, not `.dat` transaction
streams. The MVP accepts only this documented format.

```text
HR upload
  -> file/security validation
  -> PhpSpreadsheet Reader\Xls
  -> LdeXlsDailyLogParser (pure DTO/error output)
  -> AttendanceImportService (one database transaction)
  -> immutable punch evidence and generated timesheets
```

`phpoffice/phpspreadsheet` is used only to decode the legacy XLS format. The
project-owned parser is `LdeXlsDailyLogParser`, with the persisted version
identifier `LDE_XLS_DAILY_LOG_V1`.

The parser requires one worksheet with these fixed columns:

```text
Dept | User ID | Name | Enroll ID | MM/DD ddd | ...
```

It requires HR to select the registered device, source year, and source month.
It validates each date header, preserves `Enroll ID` as a string including
leading zeroes, expands every valid space-separated `HH:mm` token, and retains
the source row, date column, original cell value, department, user ID, and name
as immutable evidence.

The parser does not infer time-in/time-out, employee identity, branch, or
payroll treatment. Those decisions occur after parsing: the import service
matches source device + enrollment code + punch time against effective
enrollment and branch history, then the timesheet generator applies the
one/two/multiple-punch attendance rules.

## Import controls and auditability

Before parsing, the system requires the `.xls` extension, validates the OLE
compound-file signature, computes SHA-256, stores uploads temporarily outside
the public directory with randomized names, and enforces configured size,
worksheet, row, column, and token limits. MIME detection is supplementary only.
Formula-bearing, corrupt, encrypted, unexpected, or structurally invalid files
are rejected with safe row/column-scoped error codes and no partial import.

Each accepted import records its checksum, device, HR-selected source year and
month, parser version, lifecycle status, uploader, timestamp, and outcome
counts. Raw punches are immutable. Unmatched and branch-coverage exceptions
remain available for HR reconciliation; they are never silently discarded or
auto-assigned. The complete import either commits as one transaction or rolls
back.

## Scope and traceability

This addendum changes implementation technology and parser controls only. It
does not alter the previously approved Friday-to-Thursday payroll cycle,
contribution schedule, holiday formulas, employee categories, Owner role, or
canonical schema relationships.

The authoritative detailed records are:

- [ADR-0001 development baseline](../adr/0001-development-baseline.md)
- [ADR-0002 schema integrity corrections](../adr/0002-schema-integrity-corrections.md)
- [ADR-0003 frameworkless PHP and XLS parser](../adr/0003-frameworkless-php-and-xls-parser.md)
- [Canonical database schema v1.1](Canonical-Database-Schema-v1.1.md)
- [Requirements](../requirements.md)
- [Design](../design.md)
