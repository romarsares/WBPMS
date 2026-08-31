# Database Documentation Revision Record

**Revision date:** August 31, 2026

**Source reviewed:** *Web-Based Payroll Management System for Light
Diamond Enterprises*, Capstone Project Proposal, April 2026

**Affected documentation:** `database-schema.md`, `design.md`, and any
future SQL/PDO repository schema derived from them

## Purpose

This record explains corrections made after comparing the transcription in
`database-schema.md` directly with Table 85, Figures 163–164, Tables 86–97,
and the referenced functional requirements. It is intended to support a
controlled update of the capstone documentation without losing the wording
or mistakes found in the original source.

ADR-0001 authorizes the domain baseline. ADR-0002 and the v1.1 capstone
schema addendum now authorize the corrected migration contract. ADR-0003 and
the capstone implementation addendum record the frameworkless PHP and strict
XLS-parser implementation decisions. This record still does not certify
production statutory master data or rewrite the historical PDF evidence.

## Revision summary

| ID | Previous documentation statement | Revised statement | Evidence and impact |
|---|---|---|---|
| DBR-001 | The 23 Final Relation entities formed one source-of-truth schema. | The source contains four competing representations: Table 85 has 23 entities, Figure 163 has 18, Figure 164 has 17, and the Data Dictionary covers 12. | Prevents incompatible columns from being merged silently. |
| DBR-002 | The 11 entities without Data Dictionary pages had no types, keys, or constraints anywhere. | Those entities have data types in Figure 164, PK/FK markers in Figure 163, and normalization sequences in Tables 37–84. They lack complete dictionary metadata and explicit implementation constraints. | Replaces inferred types with source-backed model information. |
| DBR-003 | `employee.branch_id` was absent from the schema and had to be added as an extension. | It is absent from Table 85 and Table 86 but present as an FK in Figure 163 and as an integer field in Figure 164. | Reclassifies `branch_id` as a source-backed conflict requiring reconciliation, not a new extension. |
| DBR-004 | Table 85 names were presented as literal while obvious errors were silently normalized. | Literal labels are now retained with separate canonical notes. | Preserves traceability while still recommending usable names. |
| DBR-005 | The schema implied `province → city → barangay → address`. | Table 85/Tables 86–90 place `province_id`, `city_id`, and `barangay_id` directly on `address`; the hierarchy is not source-defined. | Any hierarchical geography FKs must be documented as an implementation decision. |
| DBR-006 | Relationships from Table 85, the Data Dictionary, and Figures 163–164 were combined into one diagram. | Relationships are now grouped by source, with conflicts called out. | Prevents incorrect FK direction and cardinality assumptions. |
| DBR-007 | The only explicitly noted gaps were branch linkage, holiday dates, and government brackets. | The audit now also records employee, payroll, request, attendance-adjustment, cash-transaction, earnings, audit-log, and branch-count conflicts. | Expands the correction scope before schema implementation. |
| DBR-008 | `users_tbl.password` was documented as a plain `varchar` column with no hashing specification. | Renamed to `password_hash` in `design.md`'s canonical `users` table. The source never specifies hashing; the rename is an implementation extension required by REQN011 (secure login). The original source spelling is preserved in §3.3 of `database-schema.md` for traceability. | Prevents a migration from creating a column named `password` that tempts plain-text storage; aligns the data model with the approved password-hashing policy. |
| DBR-009 | `MM/DD ddd` workbook headers were treated as complete dates. | Import batches now require a source year/month and validate header weekday/month before creating UTC punch instants. | Prevents ambiguous or incorrectly dated attendance. |
| DBR-010 | Attendance had no declared employee/day uniqueness or exact punch lineage. | Added unique daily grain plus `attendance_punch`. | Prevents duplicate payroll inputs and preserves every punch used as evidence. |
| DBR-011 | Payroll approval state appeared on both run and employee detail with three spellings. | `payroll_run.status` is the only authority and uses `PendingOwnerApproval`. | Prevents contradictory approval/finality state. |
| DBR-012 | Payroll lines stored only type/description/amount. | Salary/rate and line calculation inputs/source references are snapshotted. | Makes approved payroll reproducible and auditable. |
| DBR-013 | Contribution tables lacked one policy/version authority and exact deduction linkage. | Added versioned policy, unique payroll/program, and unique deduction FK. | Prevents duplicate or untraceable government deductions. |
| DBR-014 | Effective-dated tables stated non-overlap without an enforceable implementation rule. | Adopted half-open intervals, bound checks, parent-row locking, overlap queries, and concurrency tests. | Protects historical employee, device, schedule, salary, and policy resolution. |
| DBR-015 | MySQL sessions, password reset data, and unauthenticated audit events were not modeled. | Added migration-owned sessions, hashed reset challenges, account-owned email, and nullable-user security audit evidence. | Completes the selected authentication architecture and failed-login audit trail. |
| DBR-016 | Request archival/type details, leave balance, and multi-week cash-advance repayment were under-modeled. | Added type details, leave entitlement/ledger, request-linked cash-advance obligation, and repayment child rows. | Satisfies the documented request lifecycles without overwriting balances. |
| DBR-017 | The aggregate cheque was associated operationally with a branch run. | `disbursement_batch` is unique per payroll period and includes all approved branch runs. | Matches the one-cheque-per-week HR evidence. |
| DBR-018 | Payslip and current bank-account cardinalities were implied only. | Payslip is unique per payroll; bank details are effective-dated with one current active BDO account enforced by service. | Prevents duplicate payslips and ambiguous deposit preparation. |
| DBR-019 | The v1.1 schema addendum described sessions using an obsolete Express session-store reference, while the import documentation omitted parser version/lifecycle/date-context fields from its attendance-import dictionary. | ADR-0003 and the capstone implementation addendum select frameworkless PHP with a project-owned database `SessionHandlerInterface`; the canonical import-batch and schema-audit dictionaries now align on source year/month, parser version, lifecycle, and completion timestamp. | Keeps the capstone addendum stack-neutral and makes XLS import lineage reproducible. |

## Literal source corrections retained

The following text appears in Table 85 and should be corrected in the
capstone only through a documented revision:

| Original source text | Recommended canonical text | Reason |
|---|---|---|
| `Requeest_type_id` | `request_type_id` | Typographical error; Figures 163–164 use the corrected form. |
| `PAYROLL_LEARNINGS` | `PAYROLL_EARNINGS` | Typographical error; Figures 163–164 use `payroll_earnings_tbl`. |
| `Log_in` | `log_id` | Figure 163/164 uses `log_id`; `Log_in` does not describe an identifier consistently. |
| `approval_status_remarks` | `approval_status`, `remarks` | Table 97 defines these as two separate attributes. |
| CITY.`description` | CITY.`city_name` | Table 89 uses the more specific `city_name`. |
| Table 90: “Data Dictionary of Cash Advance” | “Data Dictionary of Barangay” | The table body contains `barangay_id` and `barangay_name`. |

## Schema conflicts and accepted MVP resolution

### 1. Employee

- Table 85/Table 86 contain personal and address fields such as
  `middle_initial`, `birthdate`, `id_picture`, and `address_id`.
- Figures 163–164 instead contain employment and statutory fields such as
  `branch_id`, `employee_number`, `employee_type`, `email`, `hire_date`,
  `status`, `philhealth_number`, `pagibig_number`, and `tin_number`.
- Recommended action: define one canonical employee table from verified
  requirements and HR input, then regenerate Table 85, both database-model
  figures, and the Data Dictionary from that decision.
- **ADR-0001 resolution:** accepted required/optional fields, nullable
  Owner/user linkage, effective branch assignments, and device enrollments.

### 2. Payroll, salary, earnings, and deductions

- Table 92 stores `salary_id` and one `deduction_id` on each payroll row.
- Figures 163–164 store payroll-period totals on `payroll_tbl` and use
  child `payroll_earnings_tbl` and `deductions_tbl` rows keyed by
  `payroll_id`.
- Recommended action: use a payroll header plus multiple earning and
  deduction rows, with effective-dated salary configuration kept
  separately.
- **ADR-0001 resolution:** accepted `payroll_period` → branch `payroll_run`
  → employee payroll detail, with child earnings/deductions/contributions.

### 3. Requests and attendance adjustments

- Table 85 uses a small generic request shape with `request_date`.
- Figures 163–164 use start/end dates, amount, reason, approval fields, and
  status.
- Table 85 identifies attendance adjustments by employee/date; the model
  figures identify the original attendance row and store old/new values.
- Recommended action: prefer the richer model structures because they
  support approval history and auditable corrections, but fix the apparent
  `approved_date` FK marker error before approval.
- **ADR-0001 resolution:** accepted the richer audit structures and treats
  the `approved_date` FK marker as a source error.

### 4. Cash transactions and audit logs

- Table 85 connects cash transactions and logs to `employee_id`.
- Figures 163–164 connect cash transactions to payroll/bank details and
  logs to `user_id`, with additional audit fields.
- Recommended action: confirm whether both employee and operational
  references are required rather than treating either representation as
  complete.
- **ADR-0001 resolution:** audit logs reference users; weekly disbursement
  uses one batch/cheque plus employee deposit slips instead of the historical
  cash-transaction shape.

### 5. Missing requirement-backed structures

- Add an effective-dated holiday table for REQ030.
- Add versioned SSS bracket and PhilHealth/Pag-IBIG rate tables for
  REQ061–REQ064.
- These are schema extensions because none of the database representations
  models them.
- **ADR-0001 resolution:** both entity families are accepted for migrations;
  only the documented contribution example is approved for the MVP fixture.

## MVP development approval checklist

- [x] Record the canonical authority and source-precedence rule in ADR-0001.
- [x] Resolve the branch-count conflict through configurable branch/site/device
  master data and a three-branch demo topology.
- [x] Approve employee required/optional fields, identity, archive behavior,
  branch history, and biometric enrollment.
- [x] Approve payroll period/run/detail cardinalities and uniqueness.
- [x] Approve salary authority and history behavior.
- [x] Approve payroll statuses, audit fields, return reasons, and immutability.
- [x] Approve the real `.xls` workbook contract from the sanitized samples.
- [x] Approve MVP time, money, payroll-cycle, holiday, overtime, missing-punch,
  and rounding behavior.
- [x] Approve a traceable demo contribution fixture with an explicit
  non-production disclaimer.
- [x] Select the technical stack and API/error contracts.
- [x] Approve ADR-0002's typed canonical dictionary, attendance lineage,
  payroll snapshot, lifecycle, temporal, authentication, and reconciliation
  constraints.

This checklist authorizes Tasks 1.1 and 1.2. Official master data, complete
statutory policies, and HR production acceptance remain deployment gates.

## Capstone update checklist

- [x] Select ADR-0001, ADR-0002, `docs/design.md`, and the v1.1 capstone
  addendum as the canonical schema sources.
- [x] Resolve branch counts as configurable data; flag official display names
  for production confirmation.
- [x] Approve the canonical employee attribute set and required/optional fields.
- [x] Approve payroll header/detail cardinalities.
- [x] Confirm request and attendance-adjustment audit requirements.
- [x] Add holiday and government-rate entities to the canonical model.
- [x] Correct the literal Table 85 and Table 90 errors in canonical naming.
- [ ] Regenerate Table 85 from the approved schema.
- [ ] Regenerate Figures 163 and 164 from the same schema definition.
- [x] Create a typed canonical Data Dictionary for every approved entity in
  `capstone_files/Canonical-Database-Schema-v1.1.md`.
- [ ] Verify regenerated logical/physical FKs after migrations exist.
- [x] Update `design.md` after recording canonical decisions in ADR-0001.
- [x] Update `design.md`, requirements, implementation tasks, and schema audit
  after recording integrity corrections in ADR-0002.
- [x] **`benefits_tbl` canonical exclusion** — Resolved. The supplemental
  DFD (Final Defense Reviewer PDF) routes Government Contributions into
  the D4 Deductions store and contains no Benefits store. `benefits_tbl`
  is retained as a source-literal artifact in `database-schema.md §2.10`
  but is excluded from the canonical model and will not be migrated.
  Any future non-government bonus/benefit feature requires separate
  approved requirements before a table is added. *(Closed 2026-08-30)*
- [x] **`users.password` → `password_hash`** — Resolved as DBR-008.
  The column is renamed in `design.md`'s canonical `users` table.
  Source spelling preserved in `database-schema.md §3.3` for traceability.
  *(Closed 2026-08-30)*

## Documentation update rule

Future edits should label each schema element as one of:

1. **Source-literal** — copied exactly from the capstone, including a noted
   inconsistency when applicable.
2. **Canonical correction** — a reviewed correction to conflicting or
   misspelled source material.
3. **Implementation extension** — required by a feature but absent from the
   original database documentation.

This classification keeps the historical source auditable while allowing
the implementation documentation to become internally consistent.
