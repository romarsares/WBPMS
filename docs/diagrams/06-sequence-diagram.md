# Sequence Diagrams

Derived from `AuthService.php`, `PayrollService.php`, `routes/web.php`,
controller action implementations, and the session/middleware stack.

---

## Sequence 1 — User Login

```mermaid
sequenceDiagram
    actor User
    participant Browser
    participant Router as Router\n(Http/Routing/Router)
    participant CSRF as CsrfMiddleware
    participant Auth as AuthMiddleware
    participant AC as AuthController
    participant AS as AuthService
    participant DSH as DatabaseSessionHandler
    participant DB as MySQL

    User->>Browser: Navigate to /login
    Browser->>Router: GET /login
    Router->>AC: showLogin()
    AC->>Browser: Render login form with CSRF token

    User->>Browser: Submit email + password
    Browser->>Router: POST /login (email, password, _csrf_token)
    Router->>CSRF: verify(_csrf_token)
    CSRF->>DB: Read session data
    DB-->>CSRF: Session payload
    alt CSRF invalid
        CSRF-->>Browser: 403 Forbidden
    else CSRF valid
        Router->>AC: login()
        AC->>AS: attemptLogin(email, password, requestId, ip, userAgent)
        AS->>DB: SELECT users JOIN role WHERE account_email = ?
        DB-->>AS: User row + role_name
        alt User not found or Inactive/Archived
            AS->>DB: INSERT audit_logs (login_failure)
            AS-->>AC: null
            AC-->>Browser: Render login with error
        else User found and Active
            AS->>AS: password_verify(password, hash)
            alt Password mismatch
                AS->>DB: INSERT audit_logs (login_failure)
                AS-->>AC: null
                AC-->>Browser: Render login with error
            else Password correct
                AS->>DB: INSERT audit_logs (login_success)
                AS-->>AC: {user_id, username, role_name, employee_id, requires_password_change}
                AC->>DSH: Write session data to MySQL sessions table
                DSH->>DB: INSERT/UPDATE sessions (session_id, expires_at, data)
                alt requires_password_change = true
                    AC-->>Browser: 302 Redirect → /change-password
                else Normal login
                    AC-->>Browser: 302 Redirect → /{role}/dashboard
                end
            end
        end
    end
```

---

## Sequence 2 — Password Reset (Forgot Password)

```mermaid
sequenceDiagram
    actor User
    participant Browser
    participant AC as AuthController
    participant PRS as PasswordResetService
    participant Mailer
    participant DB as MySQL
    participant SMTP as SMTP Server

    User->>Browser: GET /forgot-password
    Browser->>AC: showForgotPassword()
    AC-->>Browser: Render forgot-password form

    User->>Browser: Submit email address
    Browser->>AC: POST /forgot-password (email)
    AC->>PRS: sendResetLink(email)
    PRS->>DB: SELECT users WHERE account_email = ?
    DB-->>PRS: User row (or null)
    Note over PRS: Always returns success to\nprevent email enumeration
    alt User exists
        PRS->>PRS: Generate OTP, hash it
        PRS->>DB: INSERT password_reset_challenge\n(otp_hash, expires_at, attempts_remaining)
        PRS->>Mailer: sendPasswordResetEmail(email, otp)
        Mailer->>SMTP: SMTP send
        SMTP-->>Mailer: Delivered
    end
    AC-->>Browser: 302 Redirect → /forgot-password/sent

    User->>Browser: GET /reset-password?token=OTP&email=...
    Browser->>AC: showResetPassword(token, email)
    AC-->>Browser: Render reset-password form

    User->>Browser: Submit new password + OTP
    Browser->>AC: POST /reset-password (email, otp, password, password_confirmation)
    AC->>PRS: verifyOtpAndReset(email, otp, newPassword)
    PRS->>DB: SELECT challenge WHERE user_id = ? AND consumed_at IS NULL\nAND expires_at > NOW() AND attempts_remaining > 0
    DB-->>PRS: Challenge row
    alt Challenge expired or used
        PRS-->>AC: Throw exception
        AC-->>Browser: Show error
    else Valid challenge
        PRS->>PRS: hash_verify(otp, otp_hash)
        alt OTP mismatch
            PRS->>DB: UPDATE attempts_remaining - 1
            PRS-->>AC: Throw exception
            AC-->>Browser: Show error
        else OTP correct
            PRS->>DB: UPDATE users SET password_hash = ? WHERE user_id = ?
            PRS->>DB: UPDATE password_reset_challenge SET consumed_at = NOW()
            PRS-->>AC: Success
            AC-->>Browser: 302 Redirect → /login with flash message
        end
    end
```

---

## Sequence 3 — Payroll Run Submission & Owner Approval

```mermaid
sequenceDiagram
    actor HR as HR Head
    actor Owner as Business Owner
    participant Browser
    participant AuthMW as AuthMiddleware
    participant PC as PayrollController
    participant PS as PayrollService
    participant AS_att as AttendanceService
    participant RS as RequestService
    participant CE as ContributionEngine
    participant DB as MySQL

    %% Create Run
    HR->>Browser: POST /hr/payroll (period_id, branch_id)
    Browser->>AuthMW: Check role = HRHead
    AuthMW->>DB: SELECT session data
    DB-->>AuthMW: Session valid
    AuthMW->>PC: store()
    PC->>PS: createPayrollRun(period_id, branch_id, userId)
    PS->>DB: INSERT payroll_run (status=Draft)
    DB-->>PS: payroll_run_id
    PS-->>PC: payroll_run_id
    PC-->>Browser: 302 Redirect → /hr/payroll/{id}

    %% Compute
    HR->>Browser: POST /hr/payroll/{id}/compute
    Browser->>PC: compute()
    PC->>PS: computeRun(payroll_run_id)
    PS->>DB: SELECT employees via employee_branch_assignment\nfor this branch + period
    DB-->>PS: Employee list
    loop For each employee
        PS->>AS_att: getAttendanceSummary(employee_id, period)
        AS_att->>DB: SELECT attendance WHERE employee_id AND period
        DB-->>AS_att: Attendance rows
        AS_att-->>PS: {worked_days, late_minutes, undertime_minutes, overtime_minutes}
        PS->>RS: getApprovedRequests(employee_id, period)
        RS->>DB: SELECT request WHERE status=Approved AND period
        DB-->>RS: Approved requests
        RS-->>PS: {paid_leave_days, overtime_requests, cash_advance_amount}
        PS->>DB: SELECT salary WHERE employee_id AND effective
        DB-->>PS: daily_rate
        PS->>PS: computeGross(daily_rate, worked, leave, overtime, holidays)
        PS->>PS: computeDeductions(late, undertime, cash_advance)
        PS->>CE: isContributionDeductionRun(pay_date)
        CE-->>PS: true/false
        opt Contribution run
            PS->>CE: computeSSS(eemr)
            PS->>CE: computePhilHealth(eemr)
            PS->>CE: computePagIbig(policy)
            CE->>DB: SELECT sss_bracket, philhealth_rate, pagibig_rate
            DB-->>CE: Rates
            CE-->>PS: {employee_share, employer_share} ×3
        end
        PS->>DB: INSERT payroll, payroll_earnings,\ndeduction, contribution_record, payslip
    end
    PS->>DB: UPDATE payroll_run SET status=Computed
    PS-->>PC: Summary
    PC-->>Browser: Render payroll run detail

    %% Submit
    HR->>Browser: POST /hr/payroll/{id}/submit
    Browser->>PC: submit()
    PC->>PS: submitForApproval(payroll_run_id)
    PS->>DB: UPDATE payroll_run SET status=PendingOwnerApproval
    PC-->>Browser: 302 Redirect

    %% Owner Review
    Owner->>Browser: GET /owner/payroll/{id}/review
    Browser->>AuthMW: Check role = BusinessOwner
    AuthMW-->>Browser: Allowed
    Browser->>PC: reviewForm() via OwnerController
    PC->>DB: SELECT payroll_run JOIN payroll JOIN payslip
    DB-->>PC: Run details
    PC-->>Browser: Render review page

    %% Owner Approve
    Owner->>Browser: POST /owner/payroll/{id}/approve
    Browser->>PC: approve() via OwnerController
    PC->>PS: ownerReview(payroll_run_id, Approved, note)
    PS->>DB: UPDATE payroll_run SET status=Approved,\nreviewed_by, reviewed_at
    DB-->>PS: OK
    PS-->>PC: OK
    PC-->>Browser: 302 Redirect with success flash
```

---

## Sequence 4 — Employee Submits Leave Request

```mermaid
sequenceDiagram
    actor Emp as Employee
    participant Browser
    participant AuthMW as AuthMiddleware
    participant EPC as EmployeePortalController
    participant ReqS as RequestService
    participant DB as MySQL

    Emp->>Browser: GET /employee/requests/new
    Browser->>AuthMW: Check role = Employee
    AuthMW->>DB: SELECT session
    DB-->>AuthMW: employee_id from session
    AuthMW->>EPC: requestForm()
    EPC->>DB: SELECT request_type WHERE status=Active
    DB-->>EPC: [Leave, Overtime, CashAdvance]
    EPC-->>Browser: Render request form

    Emp->>Browser: POST /employee/requests (type=leave, dates, reason)
    Browser->>EPC: storeRequest()
    EPC->>ReqS: submit(employee_id, Leave, payload)
    ReqS->>DB: SELECT leave_entitlement + leave_ledger\nWHERE employee_id AND leave_year = YEAR(NOW())
    DB-->>ReqS: current_balance
    alt Insufficient balance
        ReqS-->>EPC: throw: Insufficient leave balance
        EPC-->>Browser: 422 with error message
    else Sufficient balance
        ReqS->>DB: INSERT request (status=Pending)
        DB-->>ReqS: request_id
        ReqS->>DB: INSERT leave_request_detail\n(request_id, leave_type, start_date, end_date, days)
        DB-->>ReqS: OK
        ReqS-->>EPC: request_id
        EPC-->>Browser: 302 Redirect → /employee/requests with flash
    end
```

## Notes

- `AuthMiddleware` intercepts every protected route before the controller runs.
- `CsrfMiddleware` intercepts all POST/PUT/DELETE requests before `AuthMiddleware`.
- The `DatabaseSessionHandler` implements PHP's `SessionHandlerInterface` and stores sessions in MySQL `sessions` table with `expires_at` Unix timestamp.
- `PasswordResetService` never reveals whether an email exists (always redirects to `/forgot-password/sent`).
- All DB writes in `PayrollService::computeRun()` happen inside a single PDO transaction; any exception triggers a full rollback.
