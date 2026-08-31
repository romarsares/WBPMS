# Developer B — attendance and user-flow start record

**Started:** 2026-08-31

This repository began as design documentation only. The initial Developer B
commit establishes code that is independent of the still-unimplemented shared
foundation and makes its required integration boundary explicit.

## Started deliverables

| Work item | Current implementation | Shared dependency before it can be completed |
|---|---|---|
| B1 / 6.3 | `LdeXlsDailyLogParser`, immutable DTOs, deterministic safe errors, runtime-generated sanitized XLS fixture, parser tests | Composer/PHP runtime to execute the suite |
| B2 / 4.1–4.4, 5.1–5.3 | Minimal employee + effective branch/schedule/enrollment service and HR form contract | Developer A migrations, PDO repositories, routing, CSRF/auth |
| B3 / 6.1, 6.4–6.8, 6.10–6.11 | Import gateway contract, transactional orchestration, matching-result contract, daily timesheet calculation, HR import/review templates | Device/enrollment/attendance persistence, upload boundary owned by A6 |
| B4 / 13.4–13.5, 14.1, 14.4 | Employee ownership guard, portal attendance/payslip templates, 24-hour and `MM/DD/YY` formatter, isolation test | Authenticated session, approved payslip read model/download renderer |
| B5 / 15.2–15.4 | Synthetic fixture policy and golden parser-output file | Shared seeders, three roles, browser runner, payroll route |

## Integration contracts for Developer A

- The upload boundary supplies `UploadedAttendanceFile`: a randomized non-public
  path, client filename, byte size, and SHA-256. It owns extension/signature/
  temporary-storage lifecycle; the parser rechecks extension and OLE signature
  defensively but does not delete the file.
- `AttendanceImportGateway` is the only B3 persistence dependency. Its
  `transactional()` method must atomically create the batch, retain all raw
  punch evidence, save attendance rows and lineage, and complete the summary.
  The canonical global checksum unique key must remain the final concurrency guard;
  the service's early checksum check is only the user-friendly fast path.
- `EmployeeSetupGateway` supplies employee, effective branch, schedule, and
  enrollment persistence. It must reject overlapping effective periods using
  the canonical migration constraints.
- Employee portal controllers must call `EmployeeScope::assertOwnRecord()` with
  the authenticated `session.employee_id` before querying attendance or a
  payslip. Route parameters must not select another employee.

## Intentionally not implemented in this lane

- Upload temporary-file handling, database transactions, authentication, CSRF
  middleware, migrations, and audited persistence are shared/Developer A
  responsibilities.
- Manual attendance adjustments and the full employee/holiday administration
  surface are explicitly deferred from P0.
- The actual HR/Employee controllers are held until the project-owned router,
  request/response types, and RBAC middleware exist; the role-guarded route
  manifest and templates are ready to connect.
