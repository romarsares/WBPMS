# ADR-0003: Frameworkless PHP Application and Strict XLS Parser

- **Status:** Accepted; supersedes ADR-0001's Node.js technical decisions
- **Date:** 2026-08-31
- **Branch:** `WBPMS-dev`
- **Scope:** application runtime, code structure, dependencies, authentication,
  persistence boundary, tests, deployment, and attendance workbook parsing

## Context

The source capstone selected PHP, but ADR-0001 temporarily changed the planned
implementation to Node.js, TypeScript, Express, Sequelize, and SheetJS. No
executable application or migrations have been created, so returning to PHP
does not require a code or data migration.

The project will use PHP without Laravel or another full-stack application
framework. Frameworkless does not mean dependency-free: maintained,
single-purpose Composer packages are used where implementing a complex file
format or infrastructure concern internally would add avoidable risk.

The supplied attendance exports are legacy OLE/BIFF `.xls` workbooks. The
application must decode that format reliably while keeping the WBPMS-specific
layout, identity, validation, and persistence rules under project control.

## Decision

### Runtime and application structure

- Use PHP 8.5 and Composer 2. Commit `composer.json` and `composer.lock`.
- Do not use Laravel or another full-stack framework and do not introduce an
  ORM. Use a front controller, an explicit route table, thin controllers,
  application/domain services, repositories, and native PHP templates.
- Use PDO with the `pdo_mysql` driver, prepared statements, exception mode,
  explicit transactions, and repository-owned SQL.
- Use Phinx as the standalone migration/seeding tool. Schema changes must
  remain migration-owned and must implement ADR-0002 and canonical schema
  v1.1; production databases are never changed manually.
- Use native PHP sessions with a project-owned `SessionHandlerInterface`
  implementation backed by the canonical MySQL `sessions` table.
- Use `password_hash()`/`password_verify()` with `PASSWORD_DEFAULT`. Keep the
  existing idle and absolute session limits and secure cookie attributes.
- Render escaped PHP templates with progressive JavaScript. Every mutating
  browser request requires a CSRF token.
- Use PHPUnit 12 for unit/integration tests and PHPStan for static analysis.
- Keep money out of binary floating-point calculations: use integer centavos
  or validated decimal strings and round half-up only at documented component
  boundaries. Continue to use integer minutes for time calculations.
- Deploy with Nginx or Apache plus PHP-FPM, with `public/` as the only web
  document root. Local development may use PHP's built-in server and a MySQL
  8.4 Docker service.

### Attendance parser boundary

Use `phpoffice/phpspreadsheet` only as the low-level BIFF decoder. The
WBPMS-specific parser remains a separate adapter:

```text
temporary upload
    -> upload/signature/resource validation
    -> PhpSpreadsheet Reader\Xls
    -> LdeXlsDailyLogParser (pure validation and normalization)
    -> ParsedAttendanceFile DTO
    -> AttendanceImportService (transaction, matching, persistence)
    -> TimesheetGenerator
```

The following contracts are required:

```php
interface AttendanceFileParser
{
    public function supports(UploadedAttendanceFile $file): bool;

    public function parse(
        string $temporaryPath,
        ParserContext $context
    ): ParsedAttendanceFile;
}
```

The MVP implementation is named `LdeXlsDailyLogParser`; its persisted version
identifier is `LDE_XLS_DAILY_LOG_V1`. A future `.xlsx`, `.csv`, `.dat`, or
device-API source requires a separate adapter and sanitized source evidence.

### Upload and workbook controls

Before parsing, the upload boundary must:

1. require the `.xls` extension and treat `finfo` MIME detection as advisory;
2. verify the eight-byte OLE compound-file signature
   `D0 CF 11 E0 A1 B1 1A E1`;
3. calculate SHA-256 before import and reject an existing batch checksum;
4. use a randomized temporary name outside `public/`, never construct a path
   from the client filename, and delete the temporary file in a `finally`
   block;
5. enforce configurable defaults of 10 MiB per file, 5,000 enrollment rows,
   one worksheet, at most 31 date columns, 16 punch tokens per cell, and a
   bounded total token count; and
6. reject encrypted, corrupted, formula-bearing, unexpected-sheet, and
   structurally unsupported workbooks without partial persistence.

Instantiate `PhpOffice\PhpSpreadsheet\Reader\Xls` explicitly and enable
`setReadDataOnly(true)`. Do not convert the workbook to CSV first and do not
use automatic multi-format detection for this fixed MVP contract. A read
filter may be introduced if measured files exceed the accepted resource
budget, without changing the parser interface.

### Pure parsing rules

`LdeXlsDailyLogParser` has no database, session, or HTTP dependency. Given the
same file bytes and `ParserContext`, it must produce the same DTO or the same
ordered validation errors. It must:

- require one sheet and the exact identity headers `Dept`, `User ID`, `Name`,
  and `Enroll ID`;
- require HR-supplied device, source year, and source month context;
- validate each unique `MM/DD ddd` header against that year and month;
- preserve `Enroll ID` as a trimmed string, including leading zeroes;
- split non-empty date cells on ASCII whitespace and require every token to
  match and represent a valid 24-hour `HH:mm` value;
- reject formula cells and any non-empty value outside the approved matrix;
- emit every punch as an immutable value containing device employee code,
  local `Asia/Manila` timestamp, source row/column, original cell value, and
  optional source department/user/name evidence; and
- never infer punch type, employee identity, branch, attendance outcome, or
  payroll treatment.

Parser errors use stable codes such as `INVALID_OLE_SIGNATURE`,
`UNEXPECTED_SHEET_COUNT`, `MISSING_HEADER`, `INVALID_DATE_HEADER`,
`DATE_CONTEXT_MISMATCH`, `DUPLICATE_DATE_COLUMN`, `INVALID_TIME_TOKEN`,
`FORMULA_NOT_ALLOWED`, and `RESOURCE_LIMIT_EXCEEDED`, with safe row/column
locations for HR. Errors must not expose server paths or stack traces.

### Import and attendance responsibilities

After pure parsing, `AttendanceImportService` owns one database transaction:

1. create the versioned import batch;
2. match device + enrollment code + punch time to effective enrollment and
   branch coverage;
3. retain unmatched and coverage-exception punches as immutable evidence;
4. apply the canonical checksum and punch uniqueness rules;
5. generate one attendance row per employee/date and exact punch lineage; and
6. commit the batch and summary, or roll back the complete import.

The parser emits all time tokens and does not label them as in/out. The
timesheet generator applies the accepted one/two/multiple-punch policy.

### Verification fixtures

The three supplied workbooks remain private source evidence and are not copied
into public test snapshots. Tests use synthetic or irreversibly sanitized
`.xls` fixtures covering valid input, leading-zero enrollment codes, empty and
multi-punch cells, invalid headers/times, incorrect month/weekdays, formulas,
renamed `.xlsx` files, corrupt/truncated files, resource limits, duplicate
uploads, unmatched enrollment, and transaction rollback.

## Consequences

- The accepted business rules and canonical MySQL schema remain unchanged.
- The application becomes consistent with the PHP direction in the original
  capstone while retaining disciplined layering and automated verification.
- Developers write more routing, request, dependency-wiring, and repository
  infrastructure than a Laravel implementation would require; those shared
  components must be completed before parallel module work.
- PhpSpreadsheet is a replaceable decoder behind the project-owned parser
  interface rather than a dependency spread through controllers and services.
- ADR-0001 remains authoritative for business rules; ADR-0002 remains
  authoritative for schema integrity; this ADR is authoritative for the
  implementation stack and parser architecture.

## References

- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Composer introduction](https://getcomposer.org/doc/00-intro.md)
- [PDO documentation](https://www.php.net/manual/en/book.pdo.php)
- [PHP session handler interface](https://www.php.net/manual/en/class.sessionhandlerinterface.php)
- [PhpSpreadsheet project](https://github.com/PHPOffice/PhpSpreadsheet)
- [PhpSpreadsheet reading files](https://phpspreadsheet.readthedocs.io/en/stable/topics/reading-files/)
- [Phinx documentation](https://book.cakephp.org/phinx/0/en/index.html)
- [PHPUnit 12 documentation](https://docs.phpunit.de/en/12.5/)
- [ADR-0001 development baseline](0001-development-baseline.md)
- [ADR-0002 schema integrity corrections](0002-schema-integrity-corrections.md)
