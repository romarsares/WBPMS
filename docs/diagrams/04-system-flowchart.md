# System Flowchart

Covers the five core operational workflows of WBPMS. Derived from
controller actions, service method logic, and route definitions.

---

## 1. Login & Authentication Flow

```mermaid
flowchart TD
    A([Start: User visits /login]) --> B[Enter email + password]
    B --> C{CSRF token valid?}
    C -- No --> ERR1[/403 Forbidden/]
    C -- Yes --> D{Account exists\n& status = Active?}
    D -- No --> E[Log failed attempt to audit_logs]
    E --> F[/Show error: Invalid credentials/]
    F --> B
    D -- Yes --> G{password_verify\nmatches hash?}
    G -- No --> E
    G -- Yes --> H[Create MySQL session\nDatabaseSessionHandler]
    H --> I{requires_password_change\n= true?}
    I -- Yes --> J[Redirect → /change-password]
    J --> K[User sets new password]
    K --> L[Clear requires_password_change flag]
    L --> M{Role?}
    I -- No --> M
    M -- BusinessOwner --> N[/Redirect → /owner/dashboard/]
    M -- HRHead --> O[/Redirect → /hr/dashboard/]
    M -- Employee --> P[/Redirect → /employee/dashboard/]
```

---

## 2. Attendance Import Flow

```mermaid
flowchart TD
    A([HR Head: Upload .xls file\nPOST /hr/attendance/import]) --> B{File extension\n= .xls?}
    B -- No --> ERR1[/Reject: Invalid file type/]
    B -- Yes --> C{OLE BIFF signature\nvalid?}
    C -- No --> ERR2[/Reject: INVALID_OLE_SIGNATURE/]
    C -- Yes --> D{SHA-256 checksum\nalready imported?}
    D -- Yes --> ERR3[/Reject: Duplicate import/]
    D -- No --> E[Save temp file to\nstorage/private/]
    E --> F[LdeXlsDailyLogParser::parse\nLDE_XLS_DAILY_LOG_V1]
    F --> G{Workbook structure\nvalid? headers, dates,\nno formulas}
    G -- No --> ERR4[/Reject with error code\nclean up temp file/]
    G -- Yes --> H[Extract all HH:mm punches\nper employee per day]
    H --> I[Create attendance_import_batch\nstatus=Staged]
    I --> J[For each punch:\nmatch device_employee_code\nvs employee_biometric_enrollment]
    J --> K{Match found?}
    K -- No --> L[Retain in biometric_punch\nmatch_status=unmatched]
    K -- Yes --> M{Employee branch\nin device coverage?}
    M -- No --> N[Retain with\nmatch_status=coverage_exception]
    M -- Yes --> O{Duplicate\npunch?}
    O -- Yes --> P[Skip, increment\nduplicates_skipped]
    O -- No --> Q{Punches per day?}
    Q -- 1 punch --> R[Mark incomplete\nno time_in or time_out]
    Q -- 2 punches --> S[earliest = time_in\nlatest = time_out]
    Q -- 3+ punches --> T[Keep all, flag multi_punch\nfor HR review]
    R --> U[Save to attendance table\nsource=xls_import]
    S --> U
    T --> U
    L --> V[Show import summary]
    N --> V
    P --> V
    U --> V
    V --> W[HR reviews summary\nGET /hr/attendance/import]
    W --> X{HR approves?}
    X -- No --> Y[POST /hr/attendance/import/id/cancel\nbatch status=Cancelled]
    X -- Yes --> Z[POST /hr/attendance/import/id/approve\nbatch status=Completed]
    Z --> AA[Delete temp file\nfrom storage/private/]
    Z --> AB([End: Attendance records available])
```

---

## 3. Request Submission & Approval Flow

```mermaid
flowchart TD
    A([Employee: Submit request\nPOST /employee/requests]) --> B{Request type?}
    B -- Leave --> C{Leave balance\nsufficient?}
    C -- No --> ERR1[/Reject: Insufficient balance/]
    C -- Yes --> D[Create request row\nstatus=Pending\n+ leave_request_detail]
    B -- Overtime --> E[Create request row\nstatus=Pending\n+ overtime_request_detail]
    B -- Cash Advance --> F{Validate amount > 0?}
    F -- No --> ERR2[/Reject: Invalid amount/]
    F -- Yes --> G[Create request row\nstatus=Pending\n+ cash_advance_request_detail]
    D --> H[/Request visible to HR & Owner/]
    E --> H
    G --> H

    H --> I{Reviewer action?}
    I -- HR Head Approves --> J[POST /hr/requests/id/approve\nstatus=Approved]
    I -- HR Head Cancels --> K[POST /hr/requests/id/cancel\nstatus=Cancelled]
    I -- Owner Approves --> L[POST /owner/requests/id/approve\nstatus=Approved]
    I -- Owner Cancels --> M[POST /owner/requests/id/cancel\nstatus=Cancelled]

    J --> N{Is Leave request?}
    N -- Yes --> O[Append leave_ledger entry\ndays_delta = negative]
    N -- No --> P{Is Cash Advance?}
    P -- Yes --> Q[Create cash_advance_history\nobligation row]
    P -- No --> R([End: Request resolved])
    O --> R
    Q --> R
    K --> R
    L --> R
    M --> R

    R -.->|Employee can cancel\nif still Pending| S[POST /employee/requests/id/cancel\nstatus=Cancelled]
```

---

## 4. Payroll Computation & Approval Flow

```mermaid
flowchart TD
    A([HR Head: Create payroll run\nPOST /hr/payroll]) --> B[Select payroll_period\n+ branch\nstatus=Draft]
    B --> C[POST /hr/payroll/id/compute]
    C --> D[Pull eligible employees\nvia employee_branch_assignment\neffective at period_start]
    D --> E[For each employee:]
    E --> F[Load attendance summary\nfor the period]
    F --> G[Load approved overtime\n& paid leave requests]
    G --> H[Load current salary\ndaily_rate from salary table]
    H --> I[Compute gross pay\nbase + overtime×1.25\n+ regular holiday work×2.00\n+ special holiday work×1.30]
    I --> J[Compute deductions:\nlate_minutes × ₱1/min\nundertime × rate/standard_min\ncash advance installment]
    J --> K{Is last Friday\nof the month?}
    K -- Yes --> L[Compute SSS, PhilHealth,\nPag-IBIG via ContributionEngine\nEEMR = daily_rate×313÷12]
    K -- No --> M[Skip government contributions]
    L --> N[Write payroll_earnings\ndeduction\ncontribution_record rows]
    M --> N
    N --> O[net_pay = gross - deductions\n- government shares]
    O --> P[Generate payslip row]
    P --> Q[payroll_run status = Computed]
    Q --> R[HR reviews payroll run]
    R --> S[POST /hr/payroll/id/submit\nstatus = PendingOwnerApproval]
    S --> T[Owner reviews\nGET /owner/payroll/id/review]
    T --> U{Owner decision?}
    U -- Approve --> V[POST /owner/payroll/id/approve\nstatus = Approved]
    U -- Return --> W[POST /owner/payroll/id/return\nstatus = Returned\nwith return_reason]
    W --> X[HR revises, re-computes]
    X --> C
    V --> Y[Payslips locked\nDisbursement prepared]
    Y --> Z([End: Payroll cycle complete])
```

---

## 5. Employee Self-Service Flow

```mermaid
flowchart TD
    A([Employee logs in]) --> B[/Employee Dashboard/]
    B --> C{What does employee want?}

    C -- View Attendance --> D[GET /employee/attendance]
    D --> E[Load attendance records\nscoped to session.employee_id]
    E --> F[/Display timesheet rows/]

    C -- View Payslip --> G[GET /employee/payslips]
    G --> H[List approved payslips]
    H --> I{Download?}
    I -- View --> J[GET /employee/payslips/id]
    I -- Download PDF --> K[GET /employee/payslips/id/download\nDompdf renders HTML → PDF]

    C -- Submit Request --> L[GET /employee/requests/new]
    L --> M[Fill request form]
    M --> N[POST /employee/requests]
    N --> O{Leave type?}
    O -- Yes --> P{Balance check}
    P -- Insufficient --> ERR1[/Error: No leave balance/]
    P -- Sufficient --> Q[Save request\nstatus=Pending]
    O -- No --> Q
    Q --> R[/Request submitted/]

    C -- View Notices --> S[GET /employee/notices]
    S --> T[Load employee_hr_notice\nrecords for this employee]
    T --> U[/Display notices/]
    U --> V{Acknowledge?}
    V -- Yes --> W[POST /employee/notices/id/acknowledge\nacknowledged_at = now]
```

## Notes

- All flow decisions derived from actual controller method logic and service validation rules.
- The payroll state machine `Draft → Computed → PendingOwnerApproval → Approved / Returned` is enforced by `payroll_run.status` in `PayrollService`.
- The attendance import transactional rollback is enforced by `AttendanceImportGateway::transactional()`.
- Government contributions only apply on the last-Friday payroll per `ContributionEngine::isContributionDeductionRun()`.
