---
inclusion: always
---

# Technology Stack

`docs/tech.md` and
[`ADR-0003`](../../docs/adr/0003-frameworkless-php-and-xls-parser.md) are the
canonical technical baseline. Kiro must use:

- PHP 8.5 with Composer 2 and PSR-4 autoloading.
- No Laravel, full-stack PHP framework, or ORM.
- A `public/index.php` front controller, explicit route table, thin
  controllers, application/domain services, PDO repositories, and escaped
  native PHP templates with progressive JavaScript.
- MySQL 8.4 LTS, InnoDB, `utf8mb4`, strict SQL mode, and standalone Phinx
  migrations/seeders implementing ADR-0002 and canonical schema v1.1.
- Native PHP sessions persisted through a project-owned
  `SessionHandlerInterface`; secure cookie attributes, CSRF protection,
  30-minute idle expiry, and 12-hour absolute expiry.
- `password_hash()`/`password_verify()` with `PASSWORD_DEFAULT`.
- PHPUnit 12 and PHPStan.
- PhpSpreadsheet's explicit `Reader\Xls` only as the BIFF decoder behind the
  pure `AttendanceFileParser`/`LdeXlsDailyLogParser` boundary. Parser version:
  `LDE_XLS_DAILY_LOG_V1`.
- `Asia/Manila` at business boundaries, UTC stored instants, MySQL `DATE` for
  business dates, integer minutes, and integer-centavo or validated
  decimal-string money calculations.
- Environment variables `APP_ENV`, `APP_BASE_URL`, `APP_KEY`, `DB_HOST`,
  `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.

The parser has no HTTP, session, PDO, employee-matching, or payroll dependency.
The import service owns the database transaction and applies matching,
idempotency, staging, timesheet generation, and summary rules. Uploads must
meet ADR-0003's OLE signature, checksum, temporary-storage, structural, formula,
and resource-limit controls.

See `docs/tech.md` for commands, deployment, repository layout, and full
technical references. `.kiro` mirrors this decision and must not redefine it.
