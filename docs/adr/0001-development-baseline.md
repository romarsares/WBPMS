# ADR-0001: Documentation-Grounded Development Baseline

- **Status:** Accepted for MVP development; schema details amended by ADR-0002
  and technical implementation decisions superseded by ADR-0003
- **Date:** 2026-08-31
- **Branch:** `WBPMS-dev`
- **Decision basis:** repository documentation, supplemental HR answers,
  Final Defense Reviewer notes, and the three supplied biometric workbooks
- **Production caveat:** this ADR authorizes implementation of a traceable
  MVP baseline. It does not certify statutory contribution tables or replace
  final HR review of production master data.

## Context

> **Amendment:** [ADR-0002](0002-schema-integrity-corrections.md) preserves
> these business decisions and replaces incomplete/ambiguous relational details
> with the typed
> [v1.1 capstone schema addendum](../capstone_files/Canonical-Database-Schema-v1.1.md).
>
> **Technology amendment:**
> [ADR-0003](0003-frameworkless-php-and-xls-parser.md) replaces the original
> Node.js/Express/Sequelize/SheetJS selection with frameworkless PHP,
> Composer, PDO, Phinx, and a strict PhpSpreadsheet-backed `.xls` adapter.

The original capstone contains conflicting database representations and a
mix of generic and later operational requirements. The repository also
contained a blocking decision checklist even though several questions can
now be resolved from the later HR answers and real biometric samples.

This ADR freezes one coherent development baseline. When historical source
material conflicts, implementation follows this order:

1. explicit supplemental HR answers and reviewed operational calculations;
2. accepted requirements in `docs/requirements.md`;
3. the canonical model in `docs/design.md`;
4. Figures 163–164 as the preferred historical database shape;
5. Table 85 and the partial Data Dictionary as audit evidence only.

`docs/` is the canonical documentation location. Files under `.kiro/` are
mirrors for development tooling and must not independently redefine rules.

## Business and schema decisions

### Organization and branch topology

- Branches, attendance sites, biometric devices, and device-to-branch
  coverage are configurable master data; no count is hardcoded.
- The MVP demo uses three configurable branch records representing the
  documented Banga construction operation, the adjacent Banga auto-supplies
  operation, and Surallah. Display names are demo data until HR confirms the
  official legal/operating names.
- Two attendance sites/devices are used in the demo topology. The Banga
  device may cover both Banga branches, proving that a device is not the same
  thing as an organizational branch.
- `employee_branch_assignment` is effective-dated. An employee has exactly
  one assignment on any date, and transfers never rewrite history.

### Employees and users

- Required employee fields for the MVP are `employee_number`, `employee_type`,
  `first_name`, `last_name`, `hire_date`, `position`, `status`, and an initial
  branch assignment.
- `employee_number` is unique and immutable. Email is optional and unique
  when present. Middle initial, contact details, birthdate, picture, address,
  and government identifiers are optional until production data is supplied.
- `employee_type` is `Regular` or `Contractual`. Contractual records carry a
  six-month review date and an HR-recorded outcome.
- A user has at most one employee link. `users.employee_id` is nullable so
  the non-salaried Business Owner is a user without an employee/payroll row.
- Archiving changes status and preserves referenced historical records.

### Payroll ownership and lifecycle

- The canonical hierarchy is `payroll_period` → branch-scoped `payroll_run`
  → employee `payroll` detail → earnings, deductions, contribution records,
  and one payslip.
- Unique constraints prevent more than one run per period/branch and more
  than one employee payroll per period.
- Payroll membership uses the branch assignment effective at period start;
  a mid-period transfer applies to the next period.
- Run states are `Draft`, `Computed`, `PendingOwnerApproval`, `Approved`, and
  `Returned`. Submission records the submitter/time; return requires a reason;
  approval records the Owner/time. Approved runs and details are immutable.
- `salary.daily_rate` is the only authoritative wage source. Salary changes
  create effective-dated rows; `employee` does not duplicate the rate.

### Weekly payroll and time rules

- The recurring period is Friday through Thursday with Saturday as rest day,
  producing six scheduled working days. Thursday closes attendance, Friday
  morning is Owner review, and Friday afternoon is disbursement/payslip release.
- The first Sunday-through-Thursday biometric week is retained only as
  historical transition data.
- Default documented shifts are 07:00–16:00 and 08:00–17:00 with eight paid
  hours. The MVP uses no undocumented grace period.
- A single punch is incomplete and cannot be automatically paid. More than
  two punches are preserved and flagged for HR review; preview may show the
  earliest and latest punch but cannot silently discard intermediate evidence.
- Overtime requires both recorded time and an approved overtime request.
- The documented formulas are: overtime 125%, regular-holiday work 200%,
  special-holiday work 130%, and late deduction ₱1 per minute. Overtime on a
  holiday is not compounded until HR supplies that missing rule.
- Attendance-policy thresholds create HR-review flags only; the system never
  automatically suspends or terminates an employee.

### Contributions, tax, benefits, and disbursement

- Government employee shares are deductions, never benefits. The orphan
  historical `benefits_tbl` is not migrated.
- SSS and PhilHealth use EEMR/Monthly Basic Salary:
  `(daily_rate × 313) ÷ 12`. Monthly government shares are deducted only on
  the last-Friday payroll; employer shares are stored for reporting.
- The Final Defense Reviewer example is the only approved MVP contribution
  fixture: daily rate ₱460; EEMR ₱11,998.33; SSS ₱540 employee/₱1,140 employer;
  PhilHealth 4% split equally (₱239.97 each after rounding); Pag-IBIG ₱200
  employee/₱200 employer. Demo data must use this fixture or mark a calculation
  unsupported. It must not be advertised as a complete current statutory table.
- No income tax is withheld for the documented demo employees because the HR
  answer places them at or below the stated ₱250,000 annual threshold. The
  threshold is configuration, not a permanent universal exemption.
- Weekly disbursement is one aggregate pay-to-cash BDO cheque plus one manual
  BDO deposit slip per employee. REQ073 produces a printable preparation list,
  not a bank API or upload file.
- An approved cash-advance request creates one `cash_advance_history` record;
  weekly repayment deductions reduce its remaining balance transactionally.

## Biometric workbook contract

The supplied files under `docs/Biometric_logs/` are OLE/BIFF `.xls`
workbooks, not `.dat` transaction streams. All three have one sheet with:

- fixed columns `Dept`, `User ID`, `Name`, and `Enroll ID`;
- one column per calendar date labeled `MM/DD ddd`;
- each date cell empty or containing one or more space-separated `HH:mm`
  punches;
- no explicit in/out flag and no device transaction identifier.

Observed workbook evidence:

| Sample | Range | Enrollment rows | Rows with punches |
|---|---:|---:|---:|
| June | `A1:AH139` | 138 | 37 |
| July | `A1:AI139` | 138 | 34 |
| August (through Aug 28) | `A1:AF139` | 138 | 35 |

The parser contract is therefore:

1. accept `.xls` only for the MVP and validate the workbook signature,
   required headers, date-column labels, and `HH:mm` tokens;
2. require HR to select the registered source device for the upload;
3. match `Enroll ID` to the device-scoped effective biometric enrollment;
   `Dept` and `Name` are retained as evidence but are not authoritative
   employee or branch identifiers;
4. expand every time token into an immutable `biometric_punch` record;
5. deduplicate files by SHA-256 and punches by
   device + enrollment code + local date/time because no transaction ID or
   punch type exists;
6. zero punches means no punch record; one punch means incomplete; two punches
   become time-in/time-out; more than two are preserved and flagged for review;
7. reject the entire import transaction on structural errors and report
   parsed, matched, unmatched, duplicate, incomplete, and multi-punch totals.

A future `.dat` format must be a separate parser adapter backed by a real,
sanitized sample. It is not part of the MVP contract.

## Technical decisions

- Runtime: PHP 8.5 with Composer 2 and no full-stack application framework.
- Database: MySQL 8.4 LTS, InnoDB, `utf8mb4`, strict SQL mode, PDO repositories,
  prepared statements, explicit transactions, and standalone Phinx migrations.
- HTTP/UI: one `public/index.php` front controller, explicit route dispatch,
  thin controllers, escaped native PHP templates, and small progressive
  JavaScript enhancements; no separate SPA for the MVP.
- Validation: project-owned request validators, domain value objects, and
  service-boundary validation.
- Authentication: `password_hash()`/`password_verify()` plus native PHP
  sessions persisted in MySQL through `SessionHandlerInterface`. Cookies are
  `HttpOnly`, `SameSite=Lax`, and `Secure` in production. Logout destroys the
  server session; idle expiry is 30 minutes and absolute expiry is 12 hours.
  Mutating browser requests require CSRF tokens.
- Testing and analysis: PHPUnit 12 and PHPStan.
- Workbook parsing: `phpoffice/phpspreadsheet`'s explicit `Reader\Xls` behind
  the pure `AttendanceFileParser` contract specified in ADR-0003. The
  persisted MVP parser identifier is `LDE_XLS_DAILY_LOG_V1`.
- API success envelope: `{ "data": ..., "meta": ... }`.
- API error envelope:
  `{ "error": { "code": "...", "message": "...", "fields": {}, "requestId": "..." } }`.
- Timezone: `Asia/Manila`. Business dates use MySQL `DATE`; instants use UTC
  timestamps and are converted at the boundary. UI dates remain `MM/DD/YY`
  and times remain 24-hour.
- Money: Philippine pesos, `DECIMAL(12,2)` storage, integer-centavo or validated
  decimal-string arithmetic, and half-up rounding
  to centavos per final earning/deduction component. Time calculations use
  integer minutes.
- Environment variables: `APP_ENV`, `APP_BASE_URL`, `APP_KEY`,
  `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
- Local workflow: MySQL 8.4 in Docker Compose, with separate development and
  test databases.

PHP 8.5 is an actively supported branch and MySQL documents 8.4 as an LTS
series; the selected versions favor supported, reproducible dependencies for
the MVP. ADR-0003 contains the complete frameworkless PHP and parser decision.

## Consequences

- Task 1.0's development gate is complete and scaffolding/migrations may begin
  against this baseline.
- Historical capstone diagrams and full Data Dictionary regeneration remain
  documentation deliverables, but no longer block the MVP.
- Production deployment remains blocked on official branch names/master data,
  a complete effective-dated statutory contribution policy, holiday-overtime
  treatment, and HR acceptance testing.
- Any implementation that diverges from this ADR must add a superseding ADR
  and update requirements before changing code.

## References

- [Requirements](../requirements.md)
- [Design](../design.md)
- [Schema audit](../database-schema.md)
- [Revision checklist](../database-documentation-revision-log.md)
- [ADR-0002 schema integrity corrections](0002-schema-integrity-corrections.md)
- [ADR-0003 frameworkless PHP and XLS parser](0003-frameworkless-php-and-xls-parser.md)
- [Canonical database schema v1.1](../capstone_files/Canonical-Database-Schema-v1.1.md)
- [HR follow-up answers](../capstone_files/Follow-up-Questions-with-Answers-from-HR-1.pdf)
- [Final Defense Reviewer](../capstone_files/Final-Defense-Reviewer-1.pdf)
- [PHP supported versions](https://www.php.net/supported-versions.php)
- [PhpSpreadsheet reading files](https://phpspreadsheet.readthedocs.io/en/stable/topics/reading-files/)
- [MySQL 8.4 LTS release model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html)
