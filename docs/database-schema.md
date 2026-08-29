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

---

## 6. Recommended canonical decisions

These are recommendations for a buildable schema, not claims about what
the original capstone consistently specifies.

1. Use the Figure 163/164 operational model as the starting point because
   it contains richer payroll, schedule, approval, and audit structures.
2. Keep `employee.branch_id`; it is source-backed by both database-model
   figures and required by the narrative and branch filters.
3. Reconcile the employee fields by taking the union of required identity,
   employment, address, branch, and government-identifier fields, then
   document nullability and uniqueness explicitly.
4. Use child tables keyed by `payroll_id` for multiple earnings and
   deductions rather than the single `payroll.deduction_id` structure.
5. Use `city_name`, `request_type_id`, `payroll_earnings`, `log_id`, and
   separate `approval_status`/`remarks` as canonical corrected names.
6. Decide whether geography is a strict hierarchy. If it is, add explicit
   `city.province_id` and `barangay.city_id` foreign keys; do not claim the
   original schema already contains them.
7. Add complete Data Dictionary entries for every canonical table before
   implementation approval.

---

## 7. Schema extensions (not part of the original documentation)

These were proposed during later design work in this conversation to
support features the requirements call for but the original schema never
modeled. They are **not** sourced from the capstone document — listed here
only so they're clearly separated from the source-of-truth schema above.

- `sss_bracket`, `philhealth_rate`, `pagibig_rate` — contribution bracket
  and effective-rate tables (addresses issue #9).
- `holiday_calendar` — holiday dates (addresses issue #10).
- `employee.device_employee_id`, `attendance_import_batch`,
  `unmatched_punch` — added to support the `.dat` file upload / timesheet
  generation feature (a policy change made after the original
  documentation was written; not in the capstone at all).
- `city.province_id` and `barangay.city_id` — optional hierarchy fields if
  the implementation requires referentially constrained geographic levels.

`employee.branch_id` is **not** classified as an extension: it already
appears in Figures 163 and 164, although it conflicts with Table 85 and
Table 86.

The implementation-oriented reconciliation currently appears in
`design.md`. A future SQL migration should cite both this audit and the
documentation revision record before resolving the remaining decisions.
