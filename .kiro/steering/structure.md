---
inclusion: always
---

# Project and Code Structure Conventions

## Required repository layout

```text
wbpms/
├── app/
│   ├── Application/             # use cases and transaction orchestration
│   ├── Domain/                  # entities, DTOs, value objects, calculators
│   ├── Http/
│   │   ├── Controllers/         # HTTP only: bind, authorize, render/respond
│   │   ├── Middleware/          # auth, RBAC, CSRF, request ID
│   │   └── Routing/             # explicit route dispatcher
│   └── Infrastructure/
│       ├── Database/            # PDO connection factory/transaction helper
│       ├── Persistence/         # repository implementations and SQL
│       ├── Session/             # database SessionHandlerInterface
│       └── Reports/             # PDF/mail adapters
├── bootstrap/
├── config/
├── database/
│   ├── migrations/              # Phinx schema evolution
│   └── seeds/                   # synthetic/demo reference data
├── public/
│   └── index.php                # only web-accessible entry point
├── resources/views/             # escaped native PHP templates
├── routes/
│   └── web.php
├── storage/private/             # uploads, generated artifacts, logs; never public
├── tests/
├── composer.json
├── composer.lock
├── phinx.php
└── phpunit.xml
```

## Module to code mapping

Each numbered module in `product.md` maps to controller actions, application
services, domain services where calculations are involved, repository
interfaces/implementations, and tests:

| Module | PHP components | Primary tables |
|---|---|---|
| Login & Authentication | `AuthController`, `AuthService`, `DatabaseSessionHandler` | `users`, `role`, `sessions` |
| Dashboard | `DashboardController`, query repositories | reads across modules |
| User Management | `UserController`, `UserService`, `UserRepository` | `users`, `role` |
| Employee Management | `EmployeeController`, `EmployeeService`, `EmployeeRepository` | `employee`, `address`, `branch` |
| Work Schedule | `ScheduleController`, `ScheduleService` | `work_schedule`, `holiday_calendar` |
| Attendance | `AttendanceController`, `LdeXlsDailyLogParser`, `AttendanceImportService`, `AttendanceService` | import, punch, attendance tables |
| Request Management | `RequestController`, `RequestService` | `request`, `request_type` |
| Payroll | `PayrollController`, `PayrollService` | `payroll`, `payroll_earnings`, `payslip` |
| Manage Salary | `SalaryController`, `SalaryService` | `salary`, salary history |
| Contributions & Deductions | `ContributionController`, `ContributionEngine` | `deduction`, contribution policies/records |
| Reports | `ReportController`, report repositories/renderers | reads across modules |
| Employee self-service | `EmployeePortalController` | scoped attendance/request/payslip queries |

## Mandatory boundaries

- No Laravel, full-stack framework, ORM, active-record model, or SQL in a
  controller/template.
- PDO repositories own SQL and prepared statements; application services own
  multi-repository transaction boundaries.
- Domain calculations and the XLS parser have no HTTP/session/PDO dependency.
- `LdeXlsDailyLogParser` only parses/validates and returns DTOs. Matching,
  persistence, idempotency, and timesheet generation belong to
  `AttendanceImportService`.
- `public/` is the only web root. Uploaded files must use randomized paths in
  `storage/private/` and are removed after parse/import.
- Migrations and seeders are Phinx-owned; schema is never hand-edited in a
  developer database.

## Naming conventions

- Database tables: `snake_case`, singular, matching the canonical schema.
- PHP namespaces/classes/interfaces/enums: `PascalCase`; methods/properties and
  local variables: `camelCase`; constants: `UPPER_SNAKE_CASE`.
- Use `declare(strict_types=1);` in PHP source and PSR-4 namespaces under
  `Wbpms\`.
- Value objects/DTOs are immutable (`readonly` where practical).
- Requirement identifiers remain in test names, issue references, and relevant
  commit messages, e.g. `REQ018`, `REQN007`.

## Spec-driven workflow

ADR-0001 defines accepted business rules, ADR-0002 defines schema integrity,
and ADR-0003 defines frameworkless PHP and parser architecture. `docs/` is
canonical; `.kiro` mirrors it for tooling and must not redefine it.

1. Before implementing a module, read its `.kiro/specs/.../requirements.md`.
2. Do not write code until the related design is approved.
3. Complete one task at a time and retain requirement traceability.
4. Record any newly discovered requirement in the canonical requirements before
   implementing it.
