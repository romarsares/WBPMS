---
inclusion: always
---

# Technology Stack

The implementation returns to the PHP direction in the source capstone. It
uses **frameworkless PHP**: no Laravel and no full-stack replacement. Maintained
Composer libraries are permitted for focused infrastructure concerns, while
WBPMS owns its routing, validation, domain services, repositories, and business
rules. [ADR-0003](adr/0003-frameworkless-php-and-xls-parser.md) supersedes the
Node.js choices originally recorded in ADR-0001.

## Application layers

- **Runtime/language:** PHP 8.5, using strict types, namespaces, immutable DTOs,
  enums/value objects where useful, and PSR-4 Composer autoloading.
- **HTTP:** `public/index.php` front controller plus an explicit project-owned
  route dispatcher. Controllers translate HTTP input/output and do not contain
  payroll or attendance calculations.
- **Application/domain:** use-case services and pure calculators implement
  attendance, contributions, requests, payroll, approval, and reporting.
- **Persistence:** repository classes use PDO MySQL prepared statements and
  explicit transactions. There is no ORM or active-record layer.
- **Migrations/seeding:** standalone Phinx migrations implement the canonical
  schema and ADR-0002 constraints; seeders contain roles and clearly labeled
  synthetic/demo reference data.
- **Validation:** dedicated request validators and domain value objects at the
  HTTP and service boundaries. Validation failures use stable field/error codes.
- **Authentication:** `password_hash()`/`password_verify()` and native PHP
  sessions stored in MySQL through a project-owned `SessionHandlerInterface`.
  Cookies use `HttpOnly`, `SameSite=Lax`, and `Secure` in production; idle and
  absolute expiry remain 30 minutes and 12 hours. All mutating browser forms
  require CSRF protection.
- **Presentation:** escaped native PHP templates, HTML/CSS, and small
  progressive JavaScript enhancements; no separate SPA in the MVP.
- **Database:** MySQL 8.4 LTS with InnoDB, `utf8mb4`, strict SQL mode, and the
  canonical schema in `design.md` and the v1.1 addendum.
- **Reports/PDF:** HTML print views first; `dompdf/dompdf` may generate frozen
  payslip/report PDFs behind a report renderer interface.
- **Email/OTP:** `phpmailer/phpmailer` behind a mailer interface; production
  provider credentials remain environment-managed.
- **Testing/quality:** PHPUnit 12, PHPStan, PSR-12 formatting, integration tests
  against a dedicated MySQL test database, and browser-level critical-flow tests.
- **IDE:** Visual Studio Code.

## Architecture style

```text
Browser
   |
   | HTTP
   v
public/index.php -> Router -> Controller -> Application Service
                                           |              |
                                           |              -> Domain calculator
                                           v
                                      PDO Repository
                                           |
                                           v
                                        MySQL 8.4
```

The front controller and controllers remain thin. Payroll, attendance, and
contribution rules must be runnable in PHPUnit without an HTTP request or a
database. Repositories own SQL; services own transactions spanning more than
one repository operation.

## Biometric workbook integration

Attendance arrives through a manually uploaded legacy monthly `.xls` daily-log
workbook; there is no live device/API integration in the MVP.

- Composer package: `phpoffice/phpspreadsheet`.
- Decoder: explicitly instantiate `PhpOffice\PhpSpreadsheet\Reader\Xls` and
  use data-only reading.
- Project adapter: `LdeXlsDailyLogParser` implements `AttendanceFileParser`.
- Persisted parser version: `LDE_XLS_DAILY_LOG_V1`.
- Parser boundary: deterministic DTO/error output only; no PDO, HTTP, session,
  employee matching, or attendance calculation.
- Import boundary: `AttendanceImportService` owns matching, idempotency,
  immutable punch staging, transaction commit/rollback, timesheet generation,
  and the import summary.

The upload validates extension, OLE signature, checksum, location, worksheet
shape, headers, date context, time tokens, formula absence, and configurable
resource limits before persistence. Files use randomized temporary paths
outside `public/` and are deleted in a `finally` block. See ADR-0003 for the
complete security and parser contract.

## Suggested repository layout

```text
app/
  Application/
    Attendance/ImportAttendanceWorkbook.php
  Domain/
    Attendance/Parsing/AttendanceFileParser.php
    Attendance/Parsing/LdeXlsDailyLogParser.php
    Attendance/Parsing/ParserContext.php
    Attendance/Parsing/ParsedAttendanceFile.php
    Attendance/Parsing/ParsedPunch.php
    Payroll/
    Contributions/
  Http/
    Controllers/
    Middleware/
    Routing/
  Infrastructure/
    Database/PdoConnectionFactory.php
    Persistence/
    Session/DatabaseSessionHandler.php
    Reports/
bootstrap/
config/
database/
  migrations/
  seeds/
public/
  index.php
resources/views/
routes/web.php
storage/private/
tests/
composer.json
composer.lock
phinx.php
phpunit.xml
```

Only `public/` is web-accessible. Runtime uploads, generated payslips, logs, and
secrets must never be stored under that directory.

## Common commands

```bash
# Install exactly the locked dependencies
composer install

# Run migrations and demo seeders
vendor/bin/phinx migrate -e development
vendor/bin/phinx seed:run -e development

# Run automated checks
vendor/bin/phpunit
vendor/bin/phpstan analyse

# Local development only
php -S 127.0.0.1:8080 -t public public/router.php
```

Production runs through Nginx or Apache with PHP-FPM and `public/` as the
document root; PHP's built-in server is not a production server.

## Frozen MVP choices

- PHP 8.5 and Composer 2; versions are locked in `composer.lock`.
- No Laravel, full-stack framework, or ORM.
- PDO MySQL repositories and standalone Phinx migrations.
- PHPUnit 12 and PHPStan.
- Frameworkless PHP templates with progressive JavaScript.
- MySQL-persisted native PHP sessions and CSRF protection.
- Business timezone `Asia/Manila`; persist instants in UTC and business dates
  as MySQL `DATE`.
- API success envelope: `{ "data": ..., "meta": ... }`; error envelope:
  `{ "error": { "code": "...", "message": "...", "fields": {}, "requestId": "..." } }`.
- Required environment variables: `APP_ENV`, `APP_BASE_URL`, `APP_KEY`,
  `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.

## Non-functional constraints

- Data retrieval: within 1 second on a stable connection.
- Dashboard load, payroll computation, and report generation: within 5 seconds.
- Only authenticated and role-authorized users may access protected actions.
- English interface, `MM/DD/YY` dates, and 24-hour time display.
- Uploaded workbook limits are configurable and tested; structural failure
  never creates a partial import.

## Primary technical references

- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Composer](https://getcomposer.org/doc/00-intro.md)
- [PDO](https://www.php.net/manual/en/book.pdo.php)
- [PhpSpreadsheet file reading](https://phpspreadsheet.readthedocs.io/en/stable/topics/reading-files/)
- [Phinx](https://book.cakephp.org/phinx/0/en/index.html)
- [PHPUnit](https://docs.phpunit.de/en/12.5/)
