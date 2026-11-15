# Final Defense Panel Revisions — Action Plan

**Project:** Web-Based Payroll Management System (WBPMS), Light Diamond Enterprises  
**Source:** `GROUP 2 FINAL DEFENSE - REVISIONS.xlsx`, worksheet **Group 2**  
**Prepared:** 2026-11-11  
**Purpose:** Turn the panel's notes into an implementable, verifiable thesis/capstone revision plan. This document plans the revisions; it does not claim that they have been implemented.

## 1. Panel suggestions captured

| Area | Panel note (workbook cell) | Planned outcome |
|---|---|---|
| Login / recovery (A1, A3) | Rename “Login and Authentication Module” to “Login and Recovery Module”; add email verification to confirm the address exists and belongs to the user. | Update thesis/module labels and implement an email ownership verification step in the relevant account or recovery flow, with clear success and failure behavior. |
| Employee add (A6–A13) | Accept names with leading spaces; set daily rate from selected position and prevent below-rate saves; validate positions allowed for contractual employees; display available employee number; check birthdate and effective-from dates. | Normalize names, make position/rate and employment type rules consistent, preview a safe employee number, and validate dates before persistence. |
| Attendance import (A15, A18) | Imported attendance is not reflected. | Trace upload through parsing, review/confirmation, persistence, and the attendance/timesheet screens; ensure a successful import is visible and its result is reported. |
| Database (A20, A22) | “Unnecessary in the database.” | Identify the exact table, field, or data item the panel means, then assess whether it is redundant or required by payroll, attendance, history, audit, or referential integrity. Do not remove anything until that item and its dependencies are established. |

## 2. Implementation plan

### Phase 1 — Confirm the panel notes against the current system

1. Reproduce the reported behavior in the current build and record the screen, inputs, expected result, actual result, and affected role.
2. Trace each report to its form, controller/service, persistence path, and existing requirements. The repository requirements already describe OTP based password recovery, effective-dated salary rates, and a reviewed attendance-import workflow; check the defense revision against those contracts instead of creating conflicting behavior.
3. Ask the panel/adviser or consult the defense recording/notes to name the database item meant by “Unnecessary in the database.” Record the answer and the affected schema object before deciding on any schema change.
4. Confirm the approved position-to-rate schedule, contractual position eligibility, employee-number format, and the exact meaning/source of “effective from” with the project adviser/HR. These are business rules and must not be guessed from code.

**Deliverable:** A concise issue-to-screen/code/requirement trace and a resolved list of business-rule questions, especially the database item.

### Phase 2 — Login and recovery wording and email ownership

1. Use **Login and Recovery Module** consistently in the thesis, diagrams, screenshots, user guide, and interface where the old module name appears.
2. Map the panel's “verify if it is existing, its his or her email” to an explicit ownership check: send a time-limited, single-use verification code/link to the submitted email and require successful completion before treating the address as verified for the intended flow.
3. Keep account-enumeration behavior in mind: an unregistered address should not reveal another user's account status on public recovery screens. Show an actionable message after the verification attempt without exposing account data.
4. Expire and invalidate the verification challenge after use, timeout, or replacement; throttle repeated sends/attempts; never store a plaintext verification secret.
5. Update the use case/sequence diagram, requirements, and test scenarios to match whether verification applies to account creation, email changes, password recovery, or all three. Confirm this scope from the panel/adviser before implementation if the workbook alone does not settle it.

**Acceptance checks:** Correct code verifies the submitted address; incorrect/expired/reused code does not; resend invalidates or supersedes the prior challenge; recovery does not disclose account existence; verified email is associated with the intended account/action.

### Phase 3 — Employee add validation and guided fields

1. Trim leading/trailing whitespace from first, middle, and last name inputs before validation and saving. Preserve internal spaces and reject values that become empty after trimming.
2. Make position selection the source for an approved daily-rate value. Display the rate immediately, submit a trusted position/rate identifier, and resolve the amount server-side; do not trust a client-edited amount. Preserve the existing effective-dated salary history so changes apply from the approved effective date and prior payroll remains auditable.
3. Enforce the approved minimum rate on the server and in the database where feasible. Reject missing, invalid, or below-minimum rates with a field-level message; do not silently save a disallowed amount.
4. Apply the adviser/HR-approved position eligibility rule to Contractual employees and validate it server-side. Keep the selection and error message clear in the form.
5. Show the next available employee number as a preview when opening the add form. Treat it as provisional: recheck uniqueness atomically at save time and provide a recoverable message if another user obtains that number first. Never promise availability based only on a stale preview.
6. Validate birthdate as a real date, not in the future, and consistent with the approved minimum-age rule (confirm the rule); validate effective-from against its actual business meaning, valid date format, and relevant hire/salary/assignment chronology. Do not conflate employee hire date, salary effective date, branch assignment date, or schedule effective date.
7. Present all validation errors before writing any employee, salary, branch, schedule, or biometric-enrollment records; maintain transaction rollback if any linked record fails.

**Acceptance checks:** Names with surrounding spaces save in normalized form; whitespace-only names are rejected; rate follows the selected approved position and cannot be lowered through a modified request; invalid contractual-position combinations fail; previewed numbers are unique under concurrent submissions; invalid/impossible dates fail with clear messages; valid records and linked effective dates persist together.

### Phase 4 — Attendance import visibility and reconciliation

1. Reproduce using a representative approved biometric workbook and capture its checksum, source device, selected period, and expected employees/days.
2. Follow the import through upload validation, parsing, duplicate checks, employee matching, import-batch status, review/confirmation, raw punches, attendance generation, and the screens where HR expects to see the result.
3. Locate the point where the data ceases to appear. Fix the underlying flow so successful imports transition to the state required by the approved workflow and become visible in the correct attendance/timesheet views, including any required HR confirmation step.
4. Make the result page state the batch status and show a useful summary: parsed, matched, unmatched, duplicate, incomplete, and multi-punch records. Provide a direct path to review unresolved items and the generated timesheets.
5. Preserve atomicity and idempotency: invalid files must not partially import; re-uploading the same file/punches must not create duplicate attendance; unmatched punches must remain reviewable rather than disappear.
6. Update the user manual and thesis workflow/DFD/sequence diagram if the visible flow changes.

**Acceptance checks:** A valid sample import produces an inspectable batch, raw-punch evidence, and expected attendance/timesheets; HR can find results using employee and period filters; unmatched/incomplete records are visible and flagged; repeated imports do not duplicate records; invalid imports leave no partial data.

### Phase 5 — Database note and schema decision

1. Resolve the panel's unspecified “unnecessary” target. Inventory the named table/column/data and its reads/writes, foreign keys, reports, payroll calculations, historical use, and audit purpose.
2. Document the decision in a short data-dictionary/architecture note: retain with rationale, deprecate through a migration, or remove only if it is genuinely unused and the panel/adviser accepts the change.
3. If removal is approved, prepare a reversible migration where practical, preserve any required historical/audit information, update models, seeders, reports, diagrams, and documentation, and assess existing installations before release.
4. Do not treat normalized or historical data as “unnecessary” solely because it is not shown on the main screen; check whether it supports salary effective dates, attendance traceability, payroll snapshots, or auditability.

**Acceptance checks:** The exact target and rationale are recorded; no live feature or historical record loses required data; migration behavior is reviewed against a representative existing database; documentation and diagrams match the final schema.

### Phase 6 — Thesis/capstone evidence and sign-off

1. Update requirements and traceability matrix with panel-derived acceptance criteria and link each implemented item to its evidence.
2. Revise relevant context diagram/DFD, use-case, activity/sequence diagrams, data dictionary/ERD, and screenshots only where behavior or schema actually changes.
3. Prepare a revision matrix with columns: panel comment, action taken, artifact/location, verification evidence, and adviser/panel status.
4. Capture before/after screenshots or a short demonstration for each user-facing fix, using synthetic data. Include import summary and employee validation examples.
5. Mark each item complete only after the acceptance checks pass and the adviser/panel has reviewed any unresolved business rule.

## 3. Suggested order and dependencies

1. Reproduce all issues and resolve business rules (Phase 1).
2. Fix employee validation and attendance visibility (Phases 3–4), since both have concrete reported failures and can be checked against the existing workflow.
3. Confirm the scope of email verification, then implement and revise the module label and recovery documentation (Phase 2).
4. Resolve the database target before any schema decision (Phase 5).
5. Complete thesis artifacts, evidence, and sign-off (Phase 6).

## 4. Open decisions to carry into adviser/panel review

- Which user action must verify email ownership: account creation, changing an email, password recovery, or more than one of these?
- What is the approved position-to-daily-rate mapping, and is there one minimum legal/company rate or a minimum per position?
- Which positions may be assigned to Contractual employees?
- What employee-number pattern should be previewed, and should gaps from deleted/abandoned records ever be reused? (Recommended default: never reuse persisted identifiers.)
- Does “effective from” refer to hire date, daily-rate effective date, or another assignment date in the form?
- What exact database table, field, or stored value did the panel identify as unnecessary?

## 5. Scope and safety notes

- This plan is based on the workbook's single **Group 2** sheet and the project documentation/source inspected on 2026-11-11.
- The database suggestion is underspecified. Schema deletion is explicitly deferred until the target and its dependencies are confirmed.
- Employee-rate and contractual eligibility behavior depends on approved HR/adviser rules; the implementation must not invent those values.
- The implementation and verification work remains to be performed; this plan records proposed actions and acceptance checks only.
