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
an executable application, `package.json`, database migrations, or deployment
configuration.

The planned implementation uses Node.js, TypeScript, Express, Sequelize,
and MySQL. Installation and runtime commands will become applicable after
the project foundation is scaffolded.

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
Node.js + Express application
Routes -> Controllers -> Services -> Validation/RBAC
    |
    | Sequelize
    v
MySQL database
```

Business calculations belong in service classes so payroll, attendance,
and contribution rules can be tested independently from controllers and
the user interface.

### Technology direction

| Area | Planned choice |
|---|---|
| Runtime | Node.js 24.x LTS with TypeScript |
| Web framework | Express |
| Database | MySQL |
| ORM and migrations | Sequelize |
| Validation | Zod |
| Authentication | BCrypt plus server-managed sessions |
| User interface | Server-rendered EJS/HTML/CSS with progressive JavaScript |
| PDF/report generation | PDFKit or Puppeteer |
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
| [Canonical schema v1.1](docs/capstone_files/Canonical-Database-Schema-v1.1.md) | Typed implementation dictionary and capstone correction addendum. |

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

ADR-0001 and ADR-0002 close the MVP development/schema gates: organization topology is
configurable, employee and payroll cardinalities are frozen, the orphan
benefit table is excluded, the real `.xls` workbook contract is documented,
and the technical choices are selected. Production still requires official
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

1. Scaffold the Node.js/TypeScript application, migrations, seeders, and tests
   from ADR-0001, ADR-0002, and canonical schema v1.1.
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
