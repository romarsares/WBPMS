# Requirements Document

## Introduction

The Web-Based Payroll Management System automates payroll and attendance
management for Light Diamond Enterprises, a multi-branch business. It
replaces a manual, Excel-based payroll process by integrating biometric
attendance data, automating salary/deduction/contribution computation, and
providing role-based self-service for Business Owner, HR Head, and Employee
users. This document specifies functional requirements as user stories with
EARS-format acceptance criteria, and closes with system-wide non-functional
requirements. Original requirement identifiers from the source capstone
documentation (`REQ001`–`REQ082`, `REQN001`–`REQN014`) are preserved in
brackets for traceability.

Operational acceptance criteria labeled “Supplemental HR answer” and
“Final Defense Reviewer” are sourced from
[`Follow-up-Questions-with-Answers-from-HR-1.pdf`](../../../docs/capstone_files/Follow-up-Questions-with-Answers-from-HR-1.pdf)
and
[`Final-Defense-Reviewer-1.pdf`](../../../docs/capstone_files/Final-Defense-Reviewer-1.pdf),
respectively. These later answers refine the earlier generic requirements.

---

## Requirements

### Requirement 1: Login and Authentication

**User Story:** As a system user (Business Owner, HR Head, or Employee), I
want to log in securely and recover a forgotten password, so that I can
access only the features permitted for my role.

#### Acceptance Criteria

1. WHEN a user submits valid credentials THEN the system SHALL authenticate
   the user and redirect to the role-appropriate home/dashboard page.
   [REQ001]
2. IF submitted credentials do not match a stored record THEN the system
   SHALL reject the login and display an error without revealing which
   field was incorrect.
3. WHEN a user selects "Forgot Password" THEN the system SHALL collect the
   account's registered email, send an OTP, and require OTP verification
   before allowing a new password to be set. [REQ002]
4. WHEN a password reset completes THEN the system SHALL redirect the user
   back to the login screen. [REQ002]
5. WHEN a user is authenticated THEN the system SHALL enforce role-based
   access control so Business Owner, HR Head, and Employee each see only
   their permitted modules and actions.
6. WHEN a Business Owner account is created THEN the system SHALL permit
   the account to exist without an employee profile because the Owner does
   not draw an employee salary. [Supplemental HR answer, p. 3]
7. WHEN any account is created THEN the system SHALL store a unique account
   recovery email independently of the optional employee email, store only a
   hashed password/OTP, persist the server session in MySQL, and expire reset
   challenges after use or timeout. [REQ002, REQN011; ADR-0002]

### Requirement 2: Dashboard

**User Story:** As an HR Head or Business Owner, I want a role-based
dashboard, so that I can quickly see payroll status, pending approvals, and
attendance alerts without navigating multiple modules.

#### Acceptance Criteria

1. WHEN the HR Head opens the Dashboard module THEN the system SHALL
   display overview analytics, pending approvals, and payroll summaries.
   [REQ003]
2. WHEN a Business Owner opens the Dashboard THEN the system SHALL display
   payroll status pending Owner review and cross-branch employee/payroll
   summaries.
3. WHEN there are attendance entries missing time-in or time-out THEN the
   system SHALL surface them as dashboard alerts.
4. WHEN the dashboard loads THEN the system SHALL render within 5 seconds
   under normal load. [REQN004]

### Requirement 3: User Management

**User Story:** As the Owner/HR Head, I want to manage system user
accounts, so that access is limited to authorized, correctly-roled people.

#### Acceptance Criteria

1. WHEN an authorized user opens User Management THEN the system SHALL
   display all user accounts sorted in a table. [REQ004]
2. WHEN an authorized user submits a new-user form with valid data THEN the
   system SHALL create the account and assign it a role. [REQ005]
3. WHEN an authorized user edits a user's role or details and confirms THEN
   the system SHALL persist the update and reflect it immediately in the
   table. [REQ006]
4. WHEN an authorized user toggles a user's status THEN the system SHALL
   activate or deactivate that account, blocking login while inactive.
   [REQ007]
5. WHEN an authorized user archives a user account THEN the system SHALL
   remove it from active listings while retaining the record for audit.
   [REQ008]

### Requirement 4: Employee Management

**User Story:** As the HR Head, I want to maintain employee records across
branches, so that payroll and attendance always reference accurate employee
data.

#### Acceptance Criteria

1. WHEN the HR Head opens Employee Management THEN the system SHALL display
   employee records in a table including name, position, branch, and daily
   rate. [REQ009]
2. WHEN the HR Head submits a new employee form with required fields THEN
   the system SHALL create the employee record. [REQ010]
3. WHEN the HR Head edits and saves an employee record THEN the system
   SHALL persist the changes. [REQ011]
4. WHEN the HR Head archives an employee record THEN the system SHALL
   remove it from active lists while retaining it for history. [REQ012]
5. WHEN the HR Head searches by name or ID THEN the system SHALL return
   matching employee records. [REQ013, REQ014]
6. WHEN employee records are requested for selection (e.g., in attendance
   or payroll screens) THEN the system SHALL provide them as a selectable
   list. [REQ015]
7. WHEN the HR Head filters by branch or date range THEN the system SHALL
   return only matching records. [REQ016, REQ017]
8. WHEN employment status is recorded THEN the system SHALL distinguish
   `Regular` from `Contractual`; newly hired contractual employees have a
   six-month evaluation period after which HR records regularization,
   contract renewal, or separation. [Supplemental HR answer, p. 2]
9. WHEN an employee is created THEN the system SHALL maintain one stable
   employee identity and at most one linked login account, regardless of later
   branch transfers.
10. WHEN HR permanently transfers an employee THEN the system SHALL close the
    prior branch assignment and create a non-overlapping effective-dated branch
    assignment without rewriting historical attendance, payroll, or payslips.
11. FOR any calendar date, THE SYSTEM SHALL permit exactly one effective branch
    assignment per employee.
12. WHEN a contractual review occurs THEN the system SHALL preserve its due
    date, outcome (`Regularized`, `Renewed`, or `Separated`), effective date,
    reviewer, notes, and any next review date as history. [ADR-0002]

### Requirement 5: Work Schedule Management

**User Story:** As the HR Head, I want to assign and maintain calendar-based
work schedules, so that attendance can be validated against expected
working days and hours.

#### Acceptance Criteria

1. WHEN the HR Head assigns a work schedule to an employee THEN the system
   SHALL store working days, rest days, and start/end times. [REQ025]
2. WHEN the HR Head updates an existing schedule THEN the system SHALL
   apply the change and preserve its validity period. [REQ026]
3. WHEN the HR Head archives a schedule THEN the system SHALL remove it
   from active use while retaining history. [REQ027]
4. WHEN the HR Head searches schedules THEN the system SHALL return
   matching results. [REQ028]
5. WHEN the HR Head views schedules THEN the system SHALL present them in
   a calendar-based view, including designated holidays. [REQ029, REQ030]
6. WHEN a schedule type is being assigned THEN the system SHALL present a
   selectable list of schedule types. [REQ031]
7. WHEN the standard weekly schedule is configured THEN Saturday SHALL be
   represented as the rest day and the normal payroll week SHALL contain
   six working days from Friday through Thursday. Individual shifts may be
   07:00–16:00 or 08:00–17:00. [Supplemental HR answer, p. 2]
8. FOR any calendar instant, THE SYSTEM SHALL permit at most one effective
   work schedule per employee; effective periods SHALL be preserved rather
   than overwritten. [REQ026; ADR-0002]

### Requirement 6: Attendance Management

**User Story:** As the HR Head, I want to upload the biometric device's
exported monthly `.xls` daily-log workbook and have the system generate a timesheet from it,
so that payroll is computed from accurate time records without needing a
live connection to the device.

#### Acceptance Criteria

1. WHEN the HR Head uploads an `.xls` workbook matching the approved daily-log
   contract THEN the system SHALL parse `Dept`, `User ID`, `Name`, `Enroll ID`,
   date columns labeled `MM/DD ddd`, and every `HH:mm` token in a date cell.
   [REQ018, REQ019 — revised from live sync to workbook upload; ADR-0001]
2. IF the uploaded file is not a valid/recognized `.xls` workbook or its
   required headers/date/time tokens are invalid THEN the
   system SHALL reject the upload and display a clear error without
   partially importing records.
3. WHEN an `.xls` workbook is uploaded THEN the HR Head SHALL identify its registered
   source biometric device, and the system SHALL derive the device's attendance
   site and configured branch coverage rather than require a separate upload
   for each served branch.
4. WHEN an `.xls` workbook is successfully parsed THEN the system SHALL match
   each punch using the selected source device, `Enroll ID`, and punch timestamp
   against an effective biometric enrollment, then resolve the employee's
   branch assignment effective at that timestamp. `Dept` and `Name` SHALL be
   retained as evidence but SHALL NOT override configured identity or branch
   master data.
5. IF a punch record's device employee identifier does not match any
   known employee THEN the system SHALL flag that record as unmatched
   for HR review rather than silently discarding or misassigning it.
6. WHEN the HR Head selects an employee THEN the system SHALL display
   that employee's generated timesheet in a table (time-in, time-out,
   total hours). [REQ018, REQ019]
7. WHEN attendance records are computed THEN the system SHALL calculate
   total hours worked, late minutes, undertime, and overtime based on the
   employee's assigned schedule. [REQ020, REQ021]
8. WHEN a generated timesheet entry is missing a time-in or time-out THEN
   the system SHALL flag it for HR review rather than silently excluding
   it from computation. [REQ022]
9. WHEN the HR Head manually adjusts a timesheet entry THEN the system
   SHALL save the adjustment and record who made it and why. [REQ023]
10. IF a re-import has the same SHA-256 file checksum, or contains the same
    source device, enrollment code, and local timestamp, THEN the system SHALL
    skip or flag it rather than creating a second raw punch. [REQ024]
11. WHEN an `.xls` import completes THEN the system SHALL show an import
    summary (tokens parsed, matched, unmatched, duplicates skipped, incomplete
    days, and multi-punch days), grouped by resolved employee branch where
    applicable, to the HR Head.
12. WHEN one device is configured to serve multiple branches THEN a single
    valid import SHALL be able to produce attendance for employees in any of
    those branches; the device SHALL identify punch location, while the
    employee's effective assignment SHALL determine organizational branch.
13. IF the resolved employee branch is outside the source device's effective
    branch coverage THEN the system SHALL retain and flag the punch for HR
    review rather than discard or automatically post it to payroll.
14. WHEN an historical file is uploaded after an employee transfer THEN the
    system SHALL use the punch timestamp—not upload time or current branch—to
    resolve the historical enrollment and branch assignment.
15. WHEN a date cell contains one punch THEN the system SHALL preserve it and
    flag the day as incomplete; WHEN it contains two punches THEN the system
    SHALL use the earlier as time-in and later as time-out; WHEN it contains
    more than two punches THEN the system SHALL preserve every punch and flag
    the day for HR review rather than silently dropping intermediate punches.
16. WHEN Thursday attendance processing completes THEN the system SHALL
    close the Friday-through-Thursday attendance period for payroll
    preparation. The first biometric transition period (Sunday through
    Thursday, five working days) is historical data and SHALL NOT become a
    recurring payroll-calendar rule. [Supplemental HR answer, p. 3]
17. WHEN an employee has three consecutive late arrivals THEN the system
    SHALL flag the employee for an HR memorandum; WHEN three such memoranda
    have been recorded THEN the system SHALL flag the employee for HR review
    of a one-week suspension. The system SHALL NOT impose discipline
    automatically. [Supplemental HR answer, p. 1]
18. WHEN an employee has not reported for two weeks, or has three
    consecutive absences without notice and without an acceptable reason,
    THEN the system SHALL flag the employee for HR review under the
    company's AWOL/termination policy. The system SHALL NOT terminate an
    employee automatically. [Supplemental HR answer, p. 1]
19. WHEN HR uploads a workbook THEN HR SHALL provide the workbook year and
    month; the parser SHALL verify that every `MM/DD ddd` header agrees with
    that selection before creating a timestamp. [ADR-0002]
20. FOR a given employee and attendance date, THE SYSTEM SHALL store at most
    one generated attendance row and SHALL link every raw punch used as
    evidence to that row, including intermediate punches. [REQ024; ADR-0002]

### Requirement 7: Request Management (Leave, Overtime, Cash Advance)

**User Story:** As an Employee, I want to submit leave, overtime, and cash
advance requests and track their status; as the HR Head, I want to review,
approve, or reject them, so that all requests are centrally tracked and
auditable.

#### Acceptance Criteria

1. WHEN an employee submits a leave, overtime, or cash advance request
   THEN the system SHALL record it with status `Pending`. [REQ076,
   REQ077, REQ079, REQ080]
2. IF an employee's remaining paid sick leave balance is insufficient for a
   requested leave THEN the system SHALL block submission and explain why.
   [REQ078]
3. WHEN an employee updates or cancels a pending request THEN the system
   SHALL apply the change only while status is `Pending`.
4. WHEN the HR Head views requests THEN the system SHALL display and allow
   sorting/filtering by type, employee, and status. [REQ032, REQ036,
   REQ037, REQ041, REQ042, REQ046]
5. WHEN the HR Head approves or rejects a request THEN the system SHALL
   update its status to `Approved` or `Rejected` and make that status
   visible to the submitting employee. [REQ033, REQ038, REQ043]
6. WHEN the HR Head archives a resolved request THEN the system SHALL
   remove it from active queues while retaining it for reporting. [REQ034,
   REQ039, REQ044]
7. WHEN the HR Head searches requests by employee THEN the system SHALL
   return matching records. [REQ035, REQ040, REQ045]
8. WHEN a request is stored THEN the system SHALL keep exactly one detail row
   matching its type: leave dates/days, overtime date/time/minutes, or cash-
   advance amount. Archival metadata SHALL remain separate from its decision
   status. [REQ032–REQ046; ADR-0002]
9. WHEN leave entitlement changes or approved sick leave is consumed THEN the
   system SHALL append a leave-ledger entry; the current balance SHALL be
   derived from that ledger. [REQ078; ADR-0002]
10. WHEN a cash advance is approved THEN the system SHALL create exactly one
    request-linked obligation; each later payroll repayment SHALL create a
    separate repayment row linked to the exact deduction. [ADR-0001, ADR-0002]

### Requirement 8: Manage Salary

**User Story:** As the HR Head, I want to define and update salary
structures and daily rates, so that payroll computation always uses
current, auditable pay configuration.

#### Acceptance Criteria

1. WHEN the HR Head views an employee's salary configuration THEN the
   system SHALL display the current daily wage rate. [REQ053]
2. WHEN the HR Head creates a new salary structure THEN the system SHALL
   store it and make it available for payroll computation. [REQ054]
3. WHEN the HR Head updates a daily wage rate THEN the system SHALL apply
   the new rate to future payroll runs while preserving the prior rate in
   history. [REQ055, REQ056]
4. WHEN the HR Head archives an outdated salary configuration THEN the
   system SHALL exclude it from active use while keeping it queryable.
   [REQ057]
5. FOR any effective date, THE SYSTEM SHALL resolve exactly one salary rate
   per employee and reject overlapping salary periods. [ADR-0002]

### Requirement 9: Benefits and Deductions (Government Contributions)

**User Story:** As the HR Head, I want SSS, PhilHealth, and Pag-IBIG
contributions computed automatically from current brackets, so that payroll
deductions are accurate and compliant.

#### Acceptance Criteria

1. WHEN payroll is computed for an employee THEN the system SHALL compute
   SSS contribution using the salary-bracket table in effect. [REQ061,
   REQ062, REQ063 context]
2. WHEN payroll is computed THEN the system SHALL compute PhilHealth and
   Pag-IBIG contributions per current policy rates. [REQ063, REQ064]
3. WHEN the HR Head views contribution records THEN the system SHALL
   display all employee contribution records. [REQ058]
4. WHEN the HR Head edits a contribution record THEN the system SHALL
   persist the change, unless the record is locked. [REQ059, REQ060]
5. WHEN the HR Head adds or edits an SSS salary bracket THEN the system
   SHALL apply it to subsequent contribution computations. [REQ061,
   REQ062]
6. WHEN SSS or PhilHealth is computed THEN the system SHALL use the
   employee's Estimated Equivalent Monthly Rate (EEMR), treated as Monthly
   Basic Salary, calculated as `(daily_rate × 313) ÷ 12`, rather than the
   employee's variable earnings for that weekly period. [Final Defense
   Reviewer, p. 4]
7. WHEN a weekly payroll date is not the last Friday of the month THEN the
   system SHALL compute the payroll without employee SSS, PhilHealth, or
   Pag-IBIG deductions; WHEN it is the last Friday THEN the system SHALL
   deduct that month's employee shares and record the corresponding
   employer shares. [Final Defense Reviewer, p. 4]
8. WHEN government contributions are posted THEN employee shares SHALL be
   represented as payroll deductions, not as benefits. [Final Defense
   Reviewer DFD Level 0, p. 3]
9. UNTIL complete effective-dated statutory tables are approved THEN the MVP
   SHALL calculate only the ADR-0001 demo fixture and clearly label it
   non-production; an unsupported EEMR/policy combination SHALL fail visibly
   rather than extrapolate a contribution bracket.
10. WHEN a contribution is posted THEN the system SHALL store the exact
    approved policy version, allow at most one row per payroll/program, and
    link the employee share to exactly one payroll deduction row. [ADR-0002]

### Requirement 10: Payroll Processing

**User Story:** As the HR Head, I want payroll computed automatically from
attendance, salary structure, and approved requests, and routed to the
Owner for approval, so that payroll is accurate, auditable, and controlled.

#### Acceptance Criteria

1. WHEN the HR Head triggers payroll computation for a pay period THEN the
   system SHALL calculate gross pay, overtime pay, late/undertime
   deductions, cash-advance deductions, and, only on the last Friday of the
   month, government contributions per employee. [REQ047; Supplemental HR
   answer, p. 3; Final Defense Reviewer, p. 4]
2. WHEN payroll computation completes THEN the system SHALL generate a
   digital payslip per employee in the company's standard format. [REQ048]
3. WHEN the applicable period is reached THEN the system SHALL compute
   13th-month pay from total annual basic salary. [REQ049]
4. WHEN the HR Head finishes preparing a payroll run THEN the system SHALL
   allow submission to the Business Owner for approval. [REQ050]
5. WHEN the Business Owner reviews a submitted payroll THEN the system
   SHALL allow the Owner to approve it or return it to the HR Head for
   revision, with a reason for return. [REQ051]
6. WHILE a payroll run is `Pending Approval` THE SYSTEM SHALL prevent it
   from being marked as paid/final.
7. WHEN payroll computation runs THEN the system SHALL complete within 5
   seconds. [REQN005]
8. WHEN a normal payroll cycle is opened THEN its attendance period SHALL
   run Friday through Thursday, exclude Saturday as the rest day, and pay
   based on six scheduled working days. HR closes attendance on Thursday, the Business Owner
   approves or returns payroll Friday morning, and approved funds and
   payslips are released Friday afternoon. [Supplemental HR answer, pp. 2–3]
9. WHEN pay components are computed THEN overtime SHALL use
   `hourly_rate × 1.25 × overtime_hours`, work on a regular holiday SHALL
   pay `daily_rate × 2.00`, and work on a special holiday SHALL pay the
   daily rate plus a 30% premium (`daily_rate × 1.30`); lateness SHALL deduct
   `₱1 × minutes_late`. The treatment of
   overtime worked on a holiday remains a separate policy decision and
   SHALL NOT be inferred by compounding these multipliers. [Final Defense
   Reviewer, pp. 4–5]
10. WHILE an employee's projected annual taxable income remains at or
    below the company's stated ₱250,000 threshold, payroll SHALL record no
    income-tax withholding. The threshold SHALL be configurable rather
    than treated as a permanent exemption for every employee. [Supplemental
    HR answer, p. 1]
11. WHEN selecting payroll recipients THEN the system SHALL include active
    employee profiles only and SHALL exclude a Business Owner account that
    has no employee profile. [Supplemental HR answer, p. 3; canonical model
    inference]
12. WHEN HR previews or generates payroll THEN the system SHALL require a
    payroll period and branch selected through the UI and SHALL create a
    separate branch payroll run with its own totals and approval state.
13. FOR a given payroll period and branch, THE SYSTEM SHALL permit at most one
    payroll run; FOR a given payroll period and employee, THE SYSTEM SHALL
    include that employee in at most one branch payroll run.
14. WHEN selecting employees for a branch payroll run THEN the system SHALL
    use the branch assignment effective on the payroll period's start date.
15. IF an employee permanently transfers during an open payroll period THEN
    the system SHALL place the employee's complete payroll in the branch
    effective on the period start date, without splitting or duplicating the
    employee payroll; the next period SHALL use the new branch.
16. WHEN all branch payroll runs are viewed THEN the system SHALL provide both
    branch totals and a consolidated company total without making branch or
    device counts fixed application constants.
17. WHEN payroll detail is persisted THEN it SHALL snapshot the selected
    salary/daily rate and store each earning/deduction's quantity, unit rate,
    multiplier where applicable, source records, formula evidence, and final
    rounded amount. [REQ047; ADR-0002]
18. THE SYSTEM SHALL store approval state only on the branch `payroll_run`,
    using `Draft`, `Computed`, `PendingOwnerApproval`, `Approved`, or
    `Returned`; employee payroll details SHALL NOT duplicate that state.
19. WHEN a payroll period is created THEN it SHALL start Friday, end the next
    Thursday, and use the immediately following Friday as `pay_date`; invalid
    calendars SHALL be rejected. [Supplemental HR answer, pp. 2–3; ADR-0002]
20. WHEN a payroll run becomes `Approved` THEN its employee payroll rows,
    earning/deduction lines, contribution records, and payslip SHALL be
    immutable. [REQ051; ADR-0002]

### Requirement 11: Reports Management

**User Story:** As the HR Head or Business Owner, I want to generate,
print, and export payroll-related reports, so that I can monitor
operations and satisfy documentation requirements.

#### Acceptance Criteria

1. WHEN a report is requested THEN the system SHALL generate a Payroll
   Report, Attendance Report, Request Report, Government Contributions
   Report, or 13th-Month Pay Report per the selected type and period.
   [REQ065–REQ069]
2. WHEN a report is generated THEN the system SHALL complete generation
   within 5 seconds. [REQN006]
3. WHEN the user requests printing THEN the system SHALL produce a
   printable payroll summary, employee payslip, or transaction slip.
   [REQ070, REQ071, REQ072]
4. WHEN the user prepares weekly salary disbursement THEN the system SHALL
   produce a printable deposit-slip preparation list containing each
   employee's name, personal BDO account number, and exact net-pay amount.
   The system SHALL NOT describe this output as a bank-transfer API/file.
   [REQ073, reframed by Supplemental HR answer, pp. 2–3]
5. WHEN an approved weekly payroll is disbursed THEN the system SHALL
   record one pay-to-cash cheque for the aggregate weekly amount and one
   manually prepared BDO deposit slip per employee; no ATM payroll flow or
   non-BDO bank flow is currently in scope. [Supplemental HR answer,
   pp. 2–3]
6. FOR a payroll period, THE SYSTEM SHALL permit at most one aggregate cheque
   batch, created only after all included branch runs are `Approved`; each
   payroll row SHALL have at most one payslip and one deposit slip. [ADR-0002]

### Requirement 12: Employee Self-Service Portal

**User Story:** As an Employee, I want to view my own attendance, submit
requests, and access my payslips, so that I don't need to go through HR for
routine information.

#### Acceptance Criteria

1. WHEN an employee logs in THEN the system SHALL display an employee
   dashboard. [REQ074]
2. WHEN an employee opens Attendance THEN the system SHALL display only
   that employee's own attendance records in table format. [REQ075]
3. WHEN an employee opens a payslip THEN the system SHALL display its full
   details (earnings, deductions, contributions, net pay). [REQ081]
4. WHEN an employee requests a payslip download THEN the system SHALL
   provide it as a downloadable file. [REQ082]
5. IF an employee attempts to access another employee's records THEN the
   system SHALL deny access.

---

## Non-Functional Requirements

### Operational
1. THE SYSTEM SHALL operate on Windows 10 or later for server/admin
   workstations. [REQN001]
2. THE SYSTEM SHALL be accessible from Chrome, Firefox, and Edge. [REQN002]

### Performance
3. WHEN data is requested over a stable connection THEN THE SYSTEM SHALL
   return it within 1 second. [REQN003]
4. WHEN the dashboard is opened THEN THE SYSTEM SHALL load within 5
   seconds. [REQN004]
5. WHEN payroll is computed THEN THE SYSTEM SHALL complete within 5
   seconds. [REQN005]
6. WHEN a report is generated THEN THE SYSTEM SHALL complete within 5
   seconds. [REQN006]

### Security
7. THE SYSTEM SHALL restrict all access to authenticated, authorized
   users. [REQN007]
8. ONLY the HR Head role SHALL be permitted to manage payroll. [REQN008]
9. ONLY the HR Head role SHALL be permitted to approve leave requests.
   [REQN009]
10. ONLY the HR Head role SHALL be permitted to approve cash advances.
    [REQN010]
11. THE SYSTEM SHALL require secure login authentication (hashed
    passwords, session/token-based auth). [REQN011]
11a. WHEN an unauthenticated action such as a failed login is audited THEN the
    audit record SHALL allow a null user reference while retaining the event
    type, attempted identifier, request ID, IP address, and user agent.
    [ADR-0002]

### Localization / Formatting
12. THE SYSTEM SHALL use English as the primary language. [REQN012]
13. THE SYSTEM SHALL display dates in `MM/DD/YY` format. [REQN013]
14. THE SYSTEM SHALL display time in 24-hour format. [REQN014]
