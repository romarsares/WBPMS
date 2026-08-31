# Database Schema — Source Audit and Reconciliation Reference

**Source:** *Web-Based Payroll Management System for Light Diamond
Enterprises* — Capstone Project Proposal, STI College Tacurong (April 2026).
Transcribed from: Conceptual Database Model (Fig. 162), Database
Normalization (UNF→3NF), Final Relation (Table 85), Logical/Physical
Database Models (Figs. 163–164), and the Data Dictionary (Tables 86–97).

This document records the database **as it appears in the original
documentation**. The capstone does not contain one internally consistent
schema: Table 85, the logical model, the physical model, and the data
dictionary disagree on table and column coverage. Consequently, this file
is an audit and reconciliation reference, not a directly buildable schema.

Source spellings and mistakes are preserved where they matter. A canonical
name is shown separately when a source label is clearly misspelled. Tables
or fields introduced during later design work are kept in Section 7 as
explicit schema extensions.

> **Implementation update (ADR-0002):** the authoritative migration-level
> dictionary is
> [`Canonical-Database-Schema-v1.1.md`](../../../docs/capstone_files/Canonical-Database-Schema-v1.1.md).
> The historical dictionaries below remain source evidence; where they differ,
> ADR-0002 and the v1.1 addendum take precedence.

### Source coverage summary

| Source section | Coverage | Detail provided |
|---|---:|---|
| Final Relation (Table 85, pp. 360–361) | 23 entities | Attribute names only |
| Logical Database Model (Fig. 163, pp. 362–363) | 18 tables | Attributes, PK/FK markers, relationships |
| Physical Database Model (Fig. 164, pp. 363–364) | 17 tables | Attributes and data types |
| Data Dictionary (Tables 86–97, pp. 364–377) | 12 tables | Types, PK, defaults, nullability, lengths, examples, validation rules |

No one source section contains all 23 entities with complete implementation
detail.

See the [Database Documentation Revision Record](database-documentation-revision-log.md)
for the correction history and the checklist for updating the capstone,
design, and future implementation artifacts.

---

## 1. Entity list (Final Relation — Table 85)

The document's Final Relation lists **23 entities**. Source spelling and
casing are preserved below. The canonical note records obvious corrections
without silently changing the transcription.

| # | Source table label | Attributes exactly as listed in Table 85 | Canonical note |
|---|---|---|---|
| 1 | EMPLOYEE | employee_id, first_name, middle_initial, last_name, birthdate, contact_number, id_picture, address_id | — |
| 2 | ADDRESS | address_id, street, barangay_id, city_id, province_id | — |
| 3 | PROVINCE | province_id, province_name | — |
| 4 | CITY | city_id, description | Table 89 uses `city_name` |
| 5 | BARANGAY | Barangay_id, barangay_name | Normalize casing to `barangay_id` |
| 6 | ATTENDANCE | attendance_id, employee_id, date, time_in, time_out | — |
| 7 | PAYROLL | Payroll_id, employee_id, salary_id, deduction_id | Normalize casing to `payroll_id` |
| 8 | SALARY | Salary_id, basic_salary | Normalize casing to `salary_id` |
| 9 | DEDUCTIONS | Deduction_id, deduction_type, amount | Normalize casing to `deduction_id` |
| 10 | BENEFITS | Benefit_id, benefit_type, amount | Normalize casing to `benefit_id` |
| 11 | PAYSLIP | Payslip_id, employee_id, payroll_id, net_pay | Normalize casing to `payslip_id` |
| 12 | BRANCH | Branch_id, branch_name, location | Normalize casing to `branch_id` |
| 13 | Role | Role_id, role_name | Normalize table and ID casing |
| 14 | USERS | User_id, employee_id, username, password, role_id | Normalize casing to `user_id` |
| 15 | WORKSCHEDULE | Schedule_id, employee_id, working_days, rest_days, work_start_time, work_end_time | Normalize casing to `schedule_id` |
| 16 | REQUEST_TYPE | Requeest_type_id, type_name | Correct typo to `request_type_id` |
| 17 | REQUESTS | Request_id, employee_id, request_type_id, request_date, status | Normalize casing to `request_id` |
| 18 | ATTENDANCE_ADJUSTMENT | Adjustment_id, employee_id, attendance_date, adjusted_time_in, adjusted_time_out, reason | Normalize casing to `adjustment_id` |
| 19 | BANK_DETAILS | Bank_id, employee_id, bank_name, account_number | Normalize casing to `bank_id` |
| 20 | CASH_TRANSACTION | Transaction_id, employee_id, transaction_type, amount, transaction_date | Normalize casing to `transaction_id` |
| 21 | PAYROLL_LEARNINGS | earning_id, employee_id, earning_type, amount | Correct table name to `PAYROLL_EARNINGS` |
| 22 | AUDIT_LOGS | Log_in, employee_id, action, log_date | `Log_in` is likely intended to be `log_id` |
| 23 | CASH_ADVANCE_HISTORY | History_id, employee_id, amount, request_date, approval_status_remarks | Table 97 separates `approval_status` and `remarks` |

Of these 23, only the **first 11 plus CASH_ADVANCE_HISTORY (12 total)**
receive complete column-level entries in the Data Dictionary (Tables
86–97). The other 11 do not have data-dictionary entries, but they are not
wholly unspecified: their types appear in Figure 164, their PK/FK roles
appear in Figure 163, and their normalization sequences appear in Tables
37–84. What remains missing for them is complete dictionary metadata such
as defaults, nullability, lengths, validation rules, and explicit database
constraints.

---

## 2. Fully specified tables (from the Data Dictionary, Tables 86–97)

### 2.1 `employee_tbl` — Table 86

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| employee_id | Employee identification | Int | None | ✅ | 01 | N | 1 | `[0-9]` |
| first_name | Employee first name | Varchar(50) | None | | Jiane Arielle | N | 50 | `[A-Za-z ]` |
| middle_initial | Employee middle initial | Varchar(5) | None | | B. | Y | 5 | `[A-Za-z.]` |
| last_name | Employee last name | Varchar(50) | None | | Gamboa | N | 50 | `[A-Za-z]` |
| birthdate | Employee birthdate | Date | None | | 04/07/2026 | N | — | `[MM-DD-YYYY]` |
| contact_number | Employee contact number | Nchar(11) | None | | 09489007196 | N | 11 | `[0-9]` |
| id_picture | Employee ID picture | Varchar(50) | None | | picture.png | Y | 50 | `[A-Za-z./-]` |
| address_id | Address identification (FK → address_tbl) | Int | None | | 1 | N | 1 | `[0-9]` |

> **Cross-source conflict:** Table 85 and Table 86 omit `branch_id`, while
> Figures 163 and 164 include `employee_tbl.branch_id` and Figure 163 marks
> it as a foreign key. The diagrams also add `employee_number`,
> `employee_type`, `email`, `hire_date`, `status`, `philhealth_number`,
> `pagibig_number`, and `tin_number`, while omitting several Table 85/Table
> 86 fields. This is a broader employee-schema divergence, not merely a
> missing branch field.

### 2.2 `address_tbl` — Table 87

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| address_id | Address identification | Int | None | ✅ | 1 | N | 1 | `[0-9]` |
| street | Street or Purok | Varchar(50) | None | | Purok Katilingban | N | 50 | `[A-Za-z0-9 ]` |
| province_id | Province identification (FK) | Int | None | | 1 | N | 1 | `[0-9]` |
| city_id | City identification (FK) | Int | None | | 1 | N | 1 | `[0-9]` |
| barangay_id | Barangay identification (FK) | Int | None | | 1 | N | 1 | `[0-9]` |

### 2.3 `province_tbl` — Table 88

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| province_id | Province identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| province_name | Province name | Varchar(50) | None | | Sultan Kudarat | N | 50 | `[A-Za-z]` |

### 2.4 `city_tbl` — Table 89

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| city_id | City identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| city_name | City name | Varchar(50) | None | | Tacurong City | N | 50 | `[A-Za-z]` |

> Note: Table 85 lists this column as `description`, while Table 89 names it
> `city_name`. They appear to represent the same value, but the original
> documentation does not formally resolve the conflict. `city_name` is the
> recommended canonical implementation name.

### 2.5 `barangay_tbl`

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| barangay_id | Barangay identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| barangay_name | Barangay name | Varchar(50) | None | | San Pablo | N | 50 | `[A-Za-z]` |

> Note: In the source document this table is mislabeled "**Table 90. Data
> Dictionary of Cash Advance**" — the heading is a copy-paste error; the
> content is unambiguously the barangay table (attributes `barangay_id`,
> `barangay_name`). Corrected here for accuracy.

### 2.6 `attendance_tbl` — Table 91

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| attendance_id | Attendance identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| employee_id | Employee identification (FK) | Int | None | | 1 | N | 11 | `[0-9]` |
| date | Attendance date | Date | None | | 04/01/2026 | N | — | `[MM-DD-YYYY]` |
| time_in | Time in | Varchar(10) | None | | 8:00 AM | N | 10 | `[Time]` |
| time_out | Time out | Varchar(10) | None | | 5:00 PM | N | 10 | `[Time]` |

### 2.7 `payroll_tbl` — Table 92

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| payroll_id | Payroll identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| employee_id | Employee identification (FK) | Int | None | | 1 | N | 11 | `[0-9]` |
| salary_id | Salary identification (FK) | Int | None | | 1 | N | 11 | `[0-9]` |
| deduction_id | Deduction identification (FK) | Int | None | | 1 | N | 11 | `[0-9]` |

### 2.8 `salary_tbl` — Table 93

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| salary_id | Salary identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| basic_salary | Basic salary amount | Float | None | | 20000 | N | — | `[0-9]` |

### 2.9 `deductions_tbl` — Table 94

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| deduction_id | Deduction identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| deduction_type | Type of deduction | Varchar(50) | None | | Tax | N | 50 | `[A-Za-z]` |
| amount | Deduction amount | Float | None | | 1000 | N | — | `[0-9.]` |

### 2.10 `benefits_tbl` — Table 95

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| benefit_id | Benefit identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| benefit_type | Type of benefit | Varchar(50) | None | | Bonus | N | 50 | `[A-Za-z]` |
| amount | Benefits amount | Float | None | | 3000 | N | — | `[0-9]` |

### 2.11 `payslip_tbl` — Table 96

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| payslip_id | Payslip identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| employee_id | Employee identification (FK) | Int | None | | 1 | N | 11 | `[0-9]` |
| payroll_id | Payroll identification (FK) | Int | None | | 1 | N | 11 | `[0-9]` |
| net_pay | Net salary amount | Float | None | | 18000 | N | — | `[0-9.]` |

### 2.12 `cash_advance_history_tbl` — Table 97

| Attribute | Description | Data Type | Default | PK | Example | Null? | Length | Validation |
|---|---|---|---|---|---|---|---|---|
| history_id | Cash advance history identification | Int | None | ✅ | 1 | N | 11 | `[0-9]` |
| employee_id | Employee identification (FK) | Int | None | | 1 | N | 11 | `[0-9]` |
| amount | Cash advance amount | Float | None | | 2000 | N | — | `[0-9.]` |
| request_date | Date of request | Date | None | | 04/02/2026 | N | — | `[MM-DD-YYYY]` |
| approval_status | Status of approval | Varchar(20) | None | | Approved | N | 20 | `[A-Za-z]` |
| remarks | Additional remarks | Varchar(100) | None | | Fully Paid | Y | 100 | `[A-Za-z0-9]` |

---

## 3. Tables without Data Dictionary entries

The following 11 tables lack full Data Dictionary entries. Unlike the
earlier version of this file, the definitions below are **not inferred**:
data types come from the Physical Database Model (Figure 164), while key
roles come from the Logical Database Model (Figure 163). Figure 163's
`UQ` marker is recorded as unique. Nullability, default values, lengths,
validation rules, and referential actions remain unspecified.

### 3.1 `branch_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| branch_id | integer | PK |
| branch_name | varchar | — |
| location | varchar | — |

This agrees with Table 85 apart from casing and the `_tbl` suffix.

### 3.2 `role_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| role_id | integer | PK |
| role_name | varchar | — |

This agrees with Table 85 apart from casing and the `_tbl` suffix.

### 3.3 `users_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| user_id | integer | PK |
| employee_id | integer | FK, UQ |
| role_id | integer | FK |
| username | varchar | — |
| password | varchar | — |
| status | varchar | — |

Table 85 omits `status`. Password hashing and credential constraints are
not specified in the source.

### 3.4 `workschedule_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| schedule_id | integer | PK |
| employee_id | integer | FK |
| working_days | varchar | — |
| rest_days | varchar | — |
| break_duration | time | — |
| work_start_time | time | — |
| work_end_time | time | — |
| effective_start_date | date | — |
| effective_end_date | date | — |
| status | varchar | — |

Table 85 omits `break_duration`, both effective-date fields, and `status`.

### 3.5 `request_type_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| request_type_id | integer | PK |
| type_name | varchar | — |

This corresponds to Table 85's `REQUEST_TYPE`, where the source misspells
the ID as `Requeest_type_id`.

### 3.6 `requests_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| request_id | integer | PK |
| employee_id | integer | FK |
| request_type_id | integer | FK |
| start_date | date | — |
| end_date | date | — |
| amount | decimal | — |
| reason | varchar | — |
| status | varchar | — |
| approved_by | integer | FK |
| approved_date | date | FK marker shown in Figure 163 |

Table 85 instead has a single `request_date` and omits the request detail
and approval fields. Figure 163 appears to mark `approved_date` as an FK,
which is probably a diagram error and must not be implemented without
confirmation.

### 3.7 `attendance_adjustment_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| adjustment_id | integer | PK |
| attendance_id | integer | FK |
| adjusted_by | integer | FK |
| adjustment_type | varchar | — |
| old_time_in | time | — |
| new_time_in | time | — |
| old_time_out | time | — |
| new_time_out | time | — |
| reason | varchar | — |
| adjustment_date | timestamp | — |

Table 85 uses `employee_id`, `attendance_date`, `adjusted_time_in`, and
`adjusted_time_out` instead. This is a substantive model conflict.

### 3.8 `bank_details_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| bank_id | integer | PK |
| employee_id | integer | FK |
| bank_name | varchar | — |
| account_name | varchar | — |
| account_number | varchar | — |
| account_type | varchar | — |
| created_at | timestamp | — |

Table 85 omits `account_name`, `account_type`, and `created_at`.

### 3.9 `cash_transaction_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| transaction_id | integer | PK |
| payroll_id | integer | FK |
| bank_id | integer | FK |
| amount | decimal | — |
| transaction_date | date | — |
| reference_no | varchar | — |
| transfer_type | varchar | — |
| status | varchar | — |

Table 85 uses `employee_id` and `transaction_type` instead of the model's
`payroll_id`, `bank_id`, and `transfer_type`, and omits the remaining audit
fields.

### 3.10 `payroll_earnings_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| earning_id | integer | PK |
| payroll_id | integer | FK |
| earning_type | varchar | — |
| description | varchar | — |
| amount | decimal | — |

Table 85 misspells the entity as `PAYROLL_LEARNINGS`, uses `employee_id`
instead of `payroll_id`, and omits `description`.

### 3.11 `audit_logs_tbl`

| Model attribute | Figure 164 type | Figure 163 key |
|---|---|---|
| log_id | integer | PK |
| user_id | integer | FK |
| action_performed | varchar | — |
| table_affected | varchar | — |
| record_id | integer | — |
| action_date | timestamp | — |
| description | varchar | — |

Table 85 instead lists `Log_in`, `employee_id`, `action`, and `log_date`.
The models support interpreting `Log_in` as a typo for `log_id`, but the
remaining fields still describe a different audit structure.

---

## 4. Relationship evidence and conflicts

Relationships must be attributed to a particular source representation;
they cannot safely be merged as though the capstone defined one coherent
model.

### Relationships shown in Figures 163–164

- `branch_tbl 1—N employee_tbl` through `employee_tbl.branch_id`.
- `employee_tbl 1—1 users_tbl` is suggested by the `UQ` marker on
  `users_tbl.employee_id`.
- `role_tbl 1—N users_tbl`.
- `employee_tbl 1—N salary_tbl`, `attendance_tbl`, `workschedule_tbl`,
  `requests_tbl`, and `bank_details_tbl`.
- `attendance_tbl 1—N attendance_adjustment_tbl`.
- `payroll_tbl 1—N payroll_earnings_tbl` and `deductions_tbl`.
- `request_type_tbl 1—N requests_tbl`.
- `payroll_tbl` participates in cash transactions and payslip generation.

### Relationships present only in other source sections

- Table 85 and Tables 86–90 connect `employee` to `address`, and `address`
  directly to `province`, `city`, and `barangay`. They do **not** define a
  `province → city → barangay` hierarchy. Those five tables are absent from
  Figures 163–164.
- `benefits` appears in Table 85 and Table 95 but is absent from Figures
  163–164 and contains no employee or payroll foreign key in those tables.
- `cash_advance_history` appears in Table 85, Table 97, and Figure 163, but
  is absent from Figure 164.
- Table 92 places `salary_id` and `deduction_id` on `payroll_tbl`; Figures
  163–164 instead place `employee_id` and payroll totals on `payroll_tbl`,
  and Figure 164 places `payroll_id` on `deductions_tbl` and
  `payroll_earnings_tbl`.

These differences require an explicit canonical-schema decision before
migrations or ORM models are created.

---

## 5. Confirmed source-document issues

1. **Competing schema versions:** Table 85 has 23 entities, Figure 163 has
   18, Figure 164 has 17, and the Data Dictionary covers 12. Their columns
   and relationships are not equivalent.
2. **Mislabeled table heading:** "Table 90. Data Dictionary of Cash
   Advance" contains the barangay definition.
3. **CITY naming conflict:** Table 85 uses `description`; Table 89 uses
   `city_name`.
4. **Employee-schema conflict:** Table 85/Table 86 omit `branch_id`, while
   Figures 163–164 include it as a source-defined field. The diagrams and
   dictionary also contain substantially different employee attributes.
5. **Incomplete dictionaries:** 11 of 23 Final Relation entities have
   model types and key markers but no complete Data Dictionary entry.
6. **Literal Table 85 errors:** `Requeest_type_id`, `PAYROLL_LEARNINGS`,
   `Log_in`, and `approval_status_remarks` conflict with later source
   sections or likely intended names.
7. **Payroll-model conflict:** Table 92 models one `salary_id` and one
   `deduction_id` per payroll row. Figures 163–164 instead model payroll
   totals and child earnings/deduction rows.
8. **Geographic relationship overreach:** the source does not define
   `province → city → barangay`; it stores three independent IDs on
   `address_tbl`.
9. **Missing government-rate structures:** no table models effective-dated
   SSS brackets or PhilHealth/Pag-IBIG rates despite REQ061–REQ064.
10. **Missing holiday structure:** no table models holiday dates despite
    REQ030 and the holiday calendar UI.
11. **Branch-count inconsistency:** the company context describes employees
    across two branches, while the stakeholder-benefit text refers to
    monitoring all three branches.

### 5.1 Supplemental operational evidence

The later HR follow-up and Final Defense Reviewer PDFs add operational
evidence that was not represented in Tables 85–97 or Figures 163–164:

- [`Follow-up-Questions-with-Answers-from-HR-1.pdf`](../../../docs/capstone_files/Follow-up-Questions-with-Answers-from-HR-1.pdf)
- [`Final-Defense-Reviewer-1.pdf`](../../../docs/capstone_files/Final-Defense-Reviewer-1.pdf)

1. Payroll is weekly: the recurring attendance period is Friday through
   Thursday, Saturday is the rest day, attendance closes Thursday, Owner
   approval occurs Friday morning, and disbursement/payslips occur Friday
   afternoon. The first Sunday-through-Thursday biometric period was a
   one-time transition, not a calendar variant.
2. SSS, PhilHealth, and Pag-IBIG employee shares are deducted only on the
   last Friday of the month. SSS and PhilHealth use EEMR/Monthly Basic
   Salary, computed as `(daily_rate × 313) ÷ 12`, rather than variable
   weekly earnings.
3. The supplemental DFD routes `Government Contributions` into the D4
   `Deductions` store and contains no Benefits store. This resolves the
   canonical treatment of government contributions as deductions and
   provides evidence to exclude the orphan `benefits_tbl` from the build.
4. Holiday calculations require a holiday classification absent from the
   source schema: regular-holiday work pays 200% and special-holiday work
   pays 130%. Overtime pays 125%; the documents do not define how overtime
   on a holiday compounds.
5. Salary disbursement is not a bank-upload file: the company uses one
   aggregate pay-to-cash cheque per week and one manually prepared BDO
   deposit slip per employee. There is no ATM payroll or non-BDO flow.
6. `employee_type` has confirmed values `Regular` and `Contractual`, with a
   six-month contractual evaluation period.
7. The Owner draws no salary. The canonical user model therefore permits a
   Business Owner user with no employee row; this database consequence is
   an implementation inference from the operational answer.
8. Current employees have no income-tax withholding because the HR answer
   states they remain at or below a ₱250,000 annual threshold. This is a
   configurable current policy, not evidence for a permanent universal tax
   exemption.
9. Attendance policy includes memo/suspension and AWOL/termination review
   thresholds. These require auditable HR-review flags, not automatic
   employment actions.
10. The three supplied biometric samples are legacy `.xls` monthly daily-log
    matrices, not `.dat` punch streams. Each contains 138 enrollment rows,
    fixed identity columns, date columns, and zero or more `HH:mm` tokens per
    employee/day. They contain neither an in/out flag nor a transaction ID.
    ADR-0001 defines the canonical import expansion and deduplication rules.

---

## 6. Accepted canonical decisions

ADR-0001 accepts these decisions for MVP development. They are not claims
about what the original capstone consistently specifies and do not certify
production statutory master data.

1. Use the Figure 163/164 operational model as the starting point because
   it contains richer payroll, schedule, approval, and audit structures.
2. Preserve the source-backed employee/branch relationship through
   effective-dated `employee_branch_assignment`; do not keep a mutable direct
   branch field as the canonical source of current and historical assignment.
3. Reconcile the employee fields by taking the union of required identity,
   employment, address, branch, and government-identifier fields, then
   document nullability and uniqueness explicitly.
4. Use child tables keyed by `payroll_id` for multiple earnings and
   deductions rather than the single `payroll.deduction_id` structure.
5. Use `city_name`, `request_type_id`, `payroll_earnings`, `log_id`, and
   separate `approval_status`/`remarks` as canonical corrected names.
6. Keep the source's flat address links for the MVP. A strict
   province→city→barangay hierarchy requires a future ADR and must not be
   attributed to the original schema.
7. Add complete Data Dictionary entries for every canonical table before
   implementation approval.
8. Exclude the orphan `benefits_tbl` from the canonical model. Record
   employee government shares in `deductions_tbl` and retain a separate
   auditable contribution-calculation record for employee/employer shares.
9. Make `users.employee_id` nullable and unique so employee-linked users
   remain one-to-one while the non-salaried Business Owner can be user-only.
10. Add `pay_date` and contribution-basis/audit fields required to enforce
    the weekly cycle and last-Friday contribution schedule.
11. Replace the assumed bank-file flow with a weekly disbursement batch
    (one cheque) and employee deposit-slip records.
12. Add configurable attendance sites/devices, effective device/branch
    coverage, employee biometric enrollment, immutable raw punches, and
    checksum-based workbook import batches.
13. Add `payroll_period` and branch-scoped `payroll_run` above employee
    payroll details, with unique period/branch and period/employee guards.
14. Model an approved cash advance as one `cash_advance_history` record and
    post repayments as payroll deductions.

---

## 7. Schema extensions (not part of the original documentation)

> **Version note:** these are the v1.0 extension proposals. ADR-0002 corrects
> their missing lineage, policy-version, lifecycle, and uniqueness rules. Use
> the v1.1 capstone addendum—not this historical proposal—as the migration
> dictionary.

These extensions support features the requirements call for but the original
schema never modeled. They are **not** sourced from the capstone document —
listed here so they are clearly separated from the source-of-truth schema
above. Full Data Dictionary entries are provided for each table so that the
implementation sign-off requirement (§6 recommendation #7) is satisfied for
these extension tables.

The extension dictionaries below are reconciled to ADR-0001, including the
real `.xls` workbook contract, effective branch/device history, branch-level
payroll runs, and manual BDO disbursement workflow.

The employee→branch relationship is source-backed by Figures 163 and 164;
the effective-dated assignment table is the implementation extension used to
preserve that relationship over time.

The accepted implementation reconciliation appears in `design.md` and
ADR-0001. SQL migrations must cite both this audit and the ADR.

---

### 7.1 `attendance_site`

Physical location of a biometric device (distinct from the organizational
branch). Supports the two-device, two-site operational topology confirmed in
the follow-up interview. Required by REQ018 AC3.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| site_id | Attendance site identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| site_code | Short code for the site | VARCHAR(20) | — | — | N | ✅ | e.g. `BANGA`, `SURALLAH` |
| site_name | Full descriptive name | VARCHAR(100) | — | — | N | — | — |
| address | Street / location description | VARCHAR(200) | — | — | Y | — | — |
| timezone | IANA timezone identifier | VARCHAR(50) | — | — | N | — | Default `Asia/Manila` |
| status | Operational status | ENUM('Active','Inactive') | — | — | N | — | Default `Active` |

---

### 7.2 `biometric_device`

A registered biometric scanner. Each device belongs to one attendance site
and has a known export file format. Required by REQ018 AC3.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| device_id | Device identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| site_id | Attendance site (FK → attendance_site) | INT UNSIGNED | — | ✅ | N | — | — |
| device_code | Short identifier for the device | VARCHAR(30) | — | — | N | ✅ | e.g. `DEV-BANGA-01` |
| device_name | Descriptive name | VARCHAR(100) | — | — | Y | — | — |
| serial_number | Hardware serial number | VARCHAR(100) | — | — | Y | ✅ | — |
| file_format | Export format identifier | VARCHAR(50) | — | — | N | — | MVP: `LDE_XLS_DAILY_LOG_V1` |
| timezone | IANA timezone of the device | VARCHAR(50) | — | — | N | — | Default `Asia/Manila` |
| status | Device status | ENUM('Active','Inactive','Retired') | — | — | N | — | Default `Active` |
| installed_at | Date device was registered | DATE | — | — | Y | — | — |
| retired_at | Date device was retired | DATE | — | — | Y | — | NULL while active |

---

### 7.3 `biometric_device_branch`

Effective-dated N–M coverage between devices and organizational branches.
Allows one device to serve multiple branches and one branch to be served by
multiple devices. Required by REQ018 AC3, AC12.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| device_branch_id | Coverage record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| device_id | Device (FK → biometric_device) | INT UNSIGNED | — | ✅ | N | — | — |
| branch_id | Branch (FK → branch) | INT UNSIGNED | — | ✅ | N | — | — |
| effective_from | Coverage start date | DATE | — | — | N | — | — |
| effective_to | Coverage end date (NULL = current) | DATE | — | — | Y | — | — |
| status | Record status | ENUM('Active','Inactive') | — | — | N | — | Default `Active` |

Unique constraint: `(device_id, branch_id, effective_from)`.

---

### 7.4 `employee_branch_assignment`

Effective-dated branch assignment for employees, enabling permanent transfers
without rewriting historical records. Replaces a direct `employee.branch_id`
column for canonical implementation. Required by REQ004 AC10–11, REQ010 AC14.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| branch_assignment_id | Assignment record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| employee_id | Employee (FK → employee) | INT UNSIGNED | — | ✅ | N | — | — |
| branch_id | Branch (FK → branch) | INT UNSIGNED | — | ✅ | N | — | — |
| effective_from | Assignment start date | DATE | — | — | N | — | — |
| effective_to | Assignment end date (NULL = current) | DATE | — | — | Y | — | — |
| transfer_reason | Reason for the transfer | VARCHAR(255) | — | — | Y | — | NULL for initial assignment |
| transferred_by | User who recorded the transfer (FK → users) | INT UNSIGNED | — | ✅ | Y | — | — |
| created_at | Record creation timestamp | TIMESTAMP | — | — | N | — | DEFAULT CURRENT_TIMESTAMP |

Constraint: no two assignments for the same `employee_id` may have
overlapping `(effective_from, effective_to)` date ranges.

---

### 7.5 `employee_biometric_enrollment`

Ties an employee to a device-specific identifier code for punch matching.
Required by REQ018 AC4.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| enrollment_id | Enrollment record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| employee_id | Employee (FK → employee) | INT UNSIGNED | — | ✅ | N | — | — |
| device_id | Device (FK → biometric_device) | INT UNSIGNED | — | ✅ | N | — | — |
| device_employee_code | Employee identifier as stored on the device | VARCHAR(50) | — | — | N | — | — |
| effective_from | Enrollment start date | DATE | — | — | N | — | — |
| effective_to | Enrollment end date (NULL = current) | DATE | — | — | Y | — | — |
| status | Enrollment status | ENUM('Active','Inactive') | — | — | N | — | Default `Active` |

Unique constraint: `(device_id, device_employee_code)` over non-overlapping
date ranges — the same code on the same device must not map to two employees
at the same time.

---

### 7.6 `holiday_calendar`

Holiday dates with type classification and confirmed pay multipliers.
Required by REQ030 and REQ047 (pay multipliers). Multipliers sourced from
Final Defense Reviewer PDF (Regular = 2.00, Special = 1.30).

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| holiday_id | Holiday record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| holiday_date | Date of the holiday | DATE | — | — | N | ✅ | — |
| description | Holiday name or description | VARCHAR(100) | — | — | N | — | e.g. `Independence Day` |
| holiday_type | Classification | ENUM('Regular','Special') | — | — | N | — | Drives pay multiplier |
| pay_multiplier | Daily-rate multiplier when work is performed | DECIMAL(4,2) | — | — | N | — | Regular = 2.00; Special = 1.30 |
| status | Record status | ENUM('Active','Inactive') | — | — | N | — | Default `Active` |

---

### 7.7 `attendance_import_batch`

Audit record for each `.xls` daily-log upload. The file checksum provides
idempotency — re-uploading the same file is detected and rejected.
Required by REQ018 AC2, REQ024.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| import_batch_id | Batch identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| device_id | Source device (FK → biometric_device) | INT UNSIGNED | — | ✅ | N | — | — |
| uploaded_by | HR user who uploaded the file (FK → users) | INT UNSIGNED | — | ✅ | N | — | — |
| file_name | Original file name | VARCHAR(255) | — | — | N | — | — |
| file_checksum | SHA-256 hash of the uploaded file | CHAR(64) | — | — | N | ✅ | Duplicate-upload guard |
| source_year | Year supplied by HR for `MM/DD ddd` headers | SMALLINT UNSIGNED | — | — | N | — | Required before timestamp construction |
| source_month | Month supplied by HR for `MM/DD ddd` headers | TINYINT UNSIGNED | — | — | N | — | CHECK `BETWEEN 1 AND 12` |
| parser_version | Versioned adapter identifier | VARCHAR(50) | — | — | N | — | MVP: `LDE_XLS_DAILY_LOG_V1` |
| status | Import lifecycle | ENUM('Processing','Completed','Rejected') | — | — | N | — | No partial completed import |
| uploaded_at | Upload timestamp | TIMESTAMP | — | — | N | — | DEFAULT CURRENT_TIMESTAMP |
| records_parsed | Total punch records parsed from file | INT UNSIGNED | — | — | N | — | — |
| records_matched | Punch records matched to an enrollment | INT UNSIGNED | — | — | N | — | — |
| records_unmatched | Punch records with no matching enrollment | INT UNSIGNED | — | — | N | — | — |
| duplicates_skipped | Records skipped as duplicate punches | INT UNSIGNED | — | — | N | — | — |
| incomplete_days | Employee/date groups containing one punch | INT UNSIGNED | — | — | N | — | Default `0` |
| multi_punch_days | Employee/date groups containing more than two punches | INT UNSIGNED | — | — | N | — | Default `0` |
| completed_at | Completion/rejection timestamp | TIMESTAMP | — | — | Y | — | NULL while processing |

---

### 7.8 `biometric_punch`

Immutable staging record for every raw punch from an import. Unmatched
punches remain here for HR reconciliation and are never dropped.
Required by REQ018 AC5, REQ018 AC10, REQ024.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| punch_id | Punch record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| import_batch_id | Source import batch (FK → attendance_import_batch) | INT UNSIGNED | — | ✅ | N | — | — |
| device_id | Source device (FK → biometric_device) | INT UNSIGNED | — | ✅ | N | — | — |
| employee_id | Matched employee (FK → employee, NULL if unmatched) | INT UNSIGNED | — | ✅ | Y | — | NULL until matched |
| branch_assignment_id | Resolved branch assignment (FK → employee_branch_assignment) | INT UNSIGNED | — | ✅ | Y | — | NULL until resolved |
| device_employee_code | Employee code as read from the device | VARCHAR(50) | — | — | N | — | — |
| source_local_at | Device-local punch timestamp | DATETIME | — | — | N | — | `Asia/Manila`; used for matching/deduplication |
| punched_at_utc | Normalized UTC punch instant | DATETIME | — | — | N | — | Derived at the import boundary |
| punch_type | Adapter-supplied in/out indicator | ENUM('In','Out','Unknown') | — | — | Y | — | NULL for ADR-0001 `.xls` source |
| device_transaction_id | Device-native transaction ID if present | VARCHAR(50) | — | — | Y | — | NULL for ADR-0001 `.xls` source |
| match_status | Resolution status | ENUM('matched','unmatched','coverage_exception','duplicate') | — | — | N | — | — |
| source_department | `Dept` value from the workbook | VARCHAR(100) | — | — | Y | — | Evidence only; not branch authority |
| source_user_id | `User ID` value from the workbook | VARCHAR(50) | — | — | Y | — | Evidence only |
| source_employee_name | `Name` value from the workbook | VARCHAR(150) | — | — | Y | — | Evidence only; not identity authority |
| raw_record | Original date-cell value and parser evidence | TEXT | — | — | N | — | Immutable; never updated |
| source_workbook_row | One-based workbook row | INT UNSIGNED | — | — | N | — | — |
| source_date_column | Original date-column label | VARCHAR(20) | — | — | N | — | e.g. `06/01 Mon` |
| resolved_by | User who manually resolved the punch (FK → users) | INT UNSIGNED | — | ✅ | Y | — | NULL until resolved |
| resolved_at | Timestamp of manual resolution | TIMESTAMP | — | — | Y | — | NULL until resolved |

Unique constraint for the MVP adapter:
`(device_id, device_employee_code, source_local_at)`. The workbook has no native
transaction ID, so this key plus the batch SHA-256 provides idempotency.

---

### 7.9 `attendance_policy_flag`

HR-review alert raised when attendance thresholds are breached. The system
never applies discipline automatically; HR records the reviewed action.
Required by REQ006 AC16–17 (supplemental HR answer, p. 1).

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| flag_id | Flag identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| employee_id | Flagged employee (FK → employee) | INT UNSIGNED | — | ✅ | N | — | — |
| flag_type | Policy threshold breached | ENUM('ConsecutiveLate','TardinessMemoCap','TwoWeekAbsence','ConsecutiveAWOL') | — | — | N | — | — |
| triggering_date | Date the threshold was reached | DATE | — | — | N | — | — |
| status | Review status | ENUM('Pending','Reviewed','Closed') | — | — | N | — | Default `Pending` |
| reviewed_by | HR user who reviewed the flag (FK → users) | INT UNSIGNED | — | ✅ | Y | — | NULL until reviewed |
| reviewed_at | Review timestamp | TIMESTAMP | — | — | Y | — | — |
| action_taken | HR's documented response | VARCHAR(255) | — | — | Y | — | — |
| notes | Supporting notes | TEXT | — | — | Y | — | — |

---

### 7.10 `payroll_period`

Concrete Friday–Thursday pay period definition shared across all branch
payroll runs. Separates the disbursement date (`pay_date`) from the
attendance window. Required by REQ047 AC8.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| payroll_period_id | Period identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| period_start | First day of the attendance window (Friday) | DATE | — | — | N | ✅ | — |
| period_end | Last day of the attendance window (Thursday) | DATE | — | — | N | — | — |
| pay_date | Disbursement date (Friday of the following week) | DATE | — | — | N | — | Used for last-Friday contribution logic |
| status | Period status | ENUM('Open','Closed','Approved') | — | — | N | — | Default `Open` |

Unique constraint: `period_start` (one period per start date).

---

### 7.11 `payroll_run`

One branch-scoped payroll transaction per period with its own approval state
machine. Required by REQ050–051, REQ010 AC12–16.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| payroll_run_id | Run identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| payroll_period_id | Pay period (FK → payroll_period) | INT UNSIGNED | — | ✅ | N | — | — |
| branch_id | Branch for this run (FK → branch) | INT UNSIGNED | — | ✅ | N | — | — |
| status | Approval state | ENUM('Draft','Computed','PendingApproval','Approved','Returned') | — | — | N | — | Default `Draft` |
| total_salary | Aggregate gross salary for the run | DECIMAL(15,2) | — | — | Y | — | NULL until computed |
| total_deductions | Aggregate deductions for the run | DECIMAL(15,2) | — | — | Y | — | NULL until computed |
| total_benefits | Aggregate benefits for the run | DECIMAL(15,2) | — | — | Y | — | NULL until computed |
| net_pay | Aggregate net pay for the run | DECIMAL(15,2) | — | — | Y | — | NULL until computed |
| computed_by | User who computed the run (FK → users) | INT UNSIGNED | — | ✅ | Y | — | — |
| computed_at | Computation timestamp | TIMESTAMP | — | — | Y | — | — |
| submitted_by | User who submitted for approval (FK → users) | INT UNSIGNED | — | ✅ | Y | — | — |
| submitted_at | Submission timestamp | TIMESTAMP | — | — | Y | — | — |
| approved_by | Owner who approved or returned (FK → users) | INT UNSIGNED | — | ✅ | Y | — | — |
| approved_at | Approval/return timestamp | TIMESTAMP | — | — | Y | — | — |
| return_reason | Reason if returned | VARCHAR(500) | — | — | Y | — | Required when status = Returned |

Unique constraint: `(payroll_period_id, branch_id)` — one run per branch per period.

---

### 7.12 `sss_bracket`

SSS salary bracket lookup table for contribution computation. Required by
REQ061–062. Bracket data must be seeded from an approved version of the
current SSS contribution schedule before any payroll computation.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| bracket_id | Bracket identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| salary_from | Lower bound of the monthly salary range (EEMR) | DECIMAL(10,2) | — | — | N | — | Inclusive |
| salary_to | Upper bound of the monthly salary range (EEMR) | DECIMAL(10,2) | — | — | Y | — | NULL = no upper cap |
| employee_share | Employee monthly contribution | DECIMAL(10,2) | — | — | N | — | — |
| employer_share | Employer monthly contribution | DECIMAL(10,2) | — | — | N | — | — |
| effective_date | Date this bracket set takes effect | DATE | — | — | N | — | — |

> **Approval gate:** bracket rows must be reviewed and approved from the
> current official SSS schedule before seeding into any environment.

---

### 7.13 `philhealth_rate`

PhilHealth contribution rate, keyed by effective date. Required by
REQ063. The current policy applies a percentage to the EEMR with a ceiling
cap; the rate and cap must be seeded from the approved current schedule.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| rate_id | Rate record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| rate_percent | Contribution rate as a percentage | DECIMAL(5,4) | — | — | N | — | e.g. `0.0500` for 5% |
| monthly_ceiling | Maximum monthly EEMR subject to contribution | DECIMAL(10,2) | — | — | Y | — | NULL = no ceiling |
| effective_date | Date this rate takes effect | DATE | — | — | N | ✅ | — |

> **Approval gate:** must be seeded from the approved current PhilHealth
> circular before payroll computation.

---

### 7.14 `pagibig_rate`

Pag-IBIG contribution rate, keyed by effective date. Required by REQ064.
Note: the supplemental calculation sheet does not establish EEMR as the
Pag-IBIG basis — implementation should confirm the correct basis before
seeding.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| rate_id | Rate record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| rate_percent | Contribution rate as a percentage | DECIMAL(5,4) | — | — | N | — | — |
| monthly_ceiling | Maximum monthly contribution | DECIMAL(10,2) | — | — | Y | — | NULL = no ceiling |
| effective_date | Date this rate takes effect | DATE | — | — | N | ✅ | — |

> **Approval gate:** must be seeded from the approved current Pag-IBIG
> circular before payroll computation.

---

### 7.15 `contribution_record`

Auditable record of each SSS/PhilHealth/Pag-IBIG calculation, including
EEMR basis and both employee and employer shares. Employee shares are also
posted as `deduction` rows on the last-Friday payroll of the month.
Required by REQ058–064.

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| contribution_id | Record identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| payroll_id | Associated payroll row (FK → payroll) | INT UNSIGNED | — | ✅ | N | — | — |
| contribution_type | Contribution program | ENUM('SSS','PhilHealth','PagIBIG') | — | — | N | — | — |
| eemr_basis | EEMR used as computation basis | DECIMAL(12,2) | — | — | N | — | `(daily_rate × 313) ÷ 12` |
| employee_share | Computed employee contribution | DECIMAL(10,2) | — | — | N | — | — |
| employer_share | Computed employer contribution | DECIMAL(10,2) | — | — | N | — | Stored for reporting |
| deduction_date | Date contribution was deducted (last Friday of month) | DATE | — | — | N | — | — |
| status | Lock state | ENUM('Computed','Locked') | — | — | N | — | Default `Computed` |
| locked_at | Timestamp when record was locked | TIMESTAMP | — | — | Y | — | NULL until locked |
| locked_by | User who locked the record (FK → users) | INT UNSIGNED | — | ✅ | Y | — | NULL until locked |

---

### 7.16 `disbursement_batch`

One aggregate pay-to-cash cheque per weekly payroll cycle. Supersedes
Figure 163/164's per-payroll `cash_transaction` shape, which cannot
represent the confirmed batch disbursement flow. Required by REQ073
(Supplemental HR answer, pp. 2–3).

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| batch_id | Batch identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| period_start | Start of the attendance window covered | DATE | — | — | N | — | — |
| period_end | End of the attendance window covered | DATE | — | — | N | — | — |
| pay_date | Cheque/disbursement date | DATE | — | — | N | — | — |
| bank_name | Bank where cheque is drawn | VARCHAR(100) | — | — | N | — | Currently BDO only |
| cheque_number | Cheque number | VARCHAR(50) | — | — | Y | ✅ | NULL until issued |
| cheque_total | Total aggregate amount | DECIMAL(15,2) | — | — | N | — | — |
| status | Batch status | ENUM('Prepared','Issued','Reconciled') | — | — | N | — | Default `Prepared` |
| prepared_by | User who prepared the batch (FK → users) | INT UNSIGNED | — | ✅ | N | — | — |
| submitted_at | Timestamp batch was finalized | TIMESTAMP | — | — | Y | — | — |

---

### 7.17 `deposit_slip`

One manually prepared BDO deposit slip per employee per weekly payroll.
Required by REQ073 (Supplemental HR answer, pp. 2–3).

| Attribute | Description | Data Type | PK | FK | Null? | Unique | Notes |
|---|---|---|---|---|---|---|---|
| slip_id | Slip identifier | INT UNSIGNED AUTO_INCREMENT | ✅ | — | N | — | — |
| batch_id | Parent disbursement batch (FK → disbursement_batch) | INT UNSIGNED | — | ✅ | N | — | — |
| payroll_id | Employee payroll row (FK → payroll) | INT UNSIGNED | — | ✅ | N | ✅ | One slip per payroll row |
| bank_id | Employee bank details (FK → bank_details) | INT UNSIGNED | — | ✅ | N | — | — |
| amount | Net pay amount to deposit | DECIMAL(12,2) | — | — | N | — | Must equal payroll.net_pay |
| preparation_status | Slip preparation state | ENUM('Pending','Prepared','Deposited') | — | — | N | — | Default `Pending` |
| prepared_at | Timestamp slip was prepared | TIMESTAMP | — | — | Y | — | — |

---

> **Geography hierarchy extension (not adopted by default):** Adding
> `city.province_id` and `barangay.city_id` foreign keys would create a
> province → city → barangay referential hierarchy. The source schema never
> defines this — all three IDs are stored independently on `address`. This
> is an available implementation option but must be explicitly decided before
> any migration adds those columns. See §6 recommendation #6 and §8 for
> the current decision status.

---

## 8. Adoption status (updated after `design.md` reconciliation)

`design.md`'s Data Models section applies the
recommendations in §6, with every table now labeled by provenance
(`[Fig163/164]`, `[Table85/86]`, `[canonical correction]`,
`[canonical baseline decision]`, `[extension]`). ADR-0001 approves this
shape for MVP migrations; production master data and statutory certification
remain separate gates.

| # | Recommendation (§6) | Status in `design.md` | Notes |
|---|---|---|---|
| 1 | Use Fig. 163/164 as the operational base | ✅ Applied with a documented disbursement exception | Core operational tables follow Fig. 163/164. The later HR evidence supersedes `cash_transaction` with `disbursement_batch` + `deposit_slip`. |
| 2 | Preserve employee/branch relationship | ✅ Applied through history | `employee_branch_assignment` replaces a mutable direct field while retaining the source-backed relationship. |
| 3 | Union the employee fields | ✅ Applied | Required/optional fields and uniqueness are frozen in ADR-0001; device identity is normalized into effective enrollments. |
| 4 | `payroll_id`-keyed child tables for earnings/deductions | ✅ Applied | `payroll` is a header row; `deduction` and `payroll_earnings` are children keyed by `payroll_id`, matching Fig. 163/164. Government employee shares post to `deduction`; `benefits_tbl` is excluded. |
| 5 | Canonical corrected names (`city_name`, `request_type_id`, `payroll_earnings`, `log_id`→`audit_logs.log_id`, split `approval_status`/`remarks`) | ✅ Applied | All five corrections are in `design.md`'s table definitions. |
| 6 | Decide geography hierarchy explicitly | ✅ Flat for MVP | `address` retains the source's direct geography references; a strict hierarchy is deferred. |
| 7 | Complete Data Dictionary entries for every canonical table | ✅ Completed by ADR-0002 addendum | The v1.1 capstone addendum defines types, nullability, controlled values, keys, checks, indexes, and cross-row enforcement for the complete canonical model. |

### Canonical resolution from supplemental evidence

- **`benefits_tbl` is resolved for canonical implementation:** it remains
  documented as a literal Table 85/95 artifact, but it is not migrated.
  The supplemental DFD confirms that government contributions flow to
  Deductions. A future non-government bonus/benefit feature would need its
  own approved requirements and model.

### Development-blocking conflicts

None after ADR-0002. The second integrity review resolved the remaining
migration blockers: workbook year resolution, attendance grain/raw lineage,
one payroll status authority, period/detail consistency, calculation snapshots,
contribution/deduction linkage, temporal enforcement, persisted sessions,
unauthenticated audit events, request/leave/cash-advance lifecycles, and
period-level disbursement.

The following remain production confirmations rather than migration blockers:
official branch display names, complete statutory tables/caps/effective dates,
holiday-overtime compounding, and HR acceptance of production seed data.

### Recommended next step

Implement migrations from the v1.1 capstone addendum, `design.md`, ADR-0001,
and ADR-0002, in that precedence order. Preserve provenance in migration
comments and tests. Do not describe the documented demo contribution fixture
as production-certified statutory logic.

## 9. ADR-0002 correction summary

| Integrity risk | Canonical correction |
|---|---|
| Incomplete implementation dictionary | The v1.1 capstone addendum supplies one typed dictionary for all canonical tables. |
| Three payroll status spellings and duplicated detail status | Store only `payroll_run.status` using `PendingOwnerApproval`; employee payroll rows carry no approval fields. |
| Duplicate attendance and missing raw-punch lineage | `UNIQUE(employee_id, attendance_date)` plus `attendance_punch`. |
| `MM/DD ddd` headers lack a year | Import batch requires `source_year` and `source_month`; parser validates month and weekday. |
| Opaque payroll amounts | Payroll snapshots salary/rate; line items store quantity, rate, multiplier, source FKs, and calculation JSON. |
| Duplicate/untraceable contribution posting | Unique payroll/program, unique deduction FK, and exact contribution-policy version. |
| Temporal overlaps | Half-open periods, bound checks, one-open-row guards, parent-key locking, and overlap tests. |
| Owner recovery and persisted-session ambiguity | Account-owned email, hashed reset challenge, and migration-owned sessions table. |
| Failed-login events could not satisfy audit FK | Nullable actor plus attempted identifier, event, IP, user agent, and request ID. |
| Request, leave, and cash-advance gaps | Type-specific request details, leave entitlement/ledger, request-linked obligation, and repayment child rows. |
| One cheque incorrectly scoped to one branch run | One unique disbursement batch per payroll period, after all included runs are Approved. |
| Non-unique payslips/current bank ambiguity | Unique payslip per payroll and effective-dated BDO bank history. |
