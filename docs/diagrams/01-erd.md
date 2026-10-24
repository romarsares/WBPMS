# Entity Relationship Diagram (ERD)

Derived from actual Phinx migration files:
`20260831000001` (identity/access), `20260831000002` (organization/employee),
`20260831000003` (attendance import), `20260831000004` (requests/leave),
`20260831000005` (salary/payroll/contributions), `20260831000006` (bank disbursement),
`20260903000009` (employee lifecycle), `20260913000019` (HR notices), and amendment migrations.

```mermaid
erDiagram
    role {
        bigint role_id PK
        string role_name
    }

    users {
        bigint user_id PK
        bigint employee_id FK
        bigint role_id FK
        string username
        string account_email
        string password_hash
        enum status
        bool requires_password_change
    }

    sessions {
        string session_id PK
        bigint expires_at
        text data
    }

    audit_logs {
        bigint log_id PK
        bigint user_id FK
        string event_type
        string action_performed
        string table_affected
        bigint record_id
        string ip_address
        datetime action_at
    }

    password_reset_challenge {
        bigint challenge_id PK
        bigint user_id FK
        string otp_hash
        datetime expires_at
        int attempts_remaining
        datetime consumed_at
    }

    branch {
        bigint branch_id PK
        string branch_code
        string branch_name
        string location
        enum status
    }

    attendance_site {
        bigint site_id PK
        string site_code
        string site_name
        string timezone
        enum status
    }

    biometric_device {
        bigint device_id PK
        bigint site_id FK
        string device_code
        string file_format
        string timezone
        enum status
    }

    biometric_device_branch {
        bigint device_branch_id PK
        bigint device_id FK
        bigint branch_id FK
        date effective_from
        date effective_to
        enum status
    }

    province {
        bigint province_id PK
        string province_name
    }

    city {
        bigint city_id PK
        string city_name
    }

    barangay {
        bigint barangay_id PK
        string barangay_name
    }

    address {
        bigint address_id PK
        bigint province_id FK
        bigint city_id FK
        bigint barangay_id FK
        string street
    }

    employee {
        bigint employee_id PK
        string employee_number
        enum employee_type
        string first_name
        string last_name
        string middle_initial
        string email
        string contact_number
        date hire_date
        bigint address_id FK
        enum status
        string philhealth_number
        string pagibig_number
        string tin_number
        string position
    }

    employee_branch_assignment {
        bigint branch_assignment_id PK
        bigint employee_id FK
        bigint branch_id FK
        date effective_from
        date effective_to
        string transfer_reason
    }

    employee_biometric_enrollment {
        bigint enrollment_id PK
        bigint employee_id FK
        bigint device_id FK
        string device_employee_code
        date effective_from
        date effective_to
        enum status
    }

    employee_employment_episode {
        bigint episode_id PK
        bigint employee_id FK
        date started_on
        date ended_on
        string start_reason
        string end_reason
    }

    employee_lifecycle_event {
        bigint event_id PK
        bigint employee_id FK
        bigint episode_id FK
        string event_type
        string prior_status
        string new_status
        date effective_date
        bigint acted_by FK
    }

    employee_document {
        bigint document_id PK
        bigint employee_id FK
        string document_type
        string original_filename
        string storage_key
        string mime_type
        bigint uploaded_by FK
    }

    employment_contract_review {
        bigint review_id PK
        bigint employee_id FK
        date review_due_date
        enum outcome
        date effective_date
        bigint reviewed_by FK
    }

    work_schedule {
        bigint schedule_id PK
        bigint employee_id FK
        text working_days
        text rest_days
        int break_minutes
        time work_start_time
        time work_end_time
        int standard_minutes
        date effective_from
        date effective_to
        enum status
    }

    holiday_calendar {
        bigint holiday_id PK
        date holiday_date
        string description
        enum holiday_type
        decimal pay_multiplier
        enum status
    }

    attendance_import_batch {
        bigint import_batch_id PK
        bigint device_id FK
        bigint uploaded_by FK
        string file_checksum
        int source_year
        int source_month
        string parser_version
        enum status
        int records_parsed
        int records_matched
        int records_unmatched
    }

    biometric_punch {
        bigint punch_id PK
        bigint import_batch_id FK
        bigint device_id FK
        bigint employee_id FK
        string device_employee_code
        datetime source_local_at
        datetime punched_at_utc
        enum match_status
    }

    attendance {
        bigint attendance_id PK
        bigint employee_id FK
        bigint branch_assignment_id FK
        bigint schedule_id FK
        date attendance_date
        time time_in
        time time_out
        int hours_worked_minutes
        int late_minutes
        int undertime_minutes
        int overtime_minutes
        enum status
        enum source
    }

    attendance_punch {
        bigint attendance_id FK
        bigint punch_id FK
        string evidence_role
    }

    attendance_adjustment {
        bigint adjustment_id PK
        bigint attendance_id FK
        bigint adjusted_by FK
        string adjustment_type
        time old_time_in
        time new_time_in
        time old_time_out
        time new_time_out
        string reason
    }

    attendance_policy_flag {
        bigint flag_id PK
        bigint employee_id FK
        string flag_type
        date triggering_date
        enum status
        bigint reviewed_by FK
    }

    request_type {
        bigint request_type_id PK
        string type_name
        enum status
    }

    request {
        bigint request_id PK
        bigint employee_id FK
        bigint request_type_id FK
        string reason
        enum status
        datetime submitted_at
        bigint reviewed_by FK
    }

    leave_request_detail {
        bigint request_id PK_FK
        string leave_type
        date start_date
        date end_date
        int days_requested
    }

    overtime_request_detail {
        bigint request_id PK_FK
        date overtime_date
        time start_time
        time end_time
        int requested_minutes
    }

    cash_advance_request_detail {
        bigint request_id PK_FK
        decimal amount
    }

    leave_entitlement {
        bigint entitlement_id PK
        bigint employee_id FK
        string leave_type
        int leave_year
        int entitled_days
    }

    leave_ledger {
        bigint entry_id PK
        bigint entitlement_id FK
        bigint request_id FK
        string entry_type
        int days_delta
    }

    cash_advance_history {
        bigint history_id PK
        bigint request_id FK
        bigint employee_id FK
        decimal original_amount
        decimal remaining_balance
        enum status
    }

    salary {
        bigint salary_id PK
        bigint employee_id FK
        decimal daily_rate
        date effective_from
        date effective_to
        enum status
        bigint created_by FK
    }

    payroll_policy_version {
        bigint policy_id PK
        string policy_code
        string version
        int eemr_days_per_year
        decimal annual_tax_threshold
        decimal late_rate_per_minute
        enum status
    }

    contribution_policy_version {
        bigint contribution_policy_id PK
        string policy_code
        string version
        date effective_from
        enum status
    }

    sss_bracket {
        bigint bracket_id PK
        bigint contribution_policy_id FK
        decimal salary_from
        decimal salary_to
        decimal employee_share
        decimal employer_share
    }

    philhealth_rate {
        bigint rate_id PK
        bigint contribution_policy_id FK
        decimal rate_decimal
        decimal basis_floor
        decimal basis_ceiling
    }

    pagibig_rate {
        bigint rate_id PK
        bigint contribution_policy_id FK
        decimal rate_decimal
        decimal employee_fixed_amount
        decimal employer_fixed_amount
    }

    payroll_period {
        bigint payroll_period_id PK
        date period_start
        date period_end
        date pay_date
        enum status
    }

    payroll_run {
        bigint payroll_run_id PK
        bigint payroll_period_id FK
        bigint branch_id FK
        bigint payroll_policy_id FK
        enum status
        decimal gross_pay
        decimal total_deductions
        decimal net_pay
        bigint computed_by FK
        bigint reviewed_by FK
    }

    payroll {
        bigint payroll_id PK
        bigint payroll_run_id FK
        bigint payroll_period_id FK
        bigint employee_id FK
        bigint branch_assignment_id FK
        bigint salary_id FK
        decimal daily_rate_snapshot
        decimal gross_pay
        decimal total_deductions
        decimal net_pay
    }

    payroll_earnings {
        bigint earning_id PK
        bigint payroll_id FK
        string earning_type
        string description
        bigint source_attendance_id FK
        decimal quantity
        decimal unit_rate
        decimal multiplier
        decimal amount
    }

    deduction {
        bigint deduction_id PK
        bigint payroll_id FK
        string deduction_type
        string description
        decimal amount
    }

    contribution_record {
        bigint contribution_id PK
        bigint payroll_id FK
        bigint deduction_id FK
        bigint contribution_policy_id FK
        enum contribution_type
        decimal eemr_basis
        decimal employee_share
        decimal employer_share
    }

    payslip {
        bigint payslip_id PK
        bigint payroll_id FK
        date issue_date
        string file_path
        bigint generated_by FK
        string content_hash
    }

    bank_details {
        bigint bank_id PK
        bigint employee_id FK
        string bank_name
        string account_number
        date effective_from
        date effective_to
        enum status
    }

    disbursement_batch {
        bigint batch_id PK
        bigint payroll_period_id FK
        string cheque_number
        decimal cheque_total
        enum status
        bigint prepared_by FK
    }

    deposit_slip {
        bigint slip_id PK
        bigint batch_id FK
        bigint payroll_id FK
        bigint bank_id FK
        decimal amount
        enum status
    }

    cash_advance_repayment {
        bigint repayment_id PK
        bigint history_id FK
        bigint payroll_id FK
        bigint deduction_id FK
        decimal amount
    }

    employee_hr_notice {
        bigint notice_id PK
        bigint employee_id FK
        bigint issued_by FK
        string notice_type
        string subject
        text body
        enum status
        datetime acknowledged_at
    }

    job_position {
        bigint position_id PK
        string position_title
        enum status
        int sort_order
    }

    %% ─── Relationships ────────────────────────────────────────────────

    role ||--o{ users : "has"
    users }o--|| employee : "linked to"
    users ||--o{ audit_logs : "generates"
    users ||--o{ password_reset_challenge : "requests"

    address }o--|| province : "in"
    address }o--|| city : "in"
    address }o--|| barangay : "in"
    employee }o--o| address : "lives at"

    branch ||--o{ biometric_device_branch : "covered by"
    attendance_site ||--o{ biometric_device : "hosts"
    biometric_device ||--o{ biometric_device_branch : "covers"

    employee ||--o{ employee_branch_assignment : "assigned to"
    branch ||--o{ employee_branch_assignment : "has members"
    employee ||--o{ employee_biometric_enrollment : "enrolled in"
    biometric_device ||--o{ employee_biometric_enrollment : "registers"

    employee ||--o{ work_schedule : "has"
    employee ||--o{ employee_employment_episode : "has"
    employee ||--o{ employee_lifecycle_event : "has"
    employee ||--o{ employee_document : "owns"
    employee ||--o{ employment_contract_review : "has"

    biometric_device ||--o{ attendance_import_batch : "source of"
    attendance_import_batch ||--o{ biometric_punch : "contains"
    employee ||--o{ biometric_punch : "matched to"

    employee ||--o{ attendance : "has"
    employee_branch_assignment ||--o{ attendance : "contextualizes"
    work_schedule ||--o{ attendance : "governs"
    attendance_import_batch ||--o{ attendance : "generates"
    attendance ||--o{ attendance_punch : "linked via"
    biometric_punch ||--o{ attendance_punch : "evidences"
    attendance ||--o{ attendance_adjustment : "adjusted by"
    employee ||--o{ attendance_policy_flag : "flagged in"

    request_type ||--o{ request : "categorizes"
    employee ||--o{ request : "submits"
    request ||--o| leave_request_detail : "detail"
    request ||--o| overtime_request_detail : "detail"
    request ||--o| cash_advance_request_detail : "detail"
    leave_entitlement ||--o{ leave_ledger : "tracks"
    request ||--o| leave_ledger : "consumes"
    request ||--o| cash_advance_history : "creates obligation"
    employee ||--o{ leave_entitlement : "has"
    employee ||--o{ cash_advance_history : "owes"

    employee ||--o{ salary : "earns"
    contribution_policy_version ||--o{ sss_bracket : "defines"
    contribution_policy_version ||--o| philhealth_rate : "defines"
    contribution_policy_version ||--o| pagibig_rate : "defines"

    payroll_period ||--o{ payroll_run : "has"
    branch ||--o{ payroll_run : "for"
    payroll_policy_version ||--o{ payroll_run : "governs"
    payroll_run ||--o{ payroll : "contains"
    payroll_period ||--o{ payroll : "belongs to"
    employee ||--o{ payroll : "paid via"
    salary ||--o{ payroll : "snapshotted in"
    payroll ||--o{ payroll_earnings : "has"
    payroll ||--o{ deduction : "has"
    payroll ||--o| payslip : "generates"
    payroll ||--o{ contribution_record : "records"
    deduction ||--o| contribution_record : "backs"
    contribution_policy_version ||--o{ contribution_record : "used by"

    employee ||--o{ bank_details : "has"
    payroll_period ||--o| disbursement_batch : "results in"
    disbursement_batch ||--o{ deposit_slip : "contains"
    payroll ||--o| deposit_slip : "disbursed via"
    bank_details ||--o{ deposit_slip : "targets"

    cash_advance_history ||--o{ cash_advance_repayment : "repaid via"
    payroll ||--o{ cash_advance_repayment : "deducted from"

    employee ||--o{ employee_hr_notice : "receives"
```

## Assumptions / Notes

- `employee_hr_notice` is from migration `20260913000019`; fields reflect actual migration columns.
- `job_position` table is seeded and used for UI dropdowns; no FK to `employee.position` (position is stored as a string on `employee`).
- The `sessions` table has no FK to `users` — it uses PHP's `session_id` as the key.
- Geography tables (`province`, `city`, `barangay`) have no hierarchy FK in the migrations — all three IDs are flat on `address`.
- `password_reset_challenge` is from migration `20260921000021`.
- `attendance_policy_flag` reviewed_by references `users` but the FK is not enforced in the migration (nullable reviewer).
