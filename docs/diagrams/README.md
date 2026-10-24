# WBPMS System Diagrams

All diagrams in this folder are **read-only analyses** of the actual WBPMS
source code, migrations, routes, and service layer. No application code,
migrations, database, or configuration was modified to produce these files.

Generated: 2026-10-21

---

## Diagram Index

| File | Diagram Type | Source of Truth |
|---|---|---|
| [01-erd.md](01-erd.md) | Entity Relationship Diagram | Phinx migration files `database/migrations/` |
| [02-use-case.md](02-use-case.md) | Use Case Diagram (Mermaid flowchart) | `routes/web.php`, controller action names |
| [03-dfd.md](03-dfd.md) | Data Flow Diagram (Level 0 + Level 1) | Service classes, routes, database stores |
| [04-system-flowchart.md](04-system-flowchart.md) | System Flowchart (5 workflows) | Controller logic, service validation rules |
| [05-activity-diagram.md](05-activity-diagram.md) | Activity Diagram — Payroll Lifecycle | `PayrollService.php`, `payroll_run` state machine |
| [06-sequence-diagram.md](06-sequence-diagram.md) | Sequence Diagrams (4 flows) | `AuthService.php`, `PayrollService.php`, middleware stack |
| [07-class-diagram.md](07-class-diagram.md) | Class Diagram | All `app/` PHP source files |

---

## System Overview

**WBPMS** (Web-Based Payroll Management System) is a frameworkless PHP 8.5
application for Light Diamond Enterprises. The three-tier architecture is:

```
Browser → Router → Middleware → Controllers → Services → Repositories → MySQL
```

### Roles
| Role | Dashboard URL |
|---|---|
| Business Owner | `/owner/dashboard` |
| HR Head | `/hr/dashboard` |
| Employee | `/employee/dashboard` |

### Key Modules
1. Login & Authentication (`AuthController`, `AuthService`, `DatabaseSessionHandler`)
2. User Management (`UserController`, `UserService`)
3. Employee Management (`EmployeeController`, `EmployeeLifecycleService`, `EmployeeDocumentService`)
4. Work Schedules & Holidays (`ScheduleController`, `ScheduleRepository`)
5. Attendance Import (`AttendanceController`, `LdeXlsDailyLogParser`, `AttendanceImportService`)
6. Request Management (`RequestsController`, `RequestService`)
7. Salary Management (`SalaryController`, `SalaryService`)
8. Benefits & Contributions (`BenefitsController`, `ContributionEngine`)
9. Payroll (`PayrollController`, `PayrollService`, `HolidayPayCalculator`)
10. Reports (`ReportsController`, `PdfRenderer`)
11. Employee Self-Service (`EmployeePortalController`, `EmployeeScope`)
12. Settings (`SettingsController`)

### Payroll State Machine
```
Draft → Computed → PendingOwnerApproval → Approved (immutable)
                                      └──→ Returned → Computed (revise)
```
