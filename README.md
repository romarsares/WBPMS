# Web-Based Payroll Management System (WBPMS)

A proposed payroll, attendance, and employee self-service platform for
Light Diamond Enterprises. The system is intended to replace a slow,
spreadsheet-based payroll process with a centralized web application for
attendance processing, salary computation, government contributions,
requests, approvals, payslips, and reports.

## Project status

This repository has an **accepted MVP development baseline** in ADR-0001 as
amended by ADR-0002. Requirements, architecture, the typed canonical schema,
the biometric `.xls` contract, and technical choices are ready for
implementation. It does not yet contain
an executable application, `composer.json`, database migrations, or deployment
configuration.

The planned implementation uses frameworkless PHP 8.5, Composer, PDO, Phinx,
PhpSpreadsheet, and MySQL. Installation and runtime commands will become
applicable after the project foundation is scaffolded.

## Goals

- Reduce the manual payroll cycle currently managed through spreadsheets.
- Import biometric attendance from an exported monthly `.xls` workbook and generate
  employee timesheets.
- Calculate hours worked, lateness, undertime, overtime, deductions, and
  net pay consistently.
- Compute SSS, PhilHealth, and Pag-IBIG contributions from maintained rates
  or brackets.
- Route prepared payroll from the HR Head to the Business Owner for final
  approval or return for revision.
- Give employees secure access to their attendance, requests, and digital
  payslips.
- Preserve an audit trail for adjustments, approvals, and archived records.

## Users and responsibilities

| Role | Primary responsibilities |
|---|---|
| Business Owner | Reviews payroll, approves or returns payroll for revision, and monitors cross-branch summaries and reports. |
| HR Head | Manages users, employees, schedules, attendance, requests, salaries, contributions, payroll, and reports. |
| Employee | Views personal attendance and payslips and submits leave, overtime, and cash-advance requests. |

Access is role-based, and employee-facing operations must always be scoped
to the authenticated employee.

## Planned modules

1. Login, authentication, and password recovery
2. Role-based dashboards
3. User management
4. Employee and branch management
5. Work schedules and holiday calendars
6. Biometric `.xls` attendance import and timesheet generation
7. Leave, overtime, and cash-advance requests
8. Salary structures and historical rates
9. Government contributions, benefits, and deductions
10. Payroll computation and Owner approval
11. Reports, printing, and file exports
12. Employee self-service portal

## Planned architecture

The design follows a three-tier architecture:

```text
Browser UI
    |
    | HTTP / JSON
    v
PHP front controller application
Router -> Controllers -> Services -> Validation/RBAC
    |
    | PDO repositories
    v
MySQL database
```

Business calculations belong in service classes so payroll, attendance,
and contribution rules can be tested independently from controllers and
the user interface.

### Technology direction

| Area | Planned choice |
|---|---|
| Runtime | PHP 8.5 |
| Web framework | None — project-owned front controller and route dispatcher |
| Database | MySQL |
| Persistence and migrations | PDO repositories and Phinx |
| Validation | Project-owned request/domain validators |
| Authentication | `password_hash()` plus MySQL-backed native PHP sessions |
| User interface | Server-rendered PHP/HTML/CSS with progressive JavaScript |
| PDF/report generation | Print-ready HTML and optional Dompdf adapter |
| Testing | Unit, integration, performance, RBAC, and end-to-end tests |

## Attendance import workflow

```text
HR uploads biometric daily-log .xls workbook
              |
              v
Validate the workbook and expand date-cell time tokens
              |
              v
Match device IDs to employees
       /                    \
      v                      v
Matched punches       Unmatched-punch queue
      |
      v
Generate timesheets and flag duplicates/incomplete entries
      |
      v
Compute worked hours, late time, undertime, and overtime
```

The import must be transactional: an invalid file is rejected without a
partial import, duplicate punches are skipped or flagged, and unmatched
device identifiers are retained for HR review.

## Documentation

| Document | Purpose |
|---|---|
| [Product overview](docs/product.md) | Product goals, users, modules, business rules, and scope. |
| [Requirements](docs/requirements.md) | User stories and acceptance criteria with original REQ/REQN traceability identifiers. |
| [Design](docs/design.md) | Application architecture, service interfaces, reconciled data model, error handling, and testing strategy. |
| [Technology stack](docs/tech.md) | Planned runtime, framework, database, integrations, and non-functional constraints. |
| [Implementation plan](docs/tasks.md) | Module-by-module implementation and testing checklist. |
| [Five-day development roadmap](docs/five-day-development-roadmap.md) | Collaborative MVP scope, ownership lanes, daily integration gates, and delivery workflow. |
| [Consolidated recommendations](docs/recommendation.md) | Cross-document recommendations with their ADR-0001/ADR-0002 resolution status. |
| [Database schema audit](docs/database-schema.md) | Source-literal database evidence, cross-source conflicts, and recommended canonical decisions. |
| [Database revision record](docs/database-documentation-revision-log.md) | Corrections, accepted MVP resolutions, and remaining capstone-publication work. |
| [Development baseline ADR](docs/adr/0001-development-baseline.md) | Accepted MVP decisions, workbook contract, technology choices, and production caveats. |
| [Schema integrity ADR](docs/adr/0002-schema-integrity-corrections.md) | Corrected lifecycle authority, lineage, temporal constraints, and migration rules. |
| [PHP/parser ADR](docs/adr/0003-frameworkless-php-and-xls-parser.md) | Frameworkless PHP baseline, dependency choices, XLS parser contract, and upload controls. |
| [Canonical schema v1.1](docs/capstone_files/Canonical-Database-Schema-v1.1.md) | Typed implementation dictionary and capstone correction addendum. |
| [PHP/parser capstone addendum](docs/capstone_files/Capstone-Implementation-Stack-and-Parser-Addendum-v1.0.md) | Capstone-facing frameworkless PHP and verified XLS import implementation record. |

Original reference material is retained under
[`docs/capstone_files`](docs/capstone_files/), including the
[capstone submission](<docs/capstone_files/Capstone 1 Final Submission.docx.pdf>),
[final-defense review](docs/capstone_files/Final-Defense-Reviewer-1.pdf),
and [HR follow-up answers](docs/capstone_files/Follow-up-Questions-with-Answers-from-HR-1.pdf).
Those PDFs remain immutable evidence; the v1.1 addendum records the accepted
schema corrections.

The `.kiro/` directory contains mirrored specifications and persistent
project steering guidance.

## Database documentation warning

The original capstone does not provide one internally consistent database
schema:

| Source representation | Coverage |
|---|---:|
| Final Relation (Table 85) | 23 entities |
| Logical Database Model (Figure 163) | 18 tables |
| Physical Database Model (Figure 164) | 17 tables |
| Complete Data Dictionary entries | 12 tables |

These representations disagree on several columns and relationships. For
example, `employee.branch_id` is absent from Table 85 and the employee Data
Dictionary but present in the logical and physical models. Holiday dates
and government contribution rate/bracket structures are required by the
functional requirements but are absent from all source database models.

Do not generate migrations directly from only one source diagram. Use the
[schema audit](docs/database-schema.md), [revision record](docs/database-documentation-revision-log.md),
accepted [development baseline ADR](docs/adr/0001-development-baseline.md),
[schema integrity ADR](docs/adr/0002-schema-integrity-corrections.md), and
[canonical schema v1.1](docs/capstone_files/Canonical-Database-Schema-v1.1.md).

## Development baseline

ADR-0001 through ADR-0003 close the MVP development/schema gates: organization topology is
configurable, employee and payroll cardinalities are frozen, the orphan
benefit table is excluded, the real `.xls` workbook contract is documented,
and the PHP technical choices are selected. Production still requires official
branch display names, full effective-dated statutory contribution policies,
holiday-overtime treatment, and HR acceptance testing.

## Non-functional targets

- Data retrieval within 1 second under normal conditions.
- Dashboard, payroll computation, and report generation within 5 seconds.
- Authentication and role authorization for every protected operation.
- Hashed passwords and secure session or token handling.
- Chrome, Firefox, and Edge support.
- English interface, `MM/DD/YY` dates, and 24-hour time display.

## Recommended implementation sequence

1. Scaffold the frameworkless PHP/Composer application, Phinx migrations,
   seeders, and PHPUnit/PHPStan checks from ADR-0001, ADR-0002, ADR-0003, and
   canonical schema v1.1.
2. Implement authentication and RBAC before the business modules.
3. Build employee, schedule, attendance, request, salary, and contribution
   modules.
4. Integrate payroll, approval, payslip, and reporting workflows.
5. Complete role-scoped end-to-end and performance verification.

Implementation work should retain the original `REQ0xx` and `REQNxxx`
identifiers in tests, issues, and relevant commit messages for
traceability.

## License

No license has been added to this repository. Unless the project owners
state otherwise, the source and documentation should be treated as
all-rights-reserved project material.
