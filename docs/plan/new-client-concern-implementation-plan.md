# New Client Concern: Investigation and Implementation Plan

**Status:** Planning and issue triage; implementation has not started.  
**Source:** Screenshots in `issues/New Client Concern/`.  
**Application baseline:** Existing PHP/PDO/MySQL application, HR-only route gates, attendance import review workflow, and audit history.

## 1. Issue summary

The supplied screenshots identify five concerns:

| Area | Evidence / request | Initial interpretation |
|---|---|---|
| Attendance import | `Check if duplicate file allowed.png` shows the Import Attendance page and repeated-looking filenames in Recent Imports. | Determine whether identical workbook content can be imported more than once, and explain/repair behavior if the same file is being accepted. The current design says SHA-256 checksum prevents duplicate imports; establish whether the rows shown are actually identical files or distinct workbooks with similar names. |
| Request report | `Add HRApproved on the dropdown.png` shows `/hr/reports/requests` status options All, Pending, Approved, Rejected, Cancelled. | Add canonical `HRApproved` to the Request report status filter and its server-side accepted values. |
| Benefits & Deductions | `Add Filter in Benefits Deduction.png` shows the Benefits overview with recent contribution records and active policies. | Add useful filters to the contribution record list; confirm whether policy versions also need filtering. |
| Salary rate update | `890969060_2049199935699450_7756932164475541506_n.png` shows `/hr/salary/119` failing with `SQLSTATE[HY000]: General error: 3819 Check constraint 'chk_salary_period' is violated.` Effective From shown is 26/11/2026. | Reproduce and trace effective-date period update/insert against the actual schema and database constraint. Correct the date-range transition while preserving salary history. |
| Manual payroll adjustment | `856834803_1685410199968555_3841381255264323811_n.png` shows `/hr/payroll/25/employees/427/adjust` failing with `SQLSTATE[HY093]: Invalid parameter number`. | Trace the PDO statement and its bound parameters on the adjustment POST path, then fix the binding mismatch and ensure the adjustment transaction remains atomic. |

## 2. Current implementation context

- Attendance import routes and upload/review/approve/cancel behavior are in `routes/web.php` and `app/Http/Controllers/AttendanceController.php`. The controller reports duplicate checksum detection; `attendance_import_batch.file_checksum` is intended to be unique. Import records also track duplicate punches separately from duplicate workbook files. Do not conflate repeated punch rows inside a workbook with re-uploading the same workbook.
- Salary effective dating is handled by `SalaryController` and `SalaryService`. An update inserts a new salary row and closes the prior row before its effective date. The database migration defines a period constraint; inspect its exact predicate and installed database definition before changing update ordering or validation.
- Manual payroll adjustment uses `PayrollController` and `PayrollAdjustmentService`, with a PDO persistence path, reason/audit data, and a restriction to eligible unapproved payroll runs. The visible error indicates a statement parameter mismatch but does not identify which statement without tracing the request.
- Request report status choices and validation are implemented in `resources/views/hr/reports/preview.php` and `ReportsController::previewFilters()`. The request workflow's canonical state is `HRApproved`.
- Benefits overview is rendered by `resources/views/hr/benefits/index.php`; controller/service queries supply current policy summaries and recent contribution records. Existing behavior has no contribution filters.
- The screenshot evidence is from the production URL, but this plan does not authorize direct production edits, data changes, or uploads. Reproduce defects using a safe development/test database or anonymized fixture.

## 3. Goals and non-goals

### Goals

1. Resolve both reproducible database/runtime errors using the smallest safe code change.
2. Make duplicate workbook behavior explicit and reliably enforced for identical file content, including concurrent submissions.
3. Add `HRApproved` to the correct Request report status filter and accepted server-side query values.
4. Add practical filters to contribution records with deterministic ordering and clear reset behavior.
5. Preserve role authorization, CSRF protections, auditability, payroll calculations, import review/approval workflow, and historical records.

### Non-goals unless separately requested

- Changing payroll adjustment eligibility, approval rules, salary history semantics, or contribution calculations.
- Deleting/replacing existing attendance imports or changing attendance records to hide duplicate-looking history.
- Filtering policy versions if the client only intended contribution records.
- Adding database migrations unless implementation evidence shows the current schema cannot enforce the agreed behavior.

## 4. Work plan

### Phase 1 — Reproduce and diagnose

1. Check the current working tree and applicable project instructions; inspect `SalaryService`, `PayrollAdjustmentService`, their repositories/gateways, relevant controllers, and salary/import migrations.
2. Reproduce salary update on a disposable database using an employee with a current open-ended salary row and a valid future effective date. Capture the generated update/insert sequence and compare it with `chk_salary_period` as installed and as declared in migrations.
3. Reproduce manual adjustment with the screenshot's route shape and an eligible `Computed` or `Returned` payroll run. Record the failing SQL statement, placeholder names/count, and parameter map. Check all adjustment types and both earning/deduction paths.
4. For attendance, compare checksums and source filenames for the repeated-looking recent imports. Verify whether duplicate rejection is checksum-based, whether uniqueness is enforced in the deployed schema, and how a cancelled import affects a later retry. Establish expected policy: identical bytes are rejected; distinct workbook bytes may be accepted even if filenames match, subject to period/overlap rules already in force.
5. Confirm whether Benefit filters should apply to contribution records only or also policy versions. Proposed record filters: program, status, employee name/number, and payroll period/date range, with only fields supported by existing data/query relationships.

### Phase 2 — Correct salary and adjustment defects

1. Fix salary period transition so the previous row closes on the day before the new row starts, with an update/insert sequence that satisfies the actual period constraint at every statement boundary. Keep operations transactional and lock the employee's salary rows to avoid concurrent overlapping updates.
2. Validate that the effective date is valid and does not create an overlap or empty/reversed period. Show a field-level or actionable validation message for invalid dates rather than a raw database constraint exception.
3. Correct the PDO placeholder/binding mismatch in the manual adjustment path. Ensure each placeholder is bound exactly once as required by the driver's native/prepared-statement behavior; avoid reusing a named placeholder where PDO configuration does not support it.
4. Preserve adjustment amount validation, reason requirement, eligible run-state check, audit event, itemized earning/deduction row, and atomic recalculation of employee and run totals.
5. Convert expected constraint/validation failures into safe form feedback; do not expose SQL details to users.

### Phase 3 — Duplicate workbook handling

1. Confirm checksum generation uses the uploaded file's raw bytes and occurs before the preview/session lifecycle can replace or mutate the file.
2. Confirm duplicate-check and insert are race-safe through a database unique constraint; handle duplicate-key races as a friendly duplicate-file message.
3. Define user-facing behavior: same file bytes cannot be saved/imported again; a different workbook with the same name is not automatically a duplicate. Preserve the separate per-punch duplicate counting and skip logic.
4. Clarify cancellation semantics. If cancellation is intended to permit retry of the same bytes, document and implement this deliberately without weakening protection against an already approved/used import. Otherwise retain permanent checksum history and tell the user the file was previously submitted.
5. Improve recent-import history or error copy only if diagnosis shows the current display makes distinct files appear to be duplicates; do not delete or merge historical batches.

### Phase 4 — Request report status and Benefits filters

1. Add `HRApproved` to the Request report status dropdown, preserving the current statuses and the separate request-type selector.
2. Add `HRApproved` to the server-side whitelist/query mapping. Confirm filtering returns only HR-approved requests and that All still includes all permitted report states.
3. Add a GET filter form to Recent Contribution Records. Use validated, parameterized query conditions and preserve active filters in the result view and pagination links if pagination is included.
4. Keep approved policy summary/cards independent from contribution filtering unless policy filters are confirmed in scope. Retain lock/unlock controls and their authorization.
5. Provide a clear button, selected-filter state, and useful empty-result message. Keep the page usable at narrow viewport widths.

### Phase 5 — Review and handoff

1. Review changed files and verify schema migration assumptions against the migration history; add a migration only if a missing uniqueness/constraint/index is demonstrated.
2. Summarize implemented behavior, any unresolved decisions, and deployment-specific migration/configuration requirements.

## 5. Acceptance criteria

### Salary rate update

- A valid new effective date saves successfully and creates the new rate while closing the previous row at the prior day.
- No employee can end up with overlapping active salary periods after sequential or concurrent updates.
- Invalid dates and database constraint failures return actionable form feedback; salary history remains unchanged on failure.
- Payroll calculations for periods before the new effective date continue to use the historical rate; eligible later calculations use the new rate.

### Manual payroll adjustment

- Valid earning and deduction adjustments both save without `SQLSTATE[HY093]`.
- Totals reconcile with itemized payroll rows; audit history records actor, reason, and change.
- Invalid IDs, amounts, reasons, stale run state, and approved runs fail without partial writes.

### Attendance duplicate handling

- Re-uploading an identical workbook is rejected consistently, including near-simultaneous submissions.
- A different workbook with the same filename is handled according to the confirmed policy and is not mislabeled as a byte-identical duplicate.
- Duplicate punches within a file remain counted/skipped separately from duplicate file detection.
- Existing import batches and approved attendance are preserved.

### Request report

- `HRApproved` appears as a status option and applying it returns matching HR-approved requests.
- Other status options, date range, employee, and request-type filters continue to compose correctly.
- Invalid status values are rejected or ignored safely by server-side validation.

### Benefits filters

- Approved filter fields compose correctly and use parameterized SQL.
- Filtered records show accurate employee, program, period, share, status, and action information.
- Clear/reset restores the unfiltered recent record list; no-match results are explained.
- Policy cards and lock/unlock behavior remain correct.

## 6. Risks and safeguards

| Risk | Safeguard |
|---|---|
| Salary constraint is enforced on every intermediate write | Inspect installed constraint and perform period transition in a transaction with a constraint-compatible ordering; verify rollback on failure. |
| PDO driver treats named placeholders differently | Use unique placeholders per occurrence and test the exact configured PDO driver/emulation mode. |
| Duplicate guard is only an application pre-check | Enforce uniqueness in the database and handle unique-key races in the application. |
| Filename similarity is mistaken for a duplicate | Use content checksum as the duplicate identity; show enough filename/date/status context in import history. |
| Added filters alter result totals or hide records unexpectedly | Keep default view equivalent to current recent-record behavior and make active filters visible/resettable. |
| UI validation masks persistence defects | Keep server-side validation and database invariants; never rely on browser controls alone. |
| Production screenshot contains employee/payroll data | Reproduce with synthetic or anonymized records and avoid copying personal data into logs or documentation. |

## 7. Decisions to confirm during implementation

1. For attendance imports, should a cancelled batch permanently block re-upload of the same file bytes, or should cancellation release the checksum for retry? Approved/used imports must remain protected either way.
2. Should Benefits filters apply only to contribution records (the screenshot's lower table) or also to the Active Contribution Policies table?
3. Which filters are required for contribution records? Recommended initial set: program, status, employee search, and period/date range.

These choices do not prevent initial diagnosis or the two defect fixes. If no additional direction is provided, implement the screenshot-scoped interpretation above and preserve current cancellation behavior until its intended semantics are verified.

## 8. Relevant implementation areas

- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/ReportsController.php`
- `app/Http/Controllers/SalaryController.php`
- `app/Http/Controllers/PayrollController.php`
- `app/Application/SalaryService.php`
- `app/Application/PayrollAdjustmentService.php`
- `app/Infrastructure/Persistence/PdoAttendanceImportGateway.php`
- `resources/views/hr/attendance/import.php`
- `resources/views/hr/reports/preview.php`
- `resources/views/hr/benefits/index.php`
- Salary and attendance import migrations under `database/migrations/`

