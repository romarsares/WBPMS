---
inclusion: always
---

# Project & Code Structure Conventions

## Suggested repository layout

```
payroll-system/
├── src/
│   ├── config/
│   │   └── database.ts          # Sequelize connection config
│   ├── models/                  # one Sequelize model per DB entity
│   ├── controllers/              # one controller group per module (see below)
│   ├── services/                 # business logic: PayrollService, ContributionEngine, etc.
│   ├── middleware/                # auth/role guards, validation
│   ├── routes/                   # Express routers, one per module, mounted in app.ts
│   ├── views/  or  client/        # server-rendered templates, or a separate frontend app
│   └── app.ts                     # Express app setup
├── migrations/                   # Sequelize migrations (schema evolution)
├── seeders/                       # reference data: roles, request types, contribution brackets
├── tests/
├── package.json
├── tsconfig.json
└── .kiro/
    ├── steering/                  # this folder — always-on project context
    └── specs/                     # per-module specs (requirements/design/tasks)
```

## Module → code mapping

Each numbered module in `product.md` should map 1:1 to a route + controller
group, a service (where computation is involved), and a spec folder under
`.kiro/specs/`:

| Module | Route/Controller/Service | Primary tables |
|---|---|---|
| Login & Authentication | `routes/auth.ts` → `AuthController` | `users`, `role` |
| Dashboard | `routes/dashboard.ts` → `DashboardController` | reads across modules (no own table) |
| User Management | `routes/users.ts` → `UserController` | `users`, `role` |
| Employee Management | `routes/employees.ts` → `EmployeeController` | `employee`, `address`, `branch` |
| Work Schedule | `routes/schedules.ts` → `ScheduleController` | `work_schedule`, `holiday_calendar` |
| Attendance | `routes/attendance.ts` → `AttendanceController`, `AttendanceService` | `attendance`, `attendance_adjustment` |
| Request Management | `routes/requests.ts` → `RequestController` | `request`, `request_type` |
| Payroll | `routes/payroll.ts` → `PayrollController`, `PayrollService` | `payroll`, `payroll_earnings`, `payslip` |
| Manage Salary | `routes/salary.ts` → `SalaryController` | `salary`, salary history table |
| Contributions & Deductions | `routes/contributions.ts` → `ContributionController`, `ContributionEngine` | `deduction`, `contribution_record`, SSS/PhilHealth/Pag-IBIG policy tables |
| Reports | `routes/reports.ts` → `ReportController` | reads across modules |
| Employee self-service | `routes/portal.ts` → `EmployeePortalController` | reuses attendance/request/payslip tables, scoped to `employee_id` |

## Naming conventions

- Database tables: `snake_case`, singular (e.g., `employee`, `payroll`,
  `attendance_adjustment`), matching the normalized schema in `design.md`.
  Sequelize models are `PascalCase` singular (e.g., `Employee`) mapped to
  those table names via `tableName`.
- TypeScript: classes/interfaces `PascalCase`; functions/variables
  `camelCase`; one service class per module (e.g., `PayrollService`).
- Requirement IDs: keep the original `REQ0xx` (functional) and `REQNxxx`
  (non-functional) identifiers from the source documentation as traceability
  tags in code comments and commit messages, e.g. `// implements REQ047`.

## Spec-driven workflow (how Kiro should work on this project)

ADR-0001 (`docs/adr/0001-development-baseline.md`) is the accepted MVP
decision baseline. `docs/` is canonical; `.kiro` specification files mirror
it for tooling and must not independently redefine business rules.

1. Before implementing a module, open its spec in `.kiro/specs/<module>/`
   and confirm `requirements.md` is approved.
2. Do not start writing code from a task until its `design.md` section is
   approved.
3. Execute one task at a time from `tasks.md`; check requirements coverage
   before moving to the next task.
4. Any new requirement discovered mid-implementation goes back into
   `requirements.md` first — do not silently expand scope in code.
