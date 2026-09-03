# Employee Lifecycle, Archive, Rehire, and Document Specification

**Status:** Proposed implementation specification — not yet implemented  
**Owner:** HR Head (with Business Owner approval where noted)  
**Traceability:** REQ009–REQ017, especially REQ012; REQ001, REQ007–REQ008,
REQ018–REQ024, REQ047–REQ051, REQN007, REQN011; ADR-0002

## 1. Purpose and scope

This specification completes REQ012. An employee archive is a controlled
employment-lifecycle operation, not a deletion and not a bulk edit of every
row that references the employee. It must stop future operational use while
preserving the evidence needed for payroll, attendance, reporting, disputes,
and audit.

It covers employee separation/archive, rehire, linked operational records,
and employee-scoped scanned documents/attachments. It does not introduce an
automatic termination, automatic disciplinary action, or a legal retention
period; those require company policy approval.

## 2. Lifecycle and authority

| State | Meaning | Who may set it | Payroll / login effect |
|---|---|---|---|
| `Active` | Currently employed and eligible for normal HR processing. | HR Head | Eligible, subject to normal period rules; linked account may log in only if Active. |
| `Inactive` | Temporary non-working status such as approved long leave or suspension. | HR Head | Excluded from newly computed payroll; linked account is inactive. Historical records remain visible to authorized users. |
| `Separated` | Employment has ended, but exit checks are still open. | HR Head | No new operational activity after the effective date; cannot be archived until blockers are resolved or an authorized exception is recorded. |
| `Archived` | Closed employment record retained for history. | HR Head | Excluded from active lists, payroll, new schedules, biometric matching, and new requests. It may be rehired only through the controlled rehire workflow. |

The UI shall collect a **last working date**, archive reason, and HR note. The
archive effective date is the following calendar day. Effective-dated records
use the existing half-open rule `[effective_from, effective_to)`, so setting
`effective_to` to the archive effective date keeps the employee's final
working day historically valid.

Every lifecycle change must create an immutable `employee_lifecycle_event`
row with employee, event type, effective date, reason, note, acting user,
timestamp, and the prior/new state. Direct status editing must not bypass this
workflow.

## 3. Archive workflow

### Preconditions

1. The HR Head selects an employee and records the last working date, reason,
   and note.
2. The service previews all records that will be closed and all blockers.
3. The service rejects the operation if the employee belongs to a
   `Draft`, `Computed`, or `PendingOwnerApproval` payroll run, has an open
   cash-advance balance, or has a pending request. HR must resolve the item,
   recompute the unapproved payroll run, or use an explicitly recorded
   Business Owner exception. Approved payroll is never changed.
4. The action is one database transaction. On failure, no status, account,
   relationship, or audit event is partially changed.

### Transactional effects

On success, the service shall:

1. Set the employee to `Archived` at the archive effective date and append a
   lifecycle event.
2. Close the current employee branch assignment, biometric enrollment,
   employee schedule assignment, active salary rate, and active bank-details
   period on that date. These records remain queryable as history and are not
   deleted.
3. Set a linked Active user account to `Inactive` and invalidate its active
   sessions. The account is deliberately **not** set to `Archived`, because
   rehire may reuse it. If it was separately archived, the preview must flag
   it for Business Owner recovery during rehire.
4. Preserve attendance, raw biometric punches, attendance adjustments,
   approved/rejected/cancelled requests, leave-ledger entries, contribution
   records, payroll rows, payslips, deposit slips, audit logs, and documents
   exactly as they are. Their employee foreign key never changes.
5. Prevent new requests, schedule assignments, biometric enrollment, salary
   rates, bank details, and payroll membership for the archived employee.

The archive action must **not** automatically archive every request, erase
documents, zero an advance, delete a user, or modify an approved payroll.
Those behaviours would destroy evidence or create an incorrect financial
state.

## 4. Rehire / unarchive workflow

A rehire is a new employment episode for the same person, not a new employee
record. The existing `employee_id`, employee number, historic attendance,
payroll, requests, and documents remain intact.

The HR Head starts **Rehire employee** from an archived record and supplies:

- rehire effective date, branch, position, employment type, daily rate,
  schedule, and biometric enrollment (if applicable);
- confirmation or update of contact, government, bank, and document details;
- a rehire reason/note; and
- an account decision: keep login disabled or request Business Owner approval
  to reactivate the existing linked account.

The service shall append a new `employee_employment_episode` row rather than
overwrite the prior employment dates. It creates new effective-dated branch,
schedule, biometric, salary, and bank-detail records from the rehire date;
previous records remain closed. It validates that no newly opened period
overlaps history. The employee is then `Active`.

Account reactivation is separate from employee reactivation. An `Inactive`
linked account may be activated after HR confirmation. A separately
`Archived` account requires a Business Owner-authorized, audited recovery;
the system must not create a second account for the same `employee_id`.

The rehire date must not fall inside a closed/approved payroll outcome in a
way that would alter it. New payroll eligibility begins only at the rehire
date according to the normal period-membership rule.

## 5. Data model additions and constraints

| Object | Required additions / rule |
|---|---|
| `employee` | Retain the existing stable key and status. Lifecycle status changes occur only through the lifecycle service. |
| `employee_employment_episode` | `episode_id`, `employee_id`, `started_on`, `ended_on`, `start_reason` (`Hire`/`Rehire`), `end_reason`, `created_by`, `created_at`, and notes. One open episode per employee; no overlapping dates. |
| `employee_lifecycle_event` | Append-only lifecycle/audit evidence: prior/new status, event type, effective date, reason, note, acting user, and timestamp. |
| Effective-dated tables | Close, never rewrite: branch assignment, biometric enrollment, schedule assignment, salary, and bank details. A service-level lock and overlap check protect concurrent actions. |
| Financial/history tables | Attendance, payroll, payslips, contributions, request decisions, ledgers, and audit logs are immutable historical evidence and are never cascaded to a new employee ID. |
| `employee_document` | See §6. Documents are independent metadata rows; their files are private and are not database BLOBs. |

Foreign-key `ON DELETE` behaviour must remain restrictive for the employee.
No employee archive or rehire operation performs a hard delete.

## 6. Employee documents and scanned attachments

### Current-system finding

The current implementation has only `employee.id_picture`, a nullable path
under `storage/private/`. It has no employee-document table, upload endpoint,
attachment view, file validation, or document-retention workflow. The only
implemented uploads are biometric attendance workbooks. Therefore the system
does **not currently handle or save scanned employee documents/attachments**.

### Proposed document capability

Add `employee_document` with at least:

`document_id`, `employee_id`, `document_type`, `document_number_masked`,
`original_filename`, `storage_key`, `mime_type`, `byte_size`, `sha256`,
`issued_on`, `expires_on`, `status` (`Current`, `Expired`, `Superseded`,
`Archived`), `replaces_document_id`, `uploaded_by`, `uploaded_at`,
`verified_by`, `verified_at`, `archived_at`, and `notes`.

Documents include, only where the company requires them: ID photo, signed
employment contract, government-ID evidence, tax/government forms, bank proof,
and separation/rehire documents. Sensitive identifiers must be masked in list
views and logs.

Files shall be stored outside `public/` using a random server-generated key,
not the client filename. The upload boundary shall allow only approved PDF,
JPEG, and PNG content after extension, MIME/content-signature, size, and
checksum validation; it shall record a malware-scan result before a document
is downloadable. HR-only authorization and audit logging apply to upload,
view/download, replace, verify, and archive actions. The employee portal must
not expose HR documents unless a separate requirement explicitly permits it.

On employee archive, document files and metadata are retained, access remains
HR-only, and each document is marked as associated with an archived employee.
On rehire, documents are retained but shown for review. Expired or changed
documents require a replacement upload; the old document becomes
`Superseded`, retains its hash and provenance, and is never silently replaced
or deleted.

## 7. Acceptance criteria

1. WHEN HR archives an eligible employee, THEN one transaction closes future
   operational relationships, disables the linked active account, appends a
   lifecycle event, and retains all historical evidence.
2. IF an unresolved pending request, open advance, or mutable payroll run
   exists, THEN the archive operation SHALL explain the blocker and make no
   changes.
3. WHEN payroll is computed for a period after archive effective date, THEN
   the employee SHALL not be eligible; an approved prior payroll SHALL remain
   unchanged and viewable.
4. WHEN historical attendance or a payslip is opened after archive, THEN it
   SHALL still resolve to the same employee and historical effective records.
5. WHEN HR rehires an archived employee, THEN the system SHALL reuse the
   employee identity, append a new employment episode, require new current
   employment setup, and not reactivate login without the required approval.
6. WHEN HR replaces a scanned document, THEN the replacement SHALL create a
   new document record and preserve the prior file's metadata and audit trail.
7. IF an upload fails type, size, signature, authorization, or malware-scan
   validation, THEN no document metadata or file SHALL become available.

## 8. Implementation sequence

**Implementation note (2026-09-03):** The lifecycle schema, transaction
service, confirmation screens, and generic-status-edit guard are implemented.
The employee-document and scanned-attachment capability in section 6 remains
the next phase.

Implement the lifecycle schema and service before exposing archive/re-hire
buttons. Then add the document capability. Do not retrofit this by adding
status updates inside the existing generic employee edit action; that would
miss blockers, session invalidation, effective dating, and audit evidence.

The exact engineering checklist is in `tasks.md` under task 4.6.
