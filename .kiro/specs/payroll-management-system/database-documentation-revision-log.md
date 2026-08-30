# Database Documentation Revision Record

**Revision date:** August 29, 2026

**Source reviewed:** *Web-Based Payroll Management System for Light
Diamond Enterprises*, Capstone Project Proposal, April 2026

**Affected documentation:** `database-schema.md`, `design.md`, and any
future SQL/ORM schema derived from them

## Purpose

This record explains corrections made after comparing the transcription in
`database-schema.md` directly with Table 85, Figures 163–164, Tables 86–97,
and the referenced functional requirements. It is intended to support a
controlled update of the capstone documentation without losing the wording
or mistakes found in the original source.

No production database migration is authorized by this record. It documents
evidence, corrections, and decisions that still require approval.

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

## Schema conflicts requiring an approved decision

### 1. Employee

- Table 85/Table 86 contain personal and address fields such as
  `middle_initial`, `birthdate`, `id_picture`, and `address_id`.
- Figures 163–164 instead contain employment and statutory fields such as
  `branch_id`, `employee_number`, `employee_type`, `email`, `hire_date`,
  `status`, `philhealth_number`, `pagibig_number`, and `tin_number`.
- Recommended action: define one canonical employee table from verified
  requirements and HR input, then regenerate Table 85, both database-model
  figures, and the Data Dictionary from that decision.

### 2. Payroll, salary, earnings, and deductions

- Table 92 stores `salary_id` and one `deduction_id` on each payroll row.
- Figures 163–164 store payroll-period totals on `payroll_tbl` and use
  child `payroll_earnings_tbl` and `deductions_tbl` rows keyed by
  `payroll_id`.
- Recommended action: use a payroll header plus multiple earning and
  deduction rows, with effective-dated salary configuration kept
  separately.

### 3. Requests and attendance adjustments

- Table 85 uses a small generic request shape with `request_date`.
- Figures 163–164 use start/end dates, amount, reason, approval fields, and
  status.
- Table 85 identifies attendance adjustments by employee/date; the model
  figures identify the original attendance row and store old/new values.
- Recommended action: prefer the richer model structures because they
  support approval history and auditable corrections, but fix the apparent
  `approved_date` FK marker error before approval.

### 4. Cash transactions and audit logs

- Table 85 connects cash transactions and logs to `employee_id`.
- Figures 163–164 connect cash transactions to payroll/bank details and
  logs to `user_id`, with additional audit fields.
- Recommended action: confirm whether both employee and operational
  references are required rather than treating either representation as
  complete.

### 5. Missing requirement-backed structures

- Add an effective-dated holiday table for REQ030.
- Add versioned SSS bracket and PhilHealth/Pag-IBIG rate tables for
  REQ061–REQ064.
- These are schema extensions because none of the database representations
  models them.

## Capstone update checklist

- [ ] Select and approve a canonical schema owner (project team plus HR
  subject-matter owner).
- [ ] Confirm whether the organization has two or three active branches.
- [ ] Approve the canonical employee attribute set and required/optional
  fields.
- [ ] Approve payroll header/detail cardinalities.
- [ ] Confirm request and attendance-adjustment audit requirements.
- [ ] Add holiday and government-rate entities.
- [ ] Correct the literal Table 85 and Table 90 errors listed above.
- [ ] Regenerate Table 85 from the approved schema.
- [ ] Regenerate Figures 163 and 164 from the same schema definition.
- [ ] Create full Data Dictionary entries for every approved entity.
- [ ] Verify that every FK shown in the logical model exists in the physical
  model and names the correct parent table.
- [ ] Update `design.md`, SQL migrations, ORM models, and tests only after
  the canonical decisions are recorded.

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
