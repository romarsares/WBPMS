# WBPMS Client Evaluation and Implementation Plan

**Status:** Evaluation only; no application implementation authorized in this task.  
**Scope reviewed:** current PHP application, route declarations, application services, PDO queries, Phinx migration history, rendered views, and project requirements/user manual.  
**Implementation baseline:** native PHP, PDO, MySQL, Composer, Phinx; preserve the existing server-rendered application and its role gates.

## 1. Project overview

WBPMS supports HR, Business Owner, and Employee roles for employee records, attendance, requests, salary history, statutory contribution policies and records, branch payroll runs, approval, payslips, and operational reports. The relevant implementation is in `app/Http/Controllers`, `app/Application`, `app/Infrastructure`, `resources/views`, `routes/web.php`, and `database/migrations`.

The current system already contains substantial versions of the requested capabilities. This plan therefore separates verified gaps from features that exist but may be failing or need clarified scope. No source code or schema was changed during evaluation.

## 2. Client requirements breakdown

| Area | Requested outcome | Current assessment |
|---|---|---|
| Payroll | Fix Salary Adjustment error; remove 13th Month Pay document; paginate; add Owner and HR filters | Manual payroll adjustments and 13th-month report are present. Payroll run lists are capped at 100 / recent records rather than paginated. HR list has no filters; Owner shows pending and recent runs without user-selectable filters. The exact adjustment error is not supplied. |
| Salary | Remove “Define” from relevant labels; implement Salary Rate Update; remove Salary Configuration from documentation; use “Daily Rate Configuration” | Rate update is implemented as an effective-dated new salary row with prior history retained. Some documentation still says “define” and “salary configuration”; UI already says “Update Rate” and “Daily Rate”. Confirm if the reported feature gap is a failure in the existing workflow. |
| Benefits and deductions | Add filtering; remove specified “(D)” section/item | Benefits overview queries the latest 100 contribution records without filters; policy list also has no filters. The literal `(D)` was not found in searchable relevant source/docs, so the target is unidentified. |
| Reports | Remove unnecessary HTTP-related Printing reference; paginate; include `HRApprove` in Request dropdown; employee payslip printing; transaction slip printing | Report previews and print views exist; employee HTML payslip print route exists but the detail page's “Print / Download” control links to the PDF download route instead of the HTML print route. Transaction slips endpoint/view and print control exist. Report preview queries materialize all rows and have no page controls. The Request report status dropdown currently omits `HRApproved`; this is the likely intended control. No user-facing HTTP reference was located in report printing copy. |
| Requests | Owner filtering | Owner request queue currently fetches active/pending work and up to 100 actioned records without filters; HR queue already accepts status, type, and text search. |
| UI/UX | Consistent professional responsive UI across roles, targeted cleanup | Existing `public/assets/app.css` and shared `resources/views/layout.php` provide a common shell, but multiple pages use inline styling and have inconsistent `page-head`/`page-header`, table, spacing, and icon/emoji treatment. Review is needed across all role views; avoid a wholesale redesign. |

## 3. Current implementation evaluation

### 3.1 Payroll and manual adjustment

- `routes/web.php` restricts HR payroll routes to `HRHead`; Owner approval routes are restricted to `BusinessOwner`.
- `PayrollController::index()` (`app/Http/Controllers/PayrollController.php`) selects payroll runs, orders newest first, and applies `LIMIT 100`. The displayed total and status-card counts are derived from only those fetched rows. There are no branch, period, or status query filters and no page navigation.
- `OwnerController::payrollList()` supplies pending and recent runs to `resources/views/owner/payroll/index.php`; this is an approval queue/recent activity view rather than a complete searchable payroll history. It currently has no filters or pagination.
- Manual adjustment is wired through `PayrollController::adjustForm()` / `adjust()` and `PayrollAdjustmentService` to `resources/views/hr/payroll/adjust.php`. The service validates amount/reason, permits `Computed` and `Returned` runs, locks the relevant row in a transaction, updates itemized earning/deduction and payroll totals, writes `payroll_adjustment`, and records an audit event. Approved runs are excluded by state validation. The current route and service design do not identify a definite defect without the reported error message and reproduction details. Check database migration/schema compatibility (`20260906000012_add_manual_payroll_adjustments.php`), submitted field names, runtime exception/log, and expected run status before proposing a code fix.
- 13th-month calculation remains part of `PayrollService::compute13thMonth()` and appears in `ReportsController`, report export, report tile, and the user manual. `REQ049` and the requirements/design documents describe the calculation. Removing a report/document must not silently remove payroll computation or alter existing approved payroll records.

### 3.2 Salary

- `SalaryController` routes are HR-only. Its list uses `SalaryService::list()` with employee search, branch, and status filters; the service caps results at 300 without pagination.
- Salary update is already implemented in `SalaryController::update()` and `SalaryService::update()`: insert a new effective-dated salary row, close/supersede the previous active row, preserve history, and audit. `resources/views/hr/salary/index.php` exposes **Update Rate**; the edit form is titled **Update Salary Rate** and explains history preservation.
- Documentation language remains inconsistent: `docs/requirements.md` Requirement 8 and `docs/User_Manual.md` refer to “define”/“salary configuration”; the UI uses daily-rate wording. This is a terminology/documentation alignment task unless the client can provide a failing rate-update scenario.
- `salary` is already effective-dated and consumed by payroll calculation. A label change does not require schema migration.

### 3.3 Benefits and deductions

- `BenefitsController::index()` reads policy versions and the latest 100 contribution records. `resources/views/hr/benefits/index.php` renders approved-policy summary and contribution records with lock/unlock actions. There are no filter inputs or server-side query parameters.
- `BenefitsController::policies()` lists every policy version without filters. It is a separate policy administration view and should not be unintentionally affected if “filtering” refers only to contribution records.
- Payroll contribution deductions are represented in `deduction` and linked to `contribution_record`; the approved development baseline explicitly excludes the orphan legacy `benefits_tbl`. Employee contribution shares are payroll deductions, while employer shares are recorded separately. Do not remove SSS, PhilHealth, Pag-IBIG calculation, policy versions, contribution records, or lock/unlock behavior as a proxy for deleting the unidentified “(D)” item.
- Relevant tables include `contribution_policy_version`, `sss_bracket`, `philhealth_rate`, `pagibig_rate`, `contribution_record`, `deduction`, and payroll entities. Filtering should be query-only and need not require schema changes.

### 3.4 Reports and printing

- `ReportsController::index()` shows summary cards and the latest 20 approved payroll runs; there is no paging state. `resources/views/hr/reports/index.php` links to report types including 13th-month, transaction slips, and employee payslip report.
- `ReportsController::preview()` supports payroll, attendance, requests, contributions, and employee reports. `previewData()` and its view currently provide filter forms but no pagination contract. Depending on report type and data volume, loading all rows into PHP can grow memory and response time; use bounded SQL paging and a separate total-count query while keeping exported/printed output semantics explicit.
- Employee payslip report and bulk print are routed to `ReportsController::payslipsReport()` and `bulkPrintPayslips()`, both HRHead/BusinessOwner. Employee self-service has an ownership-scoped HTML print route (`/employee/payslips/{id}/print`) in `EmployeePortalController::payslipPrint()`, but `resources/views/employee/payslip.php` currently links its “Print / Download” control to the PDF download URL, not that print route. Expose a distinct direct print action while preserving PDF download and `EmployeeScope` ownership checks. The transaction-slip page already includes filter and print-preview controls.
- Transaction-slip printing is already present at `/hr/reports/transaction-slips`. It joins approved payroll to employee and active bank details and supports period, branch, and employee filters. It renders missing-account notices rather than inventing bank details. Keep approved-run scope and the actual BDO cash transaction/deposit-slip semantics; this is not a bank-transfer API.
- `resources/views/hr/reports/preview.php` and `ReportsController::previewFilters()` both whitelist request-report statuses as `Pending`, `Approved`, `Rejected`, and `Cancelled`; the actual request workflow uses `HRApproved`. Add canonical `HRApproved` to both dropdown options and server-side validation if confirmed as the intended “Request Module dropdown.” The report type dropdown is for request types and should not receive a status value. The client wrote `HRApprove`; confirm the exact value intended is the system's `HRApproved` state.
- Search of the report print templates and relevant user-manual printing section found no user-facing “HTTP” text. References to `http_build_query` and the `Http` namespace are code identifiers, not user-facing copy. The client should identify the screen/text they mean before any removal. The existing manual explains browser Print / Ctrl+P and Save as PDF; do not remove these practical instructions absent clarification.

### 3.5 Requests and roles

- HR request listing (`RequestsController::index()` + `RequestService::list()`) accepts status, request type, and employee/name/number search filters. It is HR-only.
- Owner request listing (`OwnerController::requestList()`) calls `ownerPendingList()` and `ownerActionedList()` without filters. Pending list currently includes `Pending`, `HRApproved`, and `Returned` active requests; action history includes `Approved` and `Cancelled` and has `LIMIT 100`. This differs from the User Manual, which says only HR-pre-approved requests appear for Owner action. Confirm expected default actionable statuses and align documentation/workflow deliberately; missing Owner filters remain a clear gap.
- Owner workflows and their role gates are already implemented for review/approve/return/cancel. Any filters must not remove actionable `Pending` items, mix HR-only actions into Owner UI, or expose archived rows unless specified.
- Likely safe query filters are request type, status, employee search, and submitted date range. The exact desired Owner filters and whether they apply to both pending and history panels need confirmation.

### 3.6 UI/UX across roles

- Shared frame/navigation is `resources/views/layout.php`; global styles are `public/assets/app.css`. Role menus are identity-driven. This is the highest-leverage place to normalize typography, controls, responsive breakpoints, and shared spacing without changing workflows.
- Targeted views include HR payroll/salary/benefits/reports/requests, Owner payroll and requests, and Employee dashboard/requests/payslips/attendance. For HR, prioritize dense tables, branch/period/status filters, adjustment validation, and forms. For Owner, prioritize approval queues, request filters, decision actions, and summaries. For Employees, prioritize personal records, request forms/statuses, payslip actions, and narrow-screen table behavior. Employee print views are standalone/print-specific and should be validated separately from the authenticated shell.
- Current view patterns mix `page-head` and `page-header`, inline color/spacing declarations, emoji and Unicode symbols, custom table markup, and inconsistent button sizing. Inspect for actual overflow and alignment defects at desktop and mobile widths before changing shared CSS.
- Keep the Light Diamond brand, existing navigation and content hierarchy, workflow controls, accessible labels, status meaning, CSRF fields, escaped output, and print-specific CSS. Remove only clearly decorative clutter and awkward copy the client identifies or the review can tie to a concrete usability issue. “AI-generated-looking” is subjective; use restrained, businesslike copy and plain iconography rather than deleting functional explanatory text.

## 4. Requested changes: approach, files, and risk

| Change | Expected areas (implementation phase only) | Data/schema | Permission and dependency notes | Main regression risks |
|---|---|---|---|---|
| Diagnose/fix payroll Salary Adjustment error | `PayrollController.php`, `PayrollAdjustmentService.php`, `resources/views/hr/payroll/adjust.php`, `resources/views/hr/payroll/detail.php`; adjustment migration for schema verification | No migration expected if current schema matches `payroll_adjustment`, `payroll_earnings`, `deduction`, `payroll`, and `payroll_run` | HR-only route; preserve reason/audit and `Computed`/`Returned` guard | Broken totals, duplicate itemization, stale run status, allowing approved-run mutation, non-atomic audit/write |
| Remove 13th-month “document” | `ReportsController.php`, report overview/export/view files, `PayrollService.php` only if client explicitly means calculation, plus `docs/User_Manual.md`/requirements | No migration for removing a report link/document; existing computed data is not deleted | Currently HR and Owner can view report; employee payslips unaffected | Removing the report may contradict REQ049 or remove a statutory calculation client did not intend to withdraw |
| Payroll pagination and filters | `PayrollController.php`, `OwnerController.php`, payroll services/repository query helpers if introduced, `resources/views/hr/payroll/index.php`, `resources/views/owner/payroll/index.php` | No migration expected; indexes can be considered only after query plan measurement | HR sees management statuses; Owner sees approval/history scope only. Branch and period filters should use canonical period/run joins | Count drift, filters lost between pages, incorrect summaries over current page only, Owner pending queue hidden by filter |
| Salary terminology/update | `docs/requirements.md`, `docs/User_Manual.md`, possibly `docs/product.md`; salary views only where matching label exists; `SalaryController`/`SalaryService` only if repro shows defect | No migration | HR-only existing write flow; preserve effective-date validation and history | Wording implies mutable in-place edit; future pay periods or history altered incorrectly |
| Benefits filtering and “(D)” removal | `BenefitsController.php`, `resources/views/hr/benefits/index.php`, `resources/views/hr/benefits/policies.php`, perhaps report views/docs after target identified | Filters require no schema. Removal cannot be scoped until “(D)” source is identified | HR-only. Policy filters and contribution-record filters are distinct surfaces | Deleting a contribution, policy, deduction category, or a legacy concept may break payroll/report reconciliation |
| Report pagination / status dropdown | `ReportsController.php`, `resources/views/hr/reports/preview.php`, `resources/views/hr/reports/index.php`, report view; possibly `RequestService.php` only if adding query filters there | No schema expected. SQL count/page query work | HR/Owner both report roles; `HRApproved` must be a status option only in the intended status control | Paginated print/export accidentally omits rows; wrong status vocabulary; large unbounded result still queried |
| Payslip/transaction printing | `EmployeePortalController.php`, `ReportsController.php`, `resources/views/employee/payslip.php`, `resources/views/hr/reports/payslips.php`, `transaction-slips.php`, route definitions | Existing `payslip`, `payroll`, `bank_details` tables are sufficient | Preserve employee own-record scope; HR/Owner report access and approved payroll-only transaction slips | Cross-employee disclosure, duplicate/missing slips, wrong bank account effective date, print CSS clipping; employee detail currently routes its combined Print/Download control to PDF download rather than the separate HTML print route |
| Owner request filtering | `OwnerController.php`, `RequestService.php`, `resources/views/owner/requests/index.php` | No schema for status/type/search/date predicates | Owner role only; preserve pending workflow statuses and actioned history | Excluding actionable `HRApproved` requests, showing archived rows, count/filter mismatch |
| UI consistency/responsive fixes | `public/assets/app.css`, `resources/views/layout.php`, affected views in HR/Owner/Employee areas | None | Do not alter role navigation or endpoint authorization through UI-only changes | Shared CSS causes regressions on unrelated modules; narrow/mobile tables become unusable; print styles affected |

Potential table families consulted: `payroll_period`, `payroll_run`, `payroll`, `payroll_earnings`, `deduction`, `payroll_adjustment`, `payslip`, `salary`, `contribution_policy_version`, contribution rate/bracket tables, `contribution_record`, `employee`, `employee_branch_assignment`, `request`, request detail tables, `request_type`, `bank_details`, and `audit_logs`. Most requested changes are query/view/service changes. No current evidence supports a schema migration.

## 5. Documentation evaluation

The principal references are `docs/requirements.md` (REQ049, REQ053–REQ057, REQ058–REQ064, REQ065–REQ073, REQ076–REQ080), `docs/product.md`, `docs/design.md`, `docs/User_Manual.md`, `docs/adr/0001-development-baseline.md`, `docs/adr/0002-schema-integrity-corrections.md`, and `docs/tasks.md`.

- The manual presently documents 13th Month Pay as a report, and requirements explicitly require calculation and reporting. Removing the report tile/manual subsection may still leave required calculation behavior; client direction is needed before editing authoritative requirements.
- The requirements use “define” and “salary configuration,” while current salary UI uses “Add Salary Record,” “Daily Rate,” and “Update Rate.” A controlled terminology update should preserve underlying “salary” domain/schema names unless a separate naming migration is requested (none is indicated).
- Existing project docs describe employee payslips, 13th-month, transaction/deposit-slip lists, and reporting. No database-documentation change is indicated for view/query enhancements.
- No `(D)` marker or HTTP wording was found in searchable report printing UI/manual sections. Binary Word/PDF source material was not exhaustively text-extracted, so the exact reference may exist there; client should point to the source/page or provide a screenshot.

## 6. Proposed phased implementation sequence

Priority places correctness fixes before enhancements and visual refinements.

### Phase 0 — Resolve acceptance details and reproduce the reported defect

1. Obtain exact Salary Adjustment error text, steps, user role, payroll run status, employee/run identifiers from a safe non-production fixture, and expected result.
2. Identify the exact “13th Month Pay document,” `(D)` element, and Printing text containing HTTP. The likely Request dropdown is the Request report status filter; confirm that `HRApprove` means the existing `HRApproved` workflow state.
3. Confirm required filters for HR payroll, Owner payroll, Owner requests, benefits records/policies, and reports, plus whether pagination should also cover report output or only on-screen tables.
4. Confirm whether 13th-month calculation remains required when the report document is removed. Treat current REQ049 as active until the client approves a requirements change.

### Phase 1 — Payroll adjustment correctness fix

1. Reproduce on a development/test database and compare live schema to the migration-defined fields.
2. Trace form POST values, validation, transaction statements, affected row totals, audit row, and run state transition.
3. Make the smallest fix consistent with the actual error; preserve auditable itemization, atomicity, and approved-run immutability.

### Phase 2 — Existing feature completion and scoped reporting changes

1. Verify effective-dated Salary Rate Update end-to-end; fix only a reproduced failure. Standardize UI/document wording to “Daily Rate” / “Daily Rate Configuration” as confirmed.
2. Add bounded, parameterized filtering and pagination for HR payroll history and Owner payroll panels; define summaries from the full filtered result set, not just the current page.
3. Add Owner request filters with independent pending/actioned scopes and preserve workflow status eligibility.
4. Add Benefits filters after confirming which records and policy lists are in scope.
5. Add report preview pagination without truncating complete print/export datasets; include `HRApproved` in both Request report filter options and server-side validation if confirmed as the intended dropdown.
6. Expose the existing employee HTML payslip print route as a distinct print action and retain PDF download. Keep transaction-slip print controls and approved-run guard intact.
7. Make only the confirmed document/copy removals. Keep salary history, contribution calculations, audit records, payslip output, and transaction slips intact unless the client specifically directs otherwise.

### Phase 3 — Printing and documentation alignment

1. Exercise employee self-service payslip printing and HR/Owner payslip print/bulk print; verify role and employee ownership checks.
2. Exercise transaction slip filters and print mode on approved runs, including missing bank details and multi-branch periods.
3. Remove the exact confirmed HTTP reference from the correct user-facing printing copy; preserve browser print instructions if those are not the identified text.
4. Update `docs/User_Manual.md`, `docs/requirements.md`, and relevant project docs only to reflect client-approved behavior and labels.

### Phase 4 — Targeted UI consistency and responsive behavior

1. Review shared shell and common CSS tokens/control styles first, then patch only affected HR, Owner, and Employee views.
2. Align page heading spacing, cards, filters, tables, form labels, button sizes, dropdown sizing, status badges, empty/error states, and breakpoints.
3. Remove decorative or awkward copy only where specifically identified or demonstrably redundant; preserve operational guidance, status explanations, and accessibility labels.
4. Check narrow-screen overflow and print layout independently.

## 7. Testing and validation checklist

### Payroll / Salary

- [ ] HR can apply an earning and a deduction to a Computed or Returned run; totals and itemized rows reconcile and the audit row identifies actor/reason.
- [ ] Invalid amount/reason, stale or mismatched run/payroll IDs, transaction failure, and Approved run attempts fail safely without partial writes.
- [ ] Salary rate update creates a new effective row, closes the prior row correctly, preserves old rows and payroll snapshots, rejects invalid/overlapping effective dates, and feeds only future eligible computations.
- [ ] Payroll filters combine correctly; page boundaries, empty pages, totals, and state cards represent the whole filtered set.
- [ ] Owner panel filters/pagination do not hide all pending work by default and do not expose HR-only controls.

### Benefits / Requests / Reports

- [ ] Benefits program/status/date/employee filters (as approved) use parameterized SQL, compose correctly, preserve lock/unlock actions, and show accurate counts.
- [ ] Owner request filters correctly separate active pending/review items from action history, include `HRApproved` where relevant, preserve employee search, and exclude archived requests by default.
- [ ] Report list pagination returns stable ordering and accurate count; print/CSV/PDF outputs include all filtered records unless a separately requested export limit is specified.
- [ ] Request status dropdown includes exactly the approved set, including `HRApproved` only in the identified status control; request-type dropdown remains request types.
- [ ] Employee payslip detail exposes separate HTML Print and PDF Download actions; printing is tested for own record, invalid ID, and cross-employee ID; HR/Owner report print access matches route policy.
- [ ] Transaction slips include only Approved payroll, retain selected branch/period/employee, use the correct active bank-details record, and visibly flag missing account data.

### UI / Access / Regression

- [ ] HRHead, BusinessOwner, and Employee route access matrix remains enforced server-side regardless of filters or form visibility.
- [ ] CSRF validation, HTML escaping, role navigation, employee ownership scope, audit history, and approved payroll immutability are unchanged.
- [ ] Desktop and mobile checks cover HR payroll, salary, benefits, reports, requests; Owner payroll/requests; Employee payslip and portal pages.
- [ ] Tables and filter forms do not overflow; keyboard focus, labels, contrast, empty states, and button semantics remain usable.
- [ ] Print preview is checked separately from responsive screen layout for payslips, transaction slips, and report output.
- [ ] Run focused regression tests and the relevant existing PHPUnit/PHPStan checks after implementation; no test or migration was run as part of this evaluation-only task.

## 8. Risks and dependencies

1. **Unspecified failure:** “Salary Adjustment error” cannot be safely diagnosed without an error message and reproduction. The existing path is implemented; speculative edits could damage payroll totals.
2. **Conflicting requirement:** deleting the 13th-month report may conflict with REQ049 and manual/report documentation. Removing a presentation artifact is different from removing calculation behavior.
3. **Ambiguous deletion target:** `(D)` is not identifiable in searchable source; removal must wait for an exact screen/label/screenshot.
4. **Dropdown spelling/scope:** `HRApproved` is the actual workflow status and is missing from the Request report status dropdown. The client wrote `HRApprove`; verify this is shorthand for `HRApproved`, and do not add it to the separate request-type selector.
5. **Pagination and totals:** current capped queries compute some summary counts from fetched subsets. Pagination requires count queries and separate all-filtered aggregate queries to avoid misleading payroll or report totals.
6. **Printing vs pagination:** screen pagination must not silently truncate print packages or compliance/business records.
7. **Shared CSS reach:** `app.css` affects all modules and roles; changes require broad visual regression review.
8. **Historical data:** salary and payroll are effective-dated/audited. Updates and removals should not rewrite approved snapshots, deduction rows, payslips, bank history, or audit evidence.
9. **Query growth:** request/report filters should remain parameterized, preserve deterministic ordering, and be checked for query cost as rows grow. Schema indexes are not currently justified without measurement.

## 9. Client clarification required

1. What exact error appears during “Salary Adjustment,” and what steps/status cause it? Is this the manual payroll adjustment screen (`/hr/payroll/{run}/employees/{payroll}/adjust`) or the Salary Rate Update screen (`/hr/salary/{salary}/edit`)?
2. Does “remove the 13th Month Pay document” mean remove the Reports tile/printable report, remove a separate generated document, or discontinue both report and calculation? Should REQ049 remain in force?
3. Which exact `(D)` label/section should be removed? Please identify the page or provide a screenshot. Should the removal affect only a displayed section or also stored contribution/deduction data?
4. Which printing text contains the unwanted HTTP reference, and where is it shown? Is the reference in application copy, the manual, or a source document?
5. Does `HRApprove` mean the canonical `HRApproved` status? The Request report status filter currently omits it and is the likely intended dropdown. Confirm if another request selector was meant.
6. What filters are expected for HR/Owner payroll, Benefits, Reports, and Owner requests? Suggested candidates are branch, payroll period/date range, run/request status, request type/program, and employee search. Confirm which apply to each screen.
7. Should pagination apply to on-screen report previews and dashboard summaries only, or should printed/exported documents also be divided into pages (while still containing the complete filtered dataset)?
8. For Owner request filtering, should filters apply independently to both “Awaiting Your Approval” and “Approval History,” and which actionable states should appear by default?
9. For Employee Payslip Printing, should the existing HTML print route be exposed as a separate Print action alongside PDF Download (recommended), or is a different format/layout required? For Transaction Slip Printing, is a layout or bulk-print change intended beyond the existing period/branch/employee filtered print-preview workflow?

## 10. Evaluation outcome

Proceed first with exact defect reproduction. The clearest implementation gaps are server-side pagination and richer filters for payroll, report lists, benefits records, and Owner requests; the Request report status filter also needs `HRApproved` added to its view options and server-side whitelist after confirming the client's spelling. Employee payslip printing has an existing route but its detail-page control currently points to PDF download, so expose a distinct print action. Transaction-slip printing and salary rate history already exist and should be verified rather than recreated. Removal requests remain gated on identifying the 13th-month document, `(D)` item, and HTTP reference. Most likely work requires no database migration; propose one only if investigation demonstrates that current persisted structures cannot support explicitly confirmed behavior.
