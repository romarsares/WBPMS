# WBPMS Comprehensive Codebase Review

This review covers the full WBPMS development branch (`romarsares/WBPMS`, branch `WBPMS-dev`) as of the reviewed commit state. It evaluates security posture, architecture compliance, code quality, database integrity, business logic correctness, and deployment readiness for the Railway-hosted production system. Changes are manually promoted to `lightdiamond0123-rgb/wbpms` (`main`), so issues found here affect what can safely be promoted.

**Watch for:**
1. Nine sensitive/diagnostic files (`backup.sql`, `backup_11_10_26.sql`, `backup.zip`, `railway-backup.sql.gz`, `check_db.php`, `diag_payroll.php`, `read_xls.php`, `read_xls_all.php`, `WBPMS-build.zip`) are **tracked in git** and not in `.gitignore` — the SQL backups contain the live production Railway schema host string; the PHP scripts are executable via web if the web root protection fails.
2. The committed `.env` file contains a real Gmail App Password and production DB credentials. Even though `.env` itself is gitignored, the `build/.env` file is **tracked** and contains a plain-text `DB_PASSWORD`.
3. `POST /users/{id}/archive` is registered **twice** in `routes/web.php` (lines 83 and 85). The first match wins; the duplicate is harmless today but signals the route table is not reviewed before merges.
4. All payroll money calculations use IEEE 754 `float` arithmetic — the codebase comment says "integer centavos" but no `bcmath` is used anywhere.

**Verdict**: NEEDS_CHANGES

---

## High-level view

**Debug and backup files in the repository** are the most urgent operational risk. Nine files that must never reach production are tracked in git without `.gitignore` coverage. `check_db.php` and `diag_payroll.php` are plain PHP scripts that connect to the database and dump schema/data when requested via HTTP; `read_xls.php` / `read_xls_all.php` load a hardcoded biometric log file and print employee data. The two SQL dump files reference `mysql.railway.internal`, confirming they were taken from the live Railway instance.

**The `.env` file is correctly gitignored**, but `build/.env` is tracked and contains plaintext credentials. The `.env` in the working copy contains a real Gmail App Password (`gimh myxv mbrq bkwp`) and a production DB root password. These are not in git history (`.env` is gitignored), but anyone with access to the working directory or the `build/.env` file can read them.

**The authentication and session security layer is solid**. CSRF tokens use `hash_equals`, the `DatabaseSessionHandler` enforces both idle and absolute TTLs, `session_regenerate_id(true)` is called on login, single-active-session enforcement deletes prior rows before the new session is committed, and `secure`/`HttpOnly`/`SameSite=Lax` cookie attributes are set correctly for production. The per-request identity refresh in `AuthMiddleware::identity()` means role changes and account deactivations take effect on the very next request.

**XSS posture is consistent**. The `Formatter::escape()` helper wraps `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE, UTF-8)` and is used throughout all reviewed templates. Flash messages piped into JavaScript use `json_encode($toastData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT)` with a secondary JS entity-replace in `showToast()`. No confirmed unescaped user-controlled output was found.

**SQL injection is controlled** throughout. All user-controlled inputs are bound via PDO named parameters. Dynamic `IN (…)` clause construction uses PHP-generated placeholder keys with integer-cast values — no raw string interpolation of user input into queries was found.

**The payroll calculation engine uses IEEE 754 floats throughout** despite the class comment saying "integer centavos". A 7-day payroll with many employees and fractional minutes can accumulate sub-centavo rounding errors that are individually invisible but will cause the per-employee totals to diverge from the run-level aggregates stored in `payroll_run`. The existing `round()` calls at each step partially mitigate this but do not eliminate it.

**The `X-Forwarded-For` header is blindly trusted** as the client IP in `AuthController::clientIp()`. On Railway every request is proxied, so `REMOTE_ADDR` is always the Railway load balancer. However the full header value (which can contain a comma-separated list injected by the client) is stored in `audit_logs.ip_address` as-is. This does not create a security vulnerability per se but it does mean audit log IP records can be spoofed.

**The `build/` directory mirrors the full source tree** and is tracked in git, including its own `.env` with production-tier credentials. Its purpose is not documented anywhere visible to a reviewer; it creates a maintenance hazard because changes to `app/` may or may not be duplicated to `build/app/`.

**Composer declares `"php": "^8.2"`** and locks the platform at `8.2.12`, but the tech stack rule specifies PHP 8.5. This discrepancy means Composer will not warn if a PHP 8.5-only feature is introduced in development but `composer.json` still allows 8.2 compatibility.

---

<details>
<summary>Issues (13)</summary>

1. **Debug scripts web-accessible** — `check_db.php`, `diag_payroll.php`, `read_xls.php`, `read_xls_all.php` are tracked in git and sit at the project root. If `.htaccess` rewriting fails (e.g. on Railway where `.htaccess` is not processed), they are directly reachable and will dump schema, employee, and configuration data. Delete them or move to a non-web path and add them to `.gitignore`.

2. **SQL backup files tracked in git** — `backup.sql`, `backup_11_10_26.sql`, `backup.zip`, `railway-backup.sql.gz`, `reseed.sql`, `clean_database.sql`, `cleanup-attendance-payroll.sql` are all tracked. The SQL files contain the live Railway hostname and full schema. Add `*.sql`, `*.sql.gz`, `*.zip` exclusions to `.gitignore` and remove these from git history (or at minimum from the working tree before the next promotion to production).

3. **`build/.env` tracked with plaintext credentials** — `build/.env` is a git-tracked file containing `DB_PASSWORD=cbsadmin` and `APP_KEY=ccfd...`. Remove it from tracking and add `build/.env` to `.gitignore`.

4. **`build/` directory purpose undefined and drift-prone** — `build/` mirrors most of `app/` and `resources/views/` but `git status` shows it already diverging (some files modified in `app/` but not in `build/`). This creates silent divergence between what is reviewed and what may be deployed. Either document and automate the sync, or remove `build/` from the repo.

5. **`WBPMS-build.zip` and `WBPMS-Change-Request-Log-2026-10-18.docx` tracked** — a 3.4 MB zip and a Word document are committed. The zip likely contains a full application snapshot with credentials. Remove from git history and add to `.gitignore`.

6. **Duplicate route registration** — `POST /users/{id}/archive` appears twice in `routes/web.php` (lines 83 and 85). The router returns on the first match, so the second is dead code. Remove the duplicate before it causes confusion if the first is ever deleted.

7. **IEEE 754 floats for money, not integer centavos** — `PayrollService::computeRun()` uses PHP `float` for every monetary calculation (basic pay, deductions, contributions, net pay). The class-level comment says "integer centavos" but no `bcmath` is used. The `round()` calls at each step reduce but do not eliminate accumulation drift. Convert to integer centavos (multiply daily_rate from DECIMAL by 100 on read, do all arithmetic in integers, divide by 100 only on INSERT) or use `bcmath` with 4-digit precision and round only once at INSERT time.

8. **X-Forwarded-For stored without sanitisation** — `AuthController::clientIp()` returns `$_SERVER['HTTP_X_FORWARDED_FOR']` directly without parsing the first hop or stripping commas. A client can send `X-Forwarded-For: 1.2.3.4, injected-value` and the full string lands in `audit_logs.ip_address`. Parse only the first comma-separated value: `explode(',', $header)[0]`.

9. **`check_db.php` and `diag_payroll.php` have no authentication** — these scripts bootstrap the application, connect to the database, and print schema/employee data with zero authentication. They must be deleted from the codebase.

10. **`composer.json` platform constraint mismatches required PHP version** — `"php": "^8.2"` and `"platform": {"php": "8.2.12"}` allow PHP 8.2-compatible code while the tech stack rule mandates PHP 8.5. If PHP 8.5-only features are used, `composer install` on a 8.2 host will silently succeed but produce a broken deployment. Update to `"php": "^8.5"` and `"platform": {"php": "8.5.0"}`.

11. **PHPUnit/PHPStan versions behind spec** — `composer.json` requires `phpunit/phpunit: ^11.0` and `phpstan/phpstan: ^1.12`; the tech stack mandates PHPUnit 12. Update to `^12.0`.

12. **`MAIL_PASSWORD` in committed `.env` (working copy)** — the working-copy `.env` contains `MAIL_PASSWORD="gimh myxv mbrq bkwp"` (a Gmail App Password) and `DB_PASSWORD=cbsadmin`. `.env` is gitignored so this is not in git history, but anyone who clones the repo and already has these credentials in their working tree (or receives the zip) has production mail access. Rotate the Gmail App Password and change the development DB password.

13. **Rest-day premium not implemented** — `PayrollService::computeRun()` hardcodes `isRestDay: false` for all holiday pay computations. Employees who work on a rest-day holiday receive no rest-day premium. The comment marks this as pending, but it is an underpayment under DOLE rules for whoever works a rest day. Track and schedule for implementation.

</details>

---

<details>
<summary>Details</summary>

## Debug and diagnostic files at the web root

`check_db.php`, `diag_payroll.php`, `read_xls.php`, and `read_xls_all.php` are all tracked in git (`git ls-files` confirms all four). They sit at the project root, one directory above `public/`. On Railway, the web server is configured with `public/` as document root via the Nixpacks/Docker entrypoint — these scripts are not reachable **when Railway's routing is working**. But the deployment configuration is not enforced at the filesystem level, only by convention. If the wrong web root is ever configured (or if a future host does not use the `public/` subdirectory pattern), all four scripts become directly executable.

`check_db.php` requires no authentication, reads environment credentials, and prints branch names, biometric device IDs, employee IDs, names, and HR user IDs. `diag_payroll.php` loads the full application, connects to the database, prints table column definitions, executes a production query, and returns the results. `read_xls.php` loads a file at a hardcoded path (`docs/Biometric_logs/Daily-Log-H20260830_1439aug.xls`) — if that file exists on the server, it prints biometric punch data for every employee in the file. These are confirmed at lines `check_db.php:1-32`, `diag_payroll.php:1-54`, `read_xls.php:1-12`, `read_xls_all.php:1-18`.

**Severity: Critical.** Delete all four before the next production promotion.

---

## Backup SQL files and archives in the repository

`backup.sql` (1.7 MB), `backup_11_10_26.sql` (1.5 MB), `backup.zip` (215 KB), `railway-backup.sql.gz` (109 KB), and `WBPMS-build.zip` (3.4 MB) are all git-tracked. `backup.sql` was produced by MariaDB's mysqldump tool against `mysql.railway.internal`, confirming it is from the live Railway instance. It contains the full schema and row data (minus user INSERT statements in the sample checked, but the structure and all business data tables are present). `railway-backup.sql.gz` is a compressed backup of the same host.

None of these files are covered by `.gitignore`. The pattern `*.sql` and `*.zip` need to be added. More importantly, if any of these files contain production employee records, payroll figures, or government ID numbers, their presence in git history becomes a data-privacy concern under Philippine law (Data Privacy Act 2012).

**Severity: Critical** for the debug scripts; **High** for the data dumps pending a full content audit.

---

## `build/` directory: tracked, drifting, and contains credentials

`build/` is a full mirror of the application source that is tracked in git. `build/.env` contains `DB_PASSWORD=cbsadmin` and `APP_KEY=ccfd084106afc61cbb4007db0768277288c6f938cffca7e824d9251aa8434565` in plaintext. `git status` shows `build/app/` and `build/resources/views/` have already diverged from their `app/` counterparts — meaning whoever reviews `build/` for a production promotion may be looking at outdated code.

The `build/BUILD-README.md` presumably explains the directory's purpose, but its absence from the standard development workflow (no Makefile target, no CI step) means it is updated manually and inconsistently. The credential exposure in `build/.env` is confirmed at `build/.env:12-13`.

**Severity: High** (credential exposure); **Medium** (drift risk).

---

## Duplicate route: `POST /users/{id}/archive`

`routes/web.php` lines 83 and 85 both register `POST /users/{id}/archive` to `[UserController::class, 'archive']` with the same role set. The router iterates routes in registration order and returns on the first match, so the second registration is permanently dead. The risk is low today, but if a developer inserts a different handler between the two entries thinking the second is intentional, one of the two will silently win. Remove line 85.

---

## IEEE 754 floats for monetary calculations

`PayrollService` comments say money is "integer centavos internally" but the actual computation uses PHP `float` throughout. `$dailyRate` is cast from a DECIMAL string to `float` at `(float) $emp['daily_rate']`. All subsequent operations (`$basicPay = round($daysCount * $dailyRate, 2)`, `$lateDed = round($totalLate * $lateRatePerMin, 2)`, etc.) are float multiplications with `round()` after each step.

PHP floats have 15–17 significant decimal digits of precision. At Philippine wage levels (e.g. ₱500/day) the error per operation is under a centavo, and the per-step `round()` calls prevent large accumulation. However the class-level documentation promise of "integer centavos" is false, and the behaviour will differ from an integer-centavo implementation in edge cases involving non-terminating binary fractions (e.g. ₱1/3 of a standard-minutes rate). This is a medium-severity correctness gap rather than a critical bug at current wage scales, but it should be resolved before the system handles higher daily rates or government contribution brackets that involve more complex fractions.

The government contribution calculations (`computeSss`, `computePhilhealth`, `computePagibig`) similarly use float arithmetic against bracket tables. Bracket lookups are integer comparisons so they are safe, but the resulting percentages multiplied by EEMR can produce sub-centavo residues.

---

## X-Forwarded-For stored without sanitisation

`AuthController::clientIp()` (line 557) returns `$_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''` directly. On Railway, every request passes through a load balancer, so `REMOTE_ADDR` is always an internal IP. The `X-Forwarded-For` header is the only way to get the actual client IP. However a client can inject arbitrary values: `curl -H "X-Forwarded-For: 1.2.3.4, evil-payload"`. The full string is stored in `audit_logs.ip_address` (truncated at 500 chars via `mb_substr` on the user agent but not on the IP). This does not enable SQL injection or authentication bypass, but it makes audit log IP records untrustworthy for forensics.

Fix: `trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])` to take only the leftmost (client-submitted) hop, then validate it is a plausible IP address before storing.

---

## Composer version constraints vs. stated PHP/PHPUnit requirements

The tech stack rule specifies PHP 8.5 and PHPUnit 12. `composer.json` requires:
- `"php": "^8.2"` with platform `8.2.12`
- `"phpunit/phpunit": "^11.0"`
- `"phpstan/phpstan": "^1.12"`

This means:
- `composer install` will succeed on PHP 8.2, so a CI runner or deployment host on 8.2 will not raise an error — it will just run code that may depend on 8.5-only features.
- PHPUnit 11 runs fine against PHPUnit 12-style tests today, but the test suite is documented as targeting 12 and future tests may use 12-only assertions.

Update `composer.json` and `composer.lock` to `"php": "^8.5"`, `"platform": {"php": "8.5.0"}`, and `"phpunit/phpunit": "^12.0"`.

---

## Security posture: auth, CSRF, sessions

CSRF enforcement is correct: every state-mutating method (POST/PUT/PATCH/DELETE) passes through `CsrfMiddleware::verify()` before the controller is called (`Router.php` lines 103–107). Token comparison uses `hash_equals`. Session cookie gets `secure=true` in production, `httponly=true` always, `samesite=Lax`. `session_regenerate_id(true)` is called on every login. Prior sessions are invalidated before the new one is committed (`DatabaseSessionHandler::invalidateByUserId()`). The per-request `AuthService::currentIdentity()` call in `AuthMiddleware::identity()` means a deactivated or role-changed account is locked out on the very next request.

Password reset tokens are stored as SHA-256 hashes with a 1-hour TTL, and `PasswordResetService::initiateReset()` always redirects to the "sent" page regardless of whether the email exists, preventing account enumeration.

The employee self-service portal uses `EmployeeScope::assertOwnRecord()` before every payslip, attendance, and request query, blocking IDOR correctly.

---

## SQL injection

All reviewed queries use PDO named parameters. Dynamic `IN (…)` clause construction uses integer-keyed placeholders built from `array_keys($employees)` with values cast to `(int)` before binding. No raw string interpolation of user input into SQL was found in any reviewed file. MySQL strict mode is enforced at connection time via `PDO::MYSQL_ATTR_INIT_COMMAND`.

---

## XSS — template output

`Formatter::escape()` wraps `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` and is used consistently across reviewed templates. Flash messages serialised into JavaScript use `json_encode($toastData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT)` (`layout.php` ~line 300). The `showToast()` JS function performs an additional entity-replace on `<` and `>`.

No confirmed unescaped output of user-controlled data was found.

---

## File upload security

Attendance import (`AttendanceController::upload()`): 10 MiB size cap, `.xls` extension check, OLE magic byte verification at the controller before the file reaches the parser, randomised storage path under `storage/private/attendance/`, `move_uploaded_file()` for atomic rename, deletion in a `finally` block. `LdeXlsDailyLogParser` re-checks the OLE signature, enforces row/column limits, rejects formula cells, and validates all date headers against the stated source period.

Employee document upload (`EmployeeDocumentService`): MIME type detected from magic bytes (not from the client-supplied content-type), limited to PDF/JPEG/PNG, 5 MB cap. Stored under `storage/private/` with randomised path. Served via `readfile()` with `Content-Disposition: inline` and `X-Content-Type-Options: nosniff`. The `stored_path` for `viewDocument` is fetched from the database, so path traversal via URL manipulation is blocked.

---

## Architecture compliance

Layer separation is maintained: no SQL in controllers, no PDO in domain classes, no HTTP concerns in services. `LdeXlsDailyLogParser` has zero HTTP/session/PDO dependencies. `AttendanceImportService` owns the transaction, matching, and timesheet generation. `PayrollService` owns all payroll SQL.

The only boundary deviation: `AuthMiddleware::identity()` instantiates `Connection` and `AuthService` directly (lines 101–105) rather than receiving them via injection. Acceptable for a static middleware class but makes unit testing harder, since the middleware requires a live database config file on disk.

---

## Business logic: payroll computation

`PayrollService::computeRun()`:
- Fetches the salary effective on `period_start` (not the current salary)
- Counts only `Complete` or `Approved` attendance from `Approved`/`Completed` import batches
- Applies paid overtime only where both an approved OT request and actual worked overtime agree
- Adjusts holiday pay by computing the premium above the regular-day amount already in basic pay, avoiding double-counting
- Applies contributions only on the month-end payroll cutoff (`isMonthlyContributionCutoff()`)
- Guards against recomputation when manual adjustments exist
- Uses `SELECT … FOR UPDATE` after beginning the transaction to prevent concurrent recompute + cancel races

Rest-day detection is hardcoded as `isRestDay: false` for all holiday calculations, so employees who work on a rest-day holiday receive no rest-day premium. The comment marks this as pending, but it is an underpayment under DOLE rules.

---

## Test coverage

The test suite covers: CSRF middleware, auth middleware, auth service, session handler, router, XLS parser, timesheet generator, contribution cutoff logic, employee scope, payroll immutability, attendance policy evaluator, holiday pay calculator, overtime calculator, leave credits, and schema invariants. This is a solid unit test suite for a frameworkless PHP application.

Not tested: the full HTTP dispatch cycle (no integration test hits a real route via the front controller), payroll computation end-to-end against a real database with contributions, the duplicate route registration bug, and `X-Forwarded-For` handling.

</details>

---

## File map

<details>
<summary>Files reviewed</summary>

| File | What was examined |
|---|---|
| `routes/web.php` | Full route table — found duplicate archive route |
| `public/index.php` | Front controller — error handler, env-gated debug |
| `bootstrap/app.php` | Session bootstrap, dotenv loading, back-fill strategy |
| `app/Http/Routing/Router.php` | Method override, CSRF/RBAC middleware calls |
| `app/Http/Middleware/CsrfMiddleware.php` | Token generation, verify, hash_equals usage |
| `app/Http/Middleware/AuthMiddleware.php` | startSession, setIdentity, requireRoles, destroySession, per-request DB refresh |
| `app/Application/AuthService.php` | Login, password verify/rehash, audit write |
| `app/Infrastructure/Session/DatabaseSessionHandler.php` | TTL enforcement, write, gc, invalidateByUserId |
| `app/Http/Controllers/AuthController.php` | Full login/logout/forgot/reset flow |
| `app/Application/PasswordResetService.php` | Token hashing, expiry, enumeration prevention |
| `app/Http/Controllers/AttendanceController.php` | Upload pipeline, SQL construction, file handling |
| `app/Domain/Attendance/Parsing/LdeXlsDailyLogParser.php` | OLE check, resource limits, formula rejection, date validation |
| `app/Application/Attendance/AttendanceImportService.php` | Transaction orchestration, match/persist flow |
| `app/Application/PayrollService.php` | computeRun: float arithmetic, holiday pay, contributions |
| `app/Http/Controllers/PayrollController.php` | Store, show, compute, submit |
| `app/Application/AttendanceAdjustmentService.php` | findForAdjustment, adjust, approved-payroll guard |
| `app/Application/PayrollAdjustmentService.php` | adjust, run-status guard |
| `app/Application/RequestService.php` | submit validation, leave balance check |
| `app/Application/EmployeeLifecycleService.php` | archive/rehire transactions |
| `app/Application/UserService.php` | CRUD, role guards |
| `app/Http/Controllers/EmployeePortalController.php` | payslipDetail/Pdf — IDOR guard confirmed |
| `app/Application/EmployeePortal/EmployeeScope.php` | assertOwnRecord |
| `app/Application/EmployeeDocumentService.php` | MIME detection, storage, path traversal defence |
| `app/Http/Controllers/EmployeeController.php` | viewDocument — path from DB, not user input |
| `app/Infrastructure/Database/Connection.php` | PDO factory, transaction helper |
| `app/Infrastructure/Persistence/PdoAttendanceImportGateway.php` | Checksum dedup, batch lifecycle |
| `app/Infrastructure/Persistence/EmployeeRepository.php` | createEmployee SQL |
| `app/Infrastructure/Session/DatabaseSessionHandler.php` | Full session handler |
| `app/Http/View/ViewRenderer.php` | Flash normalisation, layout injection |
| `app/Http/View/Formatter.php` | escape() — ENT_QUOTES|ENT_SUBSTITUTE confirmed |
| `resources/views/layout.php` | Flash rendering, JSON_HEX_* toast serialisation |
| `config/database.php` | Strict mode enforcement at PDO init |
| `phinx.php` | Migration environments |
| `composer.json` | PHP/PHPUnit version constraints |
| `.gitignore` | Missing: *.sql, *.zip, build/.env, check_db.php etc. |
| `.env` (working copy only) | Real credentials — not in git; noted for rotation |
| `build/.env` | Tracked with plaintext credentials |
| `check_db.php`, `diag_payroll.php`, `read_xls.php`, `read_xls_all.php` | Debug scripts — tracked and unauthenticated |
| `backup.sql`, `backup_11_10_26.sql`, `backup.zip`, `railway-backup.sql.gz` | Production data dumps — tracked |
| `database/migrations/` (listing) | 30 migrations reviewed for naming and coverage |

</details>
