# WBPMS System Diagrams — Version 2 (Simplified)

Generated from a read-only analysis of the WBPMS source code at
`C:\xampp\htdocs\wbpms\` on October 23, 2026.

All diagrams are Mermaid `.mmd` files compatible with
**draw.io → Arrange → Insert → Advanced → Mermaid**.

Each diagram is styled with `%%{init}` using a **black-and-white base theme**
(`primaryColor: #ffffff`, `lineColor: #000000`, `primaryTextColor: #000000`)
to ensure clean monochrome output suitable for printing on one landscape page.

---

## Directory Contents

### Use Case Diagrams (`uc-*.mmd`)

| File | Module | Actors |
|------|--------|--------|
| `uc-01-authentication-user-management.mmd` | Login, password reset, user CRUD | BusinessOwner, HRHead, Employee |
| `uc-02-employee-management.mmd` | Employee records, lifecycle, documents, bank | HRHead |
| `uc-03-attendance-management.mmd` | Biometric import, attendance, policy flags | HRHead |
| `uc-04-work-schedule-management.mmd` | Schedules, holidays, positions, periods | HRHead |
| `uc-05-request-management.mmd` | Leave, overtime, cash advance | BusinessOwner, HRHead, Employee |
| `uc-06-salary-benefits-payroll.mmd` | Salary, contributions, payroll run lifecycle | BusinessOwner, HRHead |
| `uc-07-reports.mmd` | All report types and export | BusinessOwner, HRHead |
| `uc-08-employee-self-service.mmd` | Employee portal: attendance, requests, payslips | Employee |

---

### Database Models (`db-*.mmd`)

| File | Type | Description |
|------|------|-------------|
| `db-01-conceptual-model.mmd` | Conceptual | High-level entities and relationships only |
| `db-02-logical-model.mmd` | Logical | Key tables with PKs, FKs, and main attributes |
| `db-03-physical-model.mmd` | Physical | Core tables with actual SQL data types, focused on key columns |

---

### Data Flow Diagrams (`dfd-*.mmd`)

| File | Level | Description |
|------|-------|-------------|
| `dfd-00-context.mmd` | Level 0 | System boundary with all external actors and top-level flows |
| `dfd-01-level1.mmd` | Level 1 | Ten major processes with data stores and actor flows |

---

### System Flowcharts (`fc-*.mmd`)

| File | Workflow |
|------|----------|
| `fc-01-login.mmd` | Login and session redirect |
| `fc-02-password-reset.mmd` | Forgot password, token generation, reset |
| `fc-03-attendance-import.mmd` | XLS upload, dedup, parse, match, approve |
| `fc-04-request-approval.mmd` | Leave balance check, HR + Owner approval |
| `fc-05-payroll-lifecycle.mmd` | Draft → Compute → Submit → Approve → Payslip |
| `fc-06-employee-lifecycle.mmd` | Hire → Transfer → Archive → Rehire |

---

### Activity Diagrams (`act-*.mmd`)

| File | Process | Swimlanes |
|------|---------|-----------|
| `act-01-payroll-lifecycle.mmd` | Full Payroll Cycle | HR Head, Business Owner, Employee, System |
| `act-02-request-workflow.mmd` | Request Submission and Approval | Employee, HR Head, Business Owner, System |
| `act-03-attendance-import.mmd` | Attendance Import | HR Head, System |

---

### Sequence Diagrams (`seq-*.mmd`)

| File | Interaction |
|------|-------------|
| `seq-01-login.mmd` | User Login |
| `seq-02-password-reset.mmd` | Password Reset |
| `seq-03-attendance-import.mmd` | Attendance Import |
| `seq-04-request-approval.mmd` | Request Submission and Approval |
| `seq-05-payroll-compute-approve.mmd` | Payroll Compute and Approval |
| `seq-06-payslip-generation.mmd` | Payslip View and Download |

---

### Class Diagrams (`cls-*.mmd`)

| File | Layer |
|------|-------|
| `cls-01-http-layer.mmd` | Controllers, Router, Middleware |
| `cls-02-application-services.mmd` | Application Services |
| `cls-03-domain-layer.mmd` | Domain — Parsers, Generators, Calculators |
| `cls-04-infrastructure-layer.mmd` | Repositories, Gateway, Mailer |

---

## Styling Notes

- All diagrams use `flowchart LR` (horizontal) for use case and activity diagrams to better fit landscape orientation.
- Sequence diagrams use reduced participant counts (implementation details removed).
- Physical DB model shows key columns only per table; non-critical audit timestamps omitted.
- Black-and-white theme applied via `%%{init}` on every file for print compatibility.
