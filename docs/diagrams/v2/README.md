# WBPMS System Diagrams — Version 2 (Revised)

Maintained against the WBPMS source code and database migrations. The request,
attendance-import, payroll, logical database, and physical database diagrams
were corrected on October 28, 2026 after review against the current implementation.

All diagrams are Mermaid `.mmd` files compatible with
**draw.io → Arrange → Insert → Advanced → Mermaid**.

Exported PNG images are located in `images/`.

Each diagram is styled with `%%{init}` using a **black-and-white base theme**
(`primaryColor: #ffffff`, `lineColor: #000000`, `primaryTextColor: #000000`)
to ensure clean monochrome output suitable for printing on one landscape page.

---

## Directory Contents

### Use Case Diagrams (`uc-*.puml` — PlantUML)

Use Case Diagrams are authored in **PlantUML** (`.puml`) to produce standard UML notation:
stickman figures for human actors (circle head, body, arms, legs), labeled ovals for use
cases, system boundary rectangle labeled `WBPMS (Web-Based Payroll Management System)`,
`<<include>>` and `<<extend>>` dashed arrows, and solid association lines.
The legacy `.mmd` files are kept but their PNGs are superseded by the PlantUML versions.

| PlantUML Source | Module | Actors |
|-----------------|--------|--------|
| `uc-01-authentication-user-management.puml` | Login, password reset, user CRUD | BusinessOwner, HRHead, Employee |
| `uc-02-employee-management.puml` | Employee records, lifecycle, documents, bank | HRHead |
| `uc-03-attendance-management.puml` | Biometric import, attendance, policy flags | HRHead |
| `uc-04-work-schedule-management.puml` | Schedules, holidays, positions, periods | HRHead |
| `uc-05-request-management.puml` | Leave, overtime, cash advance | BusinessOwner, HRHead, Employee |
| `uc-06-salary-benefits-payroll.puml` | Salary, contributions, payroll run lifecycle | BusinessOwner, HRHead |
| `uc-07-reports.puml` | All report types and export | BusinessOwner, HRHead |
| `uc-08-employee-self-service.puml` | Employee portal: attendance, requests, payslips | Employee |

Render command: `java -jar plantuml.jar -tpng -o images/ <file.puml>`

---

### Database Models (`db-*.mmd`)

| File | Type | Description |
|------|------|-------------|
| `db-01-conceptual-model.mmd` | Conceptual | High-level business entities and relationships only; no keys or types |
| `db-02-logical-model.mmd` | Logical | All tables with PKs, FKs, generic types, and relationships |
| `db-03-physical-model.mmd` | Physical | All tables with actual SQL column names, MySQL data types, and constraints — verified against migrations |

> **Note:** Table names in the physical model match the actual MySQL schema (no `_tbl` suffix).
> The reference image `ref diag.jpg` uses a different schema variant for reference purposes only.

---

### Data Flow Diagrams (`dfd-*.mmd`)

| File | Level | Description |
|------|-------|-------------|
| `dfd-00-context.mmd` | Context (Level 0) | System boundary with all external actors (BusinessOwner, HRHead, Employee, Biometric Device, SMTP Provider) and labeled top-level data flows |
| `dfd-01-level0.mmd` | Level 0 | Ten major processes (1.0–10.0) with data stores and actor flows; decomposition of the context diagram |
| `dfd-02-level1.mmd` | Level 1 | Detailed decomposition of Process 7.0 (Payroll Computation & Approval) into sub-processes 7.1–7.7 |

> **Note:** The original `dfd-01-level1.mmd` was retained for backwards reference but its content
> was actually a Level 0 DFD. Corrected files are `dfd-01-level0.mmd` and `dfd-02-level1.mmd`.

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

### Network Design (`net-*.mmd`)

| File | Description |
|------|-------------|
| `net-01-network-design.mmd` | Physical/logical network topology — client devices, biometric sites, application server (XAMPP/PHP/Apache), MySQL server, SMTP provider |

---

### System Architecture (`arch-*.mmd`)

| File | Description |
|------|-------------|
| `arch-01-system-architecture.mmd` | Full layered architecture — Browser → HTTP Layer → Application Layer → Domain Layer → Infrastructure Layer → Storage |

---

## Exported PNG Images (`images/`)

All 35 diagrams have been rendered to PNG using Mermaid CLI 12.0.0 with a white background.

| PNG File | Source |
|----------|--------|
| `dfd-00-context.png` | `dfd-00-context.mmd` |
| `dfd-01-level0.png` | `dfd-01-level0.mmd` |
| `dfd-02-level1.png` | `dfd-02-level1.mmd` |
| `net-01-network-design.png` | `net-01-network-design.mmd` |
| `arch-01-system-architecture.png` | `arch-01-system-architecture.mmd` |
| `db-01-conceptual-model.png` | `db-01-conceptual-model.mmd` |
| `db-02-logical-model.png` | `db-02-logical-model.mmd` |
| `db-03-physical-model.png` | `db-03-physical-model.mmd` |
| `cls-01-http-layer.png` | `cls-01-http-layer.mmd` |
| `cls-02-application-services.png` | `cls-02-application-services.mmd` |
| `cls-03-domain-layer.png` | `cls-03-domain-layer.mmd` |
| `cls-04-infrastructure-layer.png` | `cls-04-infrastructure-layer.mmd` |
| `uc-01-authentication-user-management.png` | `uc-01-authentication-user-management.mmd` |
| `uc-02-employee-management.png` | `uc-02-employee-management.mmd` |
| `uc-03-attendance-management.png` | `uc-03-attendance-management.mmd` |
| `uc-04-work-schedule-management.png` | `uc-04-work-schedule-management.mmd` |
| `uc-05-request-management.png` | `uc-05-request-management.mmd` |
| `uc-06-salary-benefits-payroll.png` | `uc-06-salary-benefits-payroll.mmd` |
| `uc-07-reports.png` | `uc-07-reports.mmd` |
| `uc-08-employee-self-service.png` | `uc-08-employee-self-service.mmd` |
| `act-01-payroll-lifecycle.png` | `act-01-payroll-lifecycle.mmd` |
| `act-02-request-workflow.png` | `act-02-request-workflow.mmd` |
| `act-03-attendance-import.png` | `act-03-attendance-import.mmd` |
| `fc-01-login.png` | `fc-01-login.mmd` |
| `fc-02-password-reset.png` | `fc-02-password-reset.mmd` |
| `fc-03-attendance-import.png` | `fc-03-attendance-import.mmd` |
| `fc-04-request-approval.png` | `fc-04-request-approval.mmd` |
| `fc-05-payroll-lifecycle.png` | `fc-05-payroll-lifecycle.mmd` |
| `fc-06-employee-lifecycle.png` | `fc-06-employee-lifecycle.mmd` |
| `seq-01-login.png` | `seq-01-login.mmd` |
| `seq-02-password-reset.png` | `seq-02-password-reset.mmd` |
| `seq-03-attendance-import.png` | `seq-03-attendance-import.mmd` |
| `seq-04-request-approval.png` | `seq-04-request-approval.mmd` |
| `seq-05-payroll-compute-approve.png` | `seq-05-payroll-compute-approve.mmd` |
| `seq-06-payslip-generation.png` | `seq-06-payslip-generation.mmd` |

---

## draw.io Compatibility

All `.mmd` files can be imported directly into draw.io via:
**Arrange → Insert → Advanced → Mermaid**

PlantUML `.puml` files (use case diagrams) cannot be imported directly into draw.io.
Their PNG exports are in `images/` and serve as the final rendered output for the manuscript.

The existing `db-03-physical-model.drawio` is a separate editable draw.io file
and has not been synchronized with the corrected physical-model `.mmd` source.
Use the `.mmd` source as authoritative until the draw.io file is refreshed.

The PNGs in `images/` are generated exports and must be re-rendered after source changes.
The source files updated on October 28, 2026 have not been re-rendered in this workspace;
render them before using the PNGs in a manuscript. PlantUML diagrams use the default DPI.

---

## Styling Notes

- All diagrams use `%%{init}` with a black-and-white base theme for print compatibility.
- Use case diagrams use `graph LR` with `style rx:50,ry:50` to produce oval-like shapes
  for use cases. Mermaid does not natively render UML stickman actors; actor nodes use
  the `([...])` ellipse shape as the closest available approximation.
- DFD process nodes use ellipse `(["..."])` shapes; external entities use rectangle `["..."]`;
  data stores use cylinder `[("...")]` shapes following Yourdon/DeMarco DFD notation.
- Physical DB model column types are written without special characters (e.g., `varchar50`
  instead of `varchar(50)`) to comply with Mermaid erDiagram attribute syntax.
- Sequence and flowchart diagrams retain their original correct structure.
