<?php
declare(strict_types=1);

/**
 * Generates the WBPMS full regression test suite as an .xlsx workbook.
 * Uses the project's already-installed PhpSpreadsheet (composer dep).
 *
 * Output: WBPMS_Regression_Test_Suite.xlsx (same folder as this script).
 * Run: php generate_regression_tests.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Borders;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/* ----------------------------------------------------------------------
   TEST CASES
   Each row: [TC ID, Module, Feature/Area, Requirement Ref, Priority,
             Test Type, Title, Preconditions, Steps, Expected, Test Data]
   ---------------------------------------------------------------------- */
$cases = [
    // ===== 1. Login & Authentication =====
    ['AUTH-001','Login & Authentication','Login','AUTH','High','Functional','Valid HR Head login','HR Head account exists and active','1. Open /login\n2. Enter HR Head email\n3. Enter valid password\n4. Submit','Authenticated; redirected to HR dashboard; session established','hr@lde.test / <valid>'],
    ['AUTH-002','Login & Authentication','Login','AUTH','High','Functional','Valid Business Owner login','Owner account exists and active','1. Open /login\n2. Enter Owner email\n3. Enter valid password\n4. Submit','Authenticated; redirected to Owner dashboard','owner@lde.test / <valid>'],
    ['AUTH-003','Login & Authentication','Login','AUTH','High','Functional','Valid Employee login','Employee account linked to an employee record','1. Open /login\n2. Enter Employee email\n3. Enter valid password\n4. Submit','Authenticated; redirected to Employee self-service portal','emp@lde.test / <valid>'],
    ['AUTH-004','Login & Authentication','Login','AUTH','High','Negative','Login with wrong password','Valid email exists','1. Open /login\n2. Enter valid email\n3. Enter wrong password\n4. Submit','Login rejected with error; no session created','<valid email> / wrongpass'],
    ['AUTH-005','Login & Authentication','Login','AUTH','Medium','Negative','Login with non-existent email','Email not registered','1. Open /login\n2. Enter unregistered email\n3. Enter any password\n4. Submit','Login rejected with generic error; no user enumeration','nobody@lde.test / x'],
    ['AUTH-006','Login & Authentication','Login','AUTH','Medium','Functional','Email is the only account credential (no username field)','N/A','1. Open /login\n2. Inspect form fields','Only an email field is present (no separate username field)','—'],
    ['AUTH-007','Login & Authentication','RBAC','AUTH','High','Security','Employee cannot access HR payroll','Logged in as Employee','1. As Employee, navigate to /hr/payroll','Access blocked (redirect or 403); no data exposed','—'],
    ['AUTH-008','Login & Authentication','RBAC','AUTH','High','Security','HR cannot perform final payroll approval','Logged in as HR; a payroll run in Submitted status','1. As HR, attempt to Approve the submitted payroll run','Approve action blocked; only Business Owner can finally approve','—'],
    ['AUTH-009','Login & Authentication','Session','AUTH','High','Security','Idle session timeout (30 min)','Logged in, then idle','1. Log in\n2. Wait 30 minutes with no activity\n3. Perform an action','Session expired; user forced to log in again','—'],
    ['AUTH-010','Login & Authentication','Session','AUTH','Medium','Security','Absolute session expiry (12h)','Logged in','1. Log in\n2. Keep session active beyond 12h absolute window\n3. Perform an action','Session invalidated; re-authentication required','—'],
    ['AUTH-011','Login & Authentication','Logout','AUTH','High','Security','Logout destroys session','Logged in','1. Click Logout\n2. Press browser Back','Session destroyed; Back does not restore authenticated state','—'],
    ['AUTH-012','Login & Authentication','CSRF','AUTH','High','Security','CSRF token required on POST','Any state-changing form','1. Submit a POST form with CSRF token removed','Request rejected (403/419); no state change','—'],
    ['AUTH-013','Login & Authentication','Password Reset','AUTH','Medium','Functional','Password reset link emailed','User with email on file','1. Use "Forgot password"\n2. Submit email\n3. Check email','Reset link emailed; link opens reset form','user@lde.test'],
    ['AUTH-014','Login & Authentication','Password Reset','AUTH','Medium','Security','Reset link is single-use and expires','Reset link already consumed once','1. Reuse a consumed reset link','Second use rejected; no password change','—'],
    ['AUTH-015','Login & Authentication','Credentials','AUTH','High','Security','Passwords stored hashed (not plaintext)','N/A','1. Inspect user record password column','Password stored via password_hash(); plaintext not recoverable','—'],
    ['AUTH-016','Login & Authentication','Session Cookie','AUTH','Medium','Security','Secure cookie attributes','Over HTTPS','1. Inspect session cookie attributes','HttpOnly, Secure, SameSite set appropriately','—'],
    ['AUTH-017','Login & Authentication','CSRF','AUTH','Medium','Security','Invalid CSRF token rejected','Logged in','1. Submit form with tampered CSRF token','Request rejected; no state change','—'],

    // ===== 2. Dashboard =====
    ['DASH-001','Dashboard','HR Dashboard','DASH','High','Functional','HR dashboard summary tiles','At least one employee, request, payroll run, attendance alert','1. Log in as HR\n2. Open dashboard','Tiles show employee count, pending requests, payroll status, attendance alerts','—'],
    ['DASH-002','Dashboard','Owner Dashboard','DASH','High','Functional','Owner dashboard summary','Approved payroll data across branches','1. Log in as Owner\n2. Open dashboard','Shows payroll costs, pending approvals, financial reports across branches','—'],
    ['DASH-003','Dashboard','Employee Dashboard','DASH','High','Functional','Employee dashboard scope','Employee with attendance/requests/payslips','1. Log in as Employee\n2. Open dashboard','Shows own attendance summary, own pending requests, recent payslips','—'],
    ['DASH-004','Dashboard','RBAC','DASH','High','Security','Dashboard numbers respect role scope','Logged in as Employee','1. Compare Employee dashboard counts to HR view','Employee sees only own data; cannot see company-wide counts','—'],
    ['DASH-005','Dashboard','Counts','DASH','Medium','Functional','Counts update after related action','Pending request count visible','1. Note pending requests\n2. Submit a new request\n3. Reload dashboard','Pending request count increments','—'],

    // ===== 3. User Management =====
    ['USER-001','User Management','Create','USER','High','Functional','Create user account','Logged in as HR/Owner','1. Open User Management\n2. Add user (email + role)\n3. Save','User saved; new user can log in','newuser@lde.test / HRHead'],
    ['USER-002','User Management','Roles','USER','High','Functional','Edit user role','User exists','1. Change user role\n2. Save\n3. Log in as that user','Permissions change to match new role','—'],
    ['USER-003','User Management','Activate/Deactivate','USER','High','Functional','Deactivate user','Active user','1. Deactivate user\n2. Attempt login as that user','Deactivated user cannot log in','—'],
    ['USER-004','User Management','Archive','USER','Medium','Functional','Archive user account','Active user','1. Archive user\n2. View active users list','Archived user hidden from active list','—'],
    ['USER-005','User Management','Self-protection','USER','High','Negative','Prevent archiving own account','Logged in as the user to archive','1. Attempt to archive own account','Action blocked; cannot archive self','—'],
    ['USER-006','User Management','UI','USER','Medium','UI','Blank-username fallback','A user with blank username display','1. Open User Management list','Blank username shown with fallback; no error/blank cell','—'],
    ['USER-007','User Management','Validation','USER','High','Negative','Duplicate email rejected','Email already used by another user','1. Create user with existing email','Rejected with validation error','existing@lde.test'],
    ['USER-008','User Management','RBAC','USER','High','Security','Only HR/Owner manage users','Logged in as Employee','1. As Employee, attempt /user management','Access blocked','—'],
    ['USER-009','User Management','Reset','USER','Medium','Functional','Admin-initiated password reset','User exists','1. Trigger admin reset\n2. User logs in with new credential','User receives/uses new credential successfully','—'],

    // ===== 4. Employee Management =====
    ['EMP-001','Employee Management','Create','EMP','High','Functional','Add employee record','Branch exists','1. Open Employee Management\n2. Add employee (name, branch, position)\n3. Save','Employee saved; appears under selected branch','—'],
    ['EMP-002','Employee Management','Edit','EMP','High','Functional','Edit employee details','Employee exists','1. Edit name/contact/position\n2. Save','Changes persisted','—'],
    ['EMP-003','Employee Management','Branch Scope','EMP','High','Functional','Per-branch employee scoping','Employees in multiple branches','1. Filter by branch\n2. View list','Only employees of selected branch shown (or all when "All Branches")','—'],
    ['EMP-004','Employee Management','Archive/Rehire','EMP','High','Functional','Archive on separation is reversible','Active employee','1. Archive employee (separation)\n2. Rehire/unarchive','Archive applied; rehire restores employee with history retained','—'],
    ['EMP-005','Employee Management','Archive/Rehire','EMP','High','Functional','Rehire retains history','Previously archived employee','1. Rehire archived employee\n2. View prior records/history','Historical records intact after rehire','—'],
    ['EMP-006','Employee Management','Validation','EMP','Medium','Negative','Employee number uniqueness/format','Employee number rules','1. Create duplicate/invalid employee number','Rejected with validation error','—'],
    ['EMP-007','Employee Management','Bank Details','EMP','High','Functional','Bank account details saved','Employee exists','1. Set account name, number, type\n2. Save\n3. Generate transaction slip','Bank details persisted and used to fill transaction slip','—'],
    ['EMP-008','Employee Management','Bank Details','EMP','Medium','Negative','No bank account on file','Employee with no bank details','1. Generate transaction slip for employee','Slip prints blank account fields; missing-bank notice shown','—'],
    ['EMP-009','Employee Management','Documents','EMP','Medium','Functional','Employee document upload','Employee exists','1. Upload a document\n2. Inspect storage','File stored under storage/private (not public); retrievable','—'],
    ['EMP-010','Employee Management','RBAC','EMP','High','Security','Employee cannot access employee management','Logged in as Employee','1. As Employee, attempt employee management','Access blocked','—'],

    // ===== 5. Work Schedule =====
    ['SCH-001','Work Schedule','Config','SCH','High','Functional','Sunday–Thursday 5-day schedule','N/A','1. Create schedule Sun–Thu (5 days)\n2. Save','Schedule saved; Sat as rest day','—'],
    ['SCH-002','Work Schedule','Config','SCH','High','Functional','Friday–Thursday 6-day schedule (Sat rest)','N/A','1. Create schedule Fri–Thu (6 days)\n2. Save','Schedule saved; Saturday as rest day','—'],
    ['SCH-003','Work Schedule','Rest Day','SCH','High','Functional','Rest day reflected in attendance','Schedule set','1. Generate timesheet for a Saturday','Saturday treated as rest day in computation','—'],
    ['SCH-004','Work Schedule','Holidays','SCH','High','Functional','Add holiday to calendar','N/A','1. Add holiday (date + type)\n2. Save','Holiday flagged in payroll/timesheet','—'],
    ['SCH-005','Work Schedule','Holiday Pay','SCH','High','Functional','Holiday pay by holiday type','Holiday + schedule set','1. Compute payroll on a holiday','Holiday pay applies correct rate for type (regular/special)','—'],
    ['SCH-006','Work Schedule','Assignment','SCH','Medium','Functional','Assign schedule to employee/branch','Schedule and employee exist','1. Assign schedule\n2. Import attendance','Schedule applied to attendance/timesheet computation','—'],
    ['SCH-007','Work Schedule','Validation','SCH','Medium','Negative','Overlapping schedule warning','Existing schedule','1. Create overlapping schedule','Warned or prevented','—'],
    ['SCH-008','Work Schedule','Config','SCH','Medium','Functional','Working week configurable per branch','Multiple branches','1. Set different working weeks per branch','Each branch honors its configured working week','—'],

    // ===== 6. Attendance =====
    ['ATT-001','Attendance','Import','ATT','High','Functional','Upload valid .xls daily log','Approved schedule; employees matched','1. Upload valid BDO/biometric .xls daily log\n2. Parse\n3. Generate timesheet','Punches preserved; per-employee timesheet generated','valid_daily_log.xls'],
    ['ATT-002','Attendance','Import','ATT','High','Negative','Reject non-.xls / wrong format','N/A','1. Upload a .csv/.xlsx or non-OLE file','Rejected (OLE signature check); no import','wrong.csv'],
    ['ATT-003','Attendance','Import','ATT','High','Negative','Duplicate file blocked (idempotency)','File already imported once','1. Re-upload the same file (same checksum)','Blocked as duplicate; no double import','same_file.xls'],
    ['ATT-004','Attendance','Import','ATT','Medium','Negative','Oversized file rejected','N/A','1. Upload a file exceeding size/resource limit','Rejected with limit error','large.xls'],
    ['ATT-005','Attendance','Import','ATT','Medium','Negative','Structural/formula validation','N/A','1. Upload a malformed .xls (bad structure/formulas)','Rejected with parse error','malformed.xls'],
    ['ATT-006','Attendance','Matching','ATT','High','Functional','Employee matching during import','Employees with biometric IDs','1. Upload log\n2. Review matched punches','Punches linked to correct employees by device ID','—'],
    ['ATT-007','Attendance','Timesheet','ATT','High','Functional','Hours/late/undertime/overtime computed','Schedule + punches imported','1. Generate timesheet','Hours, late, undertime, overtime computed per schedule','—'],
    ['ATT-008','Attendance','Adjustment','ATT','High','Functional','Manual attendance adjustment by HR','Imported attendance','1. As HR, adjust a punch/hours\n2. Save','Adjustment overrides computed value; audit logged','—'],
    ['ATT-009','Attendance','RBAC','ATT','High','Security','Only HR Head adjusts attendance','Logged in as Employee','1. As Employee, attempt attendance adjustment','Blocked; only HR Head may adjust','—'],
    ['ATT-010','Attendance','Import Tx','ATT','High','Integration','Import owns DB transaction (idempotent)','N/A','1. Upload file\n2. Interrupt mid-import (simulate)','Rollback/atomic; no partial/orphan records','—'],
    ['ATT-011','Attendance','Security','ATT','Medium','Security','Uploads use private randomized temp paths','N/A','1. Upload a file\n2. Inspect storage/private','Randomized temp path; file removed after parse/import','—'],
    ['ATT-012','Attendance','Preview','ATT','Medium','Functional','Preview parsed punches before commit','Valid file uploaded','1. Upload\n2. Open preview','Parsed punches listed before final commit','—'],
    ['ATT-013','Attendance','Reports','ATT','Medium','Functional','Attendance frequency report','Attendance data exists','1. Open attendance frequency report\n2. Generate','Report generated with frequency breakdown','—'],
    ['ATT-014','Attendance','Boundary','ATT','Medium','Boundary','Exactly on time = not late','Schedule start T','1. Punch in exactly at T','Late = 0; counted on time','—'],
    ['ATT-015','Attendance','Overtime','ATT','High','Functional','Overtime from approved overtime requests','Approved overtime request exists','1. Compute payroll for period','Overtime hours match approved overtime request','—'],

    // ===== 7. Request Management =====
    ['REQ-001','Request Management','Leave','REQ','High','Functional','Submit leave request','Employee logged in with leave balance','1. New leave request\n2. Submit','Status = Pending','—'],
    ['REQ-002','Request Management','Overtime','REQ','High','Functional','Submit overtime request','Employee logged in','1. New overtime request\n2. Submit','Status = Pending','—'],
    ['REQ-003','Request Management','Cash Advance','REQ','High','Functional','Submit cash advance request','Employee logged in','1. New cash advance request\n2. Submit','Status = Pending','—'],
    ['REQ-004','Request Management','Leave Balance','REQ','High','Negative','Block leave when sick balance insufficient','Employee has 0 sick leave balance','1. Submit sick leave request','Request blocked; insufficient balance (4 paid sick leaves)','—'],
    ['REQ-005','Request Management','Approval','REQ','High','Functional','HR Head approves request','Pending request exists','1. As HR, approve request','Status = Approved','—'],
    ['REQ-006','Request Management','Approval','REQ','High','Functional','Business Owner approves/cancels request','Pending request exists','1. As Owner, approve or cancel request','Status updated (Approved/Cancelled)','—'],
    ['REQ-007','Request Management','Status','REQ','High','Functional','Declined request = Cancelled (no Rejected)','Pending request to be declined','1. Decline a request','Status = Cancelled (no separate Rejected status)','—'],
    ['REQ-008','Request Management','Cancel','REQ','Medium','Functional','Employee cancels own pending request','Own pending request','1. Cancel own pending request','Status = Cancelled','—'],
    ['REQ-009','Request Management','Update','REQ','Medium','Functional','Employee updates own pending request','Own pending request','1. Edit own pending request\n2. Save','Updated; still Pending','—'],
    ['REQ-010','Request Management','Integration','REQ','High','Integration','Approved request affects payroll','Approved leave/overtime/cash advance','1. Compute payroll for the period','Payroll reflects approved leave/overtime/cash advance','—'],
    ['REQ-011','Request Management','Cash Advance','REQ','High','Functional','Cash advance deducted in next payroll','Approved cash advance','1. Run payroll after approval','Cash advance appears as deduction; net reduced','—'],
    ['REQ-012','Request Management','RBAC','REQ','High','Security','Employee cannot approve others requests','Logged in as Employee','1. Attempt to approve another request','Blocked','—'],
    ['REQ-013','Request Management','Validation','REQ','Medium','Negative','Invalid request type rejected','N/A','1. Submit request with invalid type','Rejected with validation error','—'],
    ['REQ-014','Request Management','Filters','REQ','Medium','Functional','Owner request filters','Owner, multiple requests','1. Use owner request filters','Requests filtered (by type/status/branch/employee)','—'],
    ['REQ-015','Request Management','Leave Balance','REQ','High','Functional','Leave balance decrements after approval','Employee with sick leave balance','1. Approve sick leave\n2. Check balance','Balance decremented by approved days','—'],

    // ===== 8. Payroll =====
    ['PAY-001','Payroll','Compute','PAY','High','Functional','Payroll derived from inputs','Approved attendance + salary + requests + contributions','1. Compute payroll for period/branch','Figures derived (not hand-entered) from attendance+salary+requests+contributions','—'],
    ['PAY-002','Payroll','Compute','PAY','High','Negative','Payroll figures not manually editable','Computed payroll','1. Attempt to manually edit a computed figure','Editing not allowed; figures are computed only','—'],
    ['PAY-003','Payroll','Workflow','PAY','High','Functional','HR submits payroll to Owner','Computed payroll ready','1. As HR, submit payroll run','Status = Submitted; routed to Owner','—'],
    ['PAY-004','Payroll','Workflow','PAY','High','Functional','Owner reviews and approves/returns','Submitted payroll','1. As Owner, review\n2. Approve or Return','Owner can approve or return for revision','—'],
    ['PAY-005','Payroll','Workflow','PAY','High','Functional','Owner returns payroll for revision','Submitted payroll','1. As Owner, Return\n2. As HR, recompute','Status = Returned; HR can recompute and resubmit','—'],
    ['PAY-006','Payroll','Workflow','PAY','High','Functional','Owner approval locks run','Returned/resubmitted payroll','1. As Owner, Approve','Status = Approved; run locked','—'],
    ['PAY-007','Payroll','Cancel','REQ047','High','Functional','Cancel payroll run','Approved/submitted run','1. Cancel a payroll run','Cancellation handled; schema/cancellation per REQ047','—'],
    ['PAY-008','Payroll','Immutability','REQ047','High','Security','Approved run immutable','Approved payroll run','1. Attempt to edit approved run','Edit blocked; immutability enforced','—'],
    ['PAY-009','Payroll','Payslip','PAY','High','Functional','Payslip generation','Approved payroll run','1. Generate payslips','Per-employee payslip with earnings, deductions, net','—'],
    ['PAY-010','Payroll','13th Month','PAY','High','Functional','13th-month pay computation','Eligible employee, full-year data','1. Compute 13th-month pay','13th-month computed per rule','—'],
    ['PAY-011','Payroll','Overtime','PAY','High','Functional','Overtime pay from approved overtime','Approved overtime + holiday','1. Compute payroll','Overtime pay per ApprovedOvertimeCalculator/HolidayPayCalculator','—'],
    ['PAY-012','Payroll','Holiday','PAY','High','Functional','Holiday pay on holidays','Payroll period with a holiday','1. Compute payroll','Holiday pay applied for holiday dates','—'],
    ['PAY-013','Payroll','Adjustment','PAY','Medium','Functional','Payroll adjustment service','Computed payroll','1. Apply a payroll adjustment','Adjustment applied and recorded','—'],
    ['PAY-014','Payroll','Multi-branch','PAY','Medium','Functional','Multi-branch run per period','Period with multiple branches','1. Run payroll across branches','Each branch run computed for the period','—'],
    ['PAY-015','Payroll','Disbursement','REQ076','Medium','Functional','Disbursement summary / deposit-slip list export','All branch runs Approved','1. Open disbursement summary\n2. Export CSV','CSV export generated (REQ076)','—'],
    ['PAY-016','Payroll','Net Pay','PAY','High','Functional','Net = gross - deductions','Contributions + other deductions set','1. Compute payroll','Net pay = gross − (SSS + PhilHealth + Pag-IBIG + other deductions)','—'],

    // ===== 9. Manage Salary =====
    ['SAL-001','Manage Salary','Structure','SAL','High','Functional','Create salary structure (daily rate)','Employee exists','1. Set daily rate\n2. Save','Salary structure saved','—'],
    ['SAL-002','Manage Salary','Edit','SAL','High','Functional','Edit daily rate','Existing salary','1. Change daily rate\n2. Save','New rate applies going forward','—'],
    ['SAL-003','Manage Salary','History','SAL','High','Functional','Salary history retained','Employee with rate changes','1. View salary history\n2. Inspect prior payslips','Past payslips keep old rate; history retained','—'],
    ['SAL-004','Manage Salary','Compute','SAL','High','Functional','Daily rate to period pay','Daily rate set','1. Compute payroll','Period pay derived from daily rate × worked days','—'],
    ['SAL-005','Manage Salary','RBAC','SAL','High','Security','Only HR/Owner manage salary','Logged in as Employee','1. As Employee, attempt salary management','Blocked','—'],
    ['SAL-006','Manage Salary','Effective Date','SAL','Medium','Functional','Salary effective date','New rate with effective date','1. Set effective date in future\n2. Compute before/after','Rate applies from effective date','—'],

    // ===== 10. Benefits & Deductions =====
    ['BEN-001','Benefits & Deductions','SSS','BEN','High','Functional','SSS contribution per bracket','Employee salary in a bracket','1. Compute payroll','SSS contribution matches bracket','—'],
    ['BEN-002','Benefits & Deductions','PhilHealth','BEN','High','Functional','PhilHealth contribution per bracket','Employee salary in a bracket','1. Compute payroll','PhilHealth contribution matches bracket','—'],
    ['BEN-003','Benefits & Deductions','Pag-IBIG','BEN','High','Functional','Pag-IBIG contribution per bracket','Employee salary in a bracket','1. Compute payroll','Pag-IBIG contribution matches bracket','—'],
    ['BEN-004','Benefits & Deductions','Net','BEN','High','Functional','Contributions deducted from gross','All contributions set','1. Compute payroll','Contributions deducted; net = gross − deductions','—'],
    ['BEN-005','Benefits & Deductions','Policies','BEN','Medium','Functional','Contribution policies configurable','Admin access','1. Edit contribution policy\n2. Save','Policy saved; applied by ContributionEngine','—'],
    ['BEN-006','Benefits & Deductions','Records','BEN','Medium','Functional','Deduction records per employee','Employee exists','1. Add a deduction record\n2. Save','Record saved; reflected in payroll','—'],
    ['BEN-007','Benefits & Deductions','Filters','BEN','Medium','Functional','Benefits deduction filter','Multiple deductions','1. Use filter in Benefits Deduction','List filtered correctly','—'],
    ['BEN-008','Benefits & Deductions','Brackets','BEN','Medium','Functional','Bracket update applies forward','Existing brackets','1. Update a bracket\n2. Compute future payroll','Future payrolls use new bracket; past unaffected','—'],
    ['BEN-009','Benefits & Deductions','13th Month','BEN','Medium','Functional','13th-month rule applied','Eligible employee','1. Compute 13th-month','Computed per 13th-month rule','—'],
    ['BEN-010','Benefits & Deductions','RBAC','BEN','High','Security','RBAC on benefits','Logged in as Employee','1. As Employee, attempt benefits management','Blocked','—'],

    // ===== 11. Reports =====
    ['REP-001','Reports','Payroll Report','REP','High','Functional','Payroll report per period/branch','Approved payroll','1. Open payroll report\n2. Generate','Report generated for period/branch','—'],
    ['REP-002','Reports','Attendance Report','REP','High','Functional','Attendance report','Attendance imported','1. Open attendance report\n2. Generate','Report generated','—'],
    ['REP-003','Reports','Request Report','REP','Medium','Functional','Request report','Requests exist','1. Open request report\n2. Generate','Report generated','—'],
    ['REP-004','Reports','Contribution Report','REP','Medium','Functional','Contribution report','Payroll with contributions','1. Open contribution report\n2. Generate','Report generated','—'],
    ['REP-005','Reports','13th Month Report','REP','Medium','Functional','13th-month report','13th-month data','1. Open 13th-month report\n2. Generate','Report generated','—'],
    ['REP-006','Reports','Transaction Slips','REQ073','High','Functional','Generate transaction slips','Approved payroll period','1. Open Transaction Slips\n2. Select period\n3. Generate','Slips generated per employee for the period','—'],
    ['REP-007','Reports','Transaction Slips','REQ073','High','Functional','Print mode = data-only overlay','Slips generated','1. Open Print Preview (?print=1)','Only fill-in data prints; no form chrome (header/panels/labels/lines/grid)','—'],
    ['REP-008','Reports','Transaction Slips','REQ073','High','Functional','Data positions preserved for pre-printed slip','Print preview open','1. Compare data positions to physical BDO slip','Data lands on the form fields at the same coordinates','—'],
    ['REP-009','Reports','Transaction Slips','REQ073','High','Functional','Savings/Current tick per account type','Employees with savings & current accounts','1. Generate slips for each\n2. Print preview','Correct box ticked (Savings or Current) per account type','—'],
    ['REP-010','Reports','Transaction Slips','REQ073','Medium','Negative','Missing bank account → blank fields','Employee without bank details','1. Generate slip','Account fields blank; missing-bank notice','—'],
    ['REP-011','Reports','Export','REQ076','Medium','Functional','Reports export as digital files','Report generated','1. Export report (CSV)','Digital file exported (REQ076)','—'],
    ['REP-012','Reports','Payslip Report','REP','Medium','Functional','HR payslip report per employee','Approved payroll','1. Open payslip report','Per-employee payslip card shown','—'],
    ['REP-013','Reports','Employee List','REP','Low','Functional','Employee list report','Employees exist','1. Open employee list report','Report generated','—'],
    ['REP-014','Reports','Print/Export','REP','Medium','Functional','Reports print/export','Any report','1. Print\n2. Export','Print and export succeed','—'],
    ['REP-015','Reports','Payslip Bulk','REP','Medium','Functional','Bulk print payslips','Approved payroll','1. Bulk print payslips','All employee payslips print','—'],
    ['REP-016','Reports','Audit Trail','REP','Medium','Functional','Audit Trail under Reports','Audit data exists','1. Open Reports → Audit Trail','Audit trail accessible under Reports','—'],
    ['REP-017','Reports','Audit Trail','REP','Low','UI','Audit trail row numbering','Audit entries exist','1. Open audit trail','Rows numbered sequentially','—'],
    ['REP-018','Reports','Preview','REP','Medium','Functional','Report preview before print','Report generated','1. Open preview','Preview renders before printing','—'],
    ['REP-019','Reports','RBAC','REP','High','Security','Employee cannot access HR reports','Logged in as Employee','1. As Employee, attempt HR reports','Blocked','—'],

    // ===== 12. Employee Self-Service =====
    ['PORT-001','Employee Self-Service','Attendance','REQ074','High','Functional','Employee views own attendance','Employee logged in','1. Open portal → attendance','Own attendance shown only','—'],
    ['PORT-002','Employee Self-Service','Schedule','REQ074','High','Functional','Employee views own work schedule','Employee logged in','1. Open portal → schedule','Own schedule shown','—'],
    ['PORT-003','Employee Self-Service','Payslips','REQ074','High','Functional','Employee views own payslips','Approved payslips exist','1. Open portal → payslips','Own payslips listed','—'],
    ['PORT-004','Employee Self-Service','Payslips','REQ074','High','Functional','Employee downloads own payslip PDF','Approved payslip exists','1. Download payslip','PDF downloaded; content correct','—'],
    ['PORT-005','Employee Self-Service','Requests','REQ074','High','Functional','Employee submits requests','Employee logged in','1. Submit leave/overtime/cash advance','Request created; status Pending','—'],
    ['PORT-006','Employee Self-Service','Requests','REQ074','Medium','Functional','Employee tracks own request status','Submitted request','1. Open request tracking','Status visible (Pending/Approved/Cancelled)','—'],
    ['PORT-007','Employee Self-Service','RBAC','REQ074','High','Security','Employee cannot view others data','Logged in as Employee','1. Attempt to access another employee record','Scoped; cannot view others data','—'],
    ['PORT-008','Employee Self-Service','Leave','REQ074','Medium','Functional','Employee views leave balance','Employee with leave balance','1. Open portal → leave balance','Own leave balance shown','—'],
    ['PORT-009','Employee Self-Service','RBAC','REQ074','High','Security','Payslip download unauthorized for others','Logged in as Employee A','1. Attempt to download Employee B payslip','Blocked','—'],
    ['PORT-010','Employee Self-Service','Account','REQ074','Medium','Negative','Unlinked employee account clear error','Account not linked to employee','1. Log in / access portal','Clear error (UnlinkedEmployeeAccountException) shown','—'],

    // ===== 13. Cross-cutting / System =====
    ['SYS-001','System / Cross-cutting','Audit','SYS','High','Functional','Audit trail logs key actions','System in use','1. Perform login/payroll/edit actions\n2. Open audit trail','Key actions logged with user/time/action','—'],
    ['SYS-002','System / Cross-cutting','Audit','SYS','Low','UI','Audit trail row numbering','Audit entries exist','1. Open audit trail','Rows numbered sequentially','—'],
    ['SYS-003','System / Cross-cutting','Timezone','SYS','Medium','Functional','Asia/Manila at boundaries, UTC stored','N/A','1. Inspect stored timestamps vs displayed','Stored instants UTC; business boundary displays Asia/Manila','—'],
    ['SYS-004','System / Cross-cutting','Money','SYS','Medium','Functional','Money integer-centavo / decimal-string','Payroll computed','1. Inspect money values','Money computed in integer centavos / validated decimal strings','—'],
    ['SYS-005','System / Cross-cutting','Localization','SYS','Low','Functional','Dates MM/DD/YY, 24h, English','N/A','1. Inspect date/time displays','Dates MM/DD/YY; time 24-hour; English UI','—'],
    ['SYS-006','System / Cross-cutting','Database','SYS','Medium','Data','MySQL strict mode / utf8mb4','N/A','1. Insert invalid/oversized data','Strict mode rejects invalid; utf8mb4 enforced','—'],
    ['SYS-007','System / Cross-cutting','Database','SYS','Medium','Data','Phinx migrations apply cleanly','N/A','1. Run phinx migrate','Migrations apply without error; schema matches canonical v1.1','—'],
    ['SYS-008','System / Cross-cutting','Session','SYS','Medium','Security','DB-backed sessions','Logged in','1. Inspect sessions table','Session persisted in DB via SessionHandlerInterface','—'],
    ['SYS-009','System / Cross-cutting','CSRF','SYS','High','Security','CSRF on all state-changing requests','N/A','1. Submit POSTs without token','All state-changing POSTs rejected without valid CSRF','—'],
    ['SYS-010','System / Cross-cutting','Security','SYS','High','Security','SQL injection protection','N/A','1. Inject SQL in inputs','Parameterized queries prevent injection; no leakage','—'],
    ['SYS-011','System / Cross-cutting','Security','SYS','High','Security','XSS output escaping','N/A','1. Submit script tags in inputs','All view output escaped; no script executes','—'],
    ['SYS-012','System / Cross-cutting','Config','SYS','Medium','Security','Errors hidden in production','APP_ENV=production','1. Trigger an error','No stack trace shown; errors suppressed in prod','—'],
    ['SYS-013','System / Cross-cutting','Health','SYS','Low','Functional','Health endpoint responds','App running','1. GET /health','Health endpoint returns OK','—'],
    ['SYS-014','System / Cross-cutting','Middleware','SYS','Low','Functional','Request ID / middleware','N/A','1. Make a request\n2. Inspect headers/logs','Request ID propagated','—'],
    ['SYS-015','System / Cross-cutting','Audit','SYS','Low','UI','Audit Trail back button','Audit trail open','1. Click back button on Audit Trail','Returns to previous/Reports view','—'],
    ['SYS-016','System / Cross-cutting','UI','SYS','Low','UI','Validation toast','Form with validation','1. Submit invalid form','Validation toast shown','—'],
    ['SYS-017','System / Cross-cutting','UI','SYS','Low','UI','App version display','N/A','1. Inspect footer/version','App version displayed','—'],
];

/* ----------------------------------------------------------------------
   BUILD WORKBOOK
   ---------------------------------------------------------------------- */
$spreadsheet = new Spreadsheet();

$columns = [
    'TC ID','Module','Feature / Area','Requirement Ref','Priority','Test Type',
    'Test Case Title','Preconditions','Test Steps','Expected Result','Test Data',
    'Status','Actual Result','Tester','Date Tested','Notes',
];
$colCount = count($columns); // 16
$lastColLetter = Coordinate::stringFromColumnIndex($colCount); // P
$lastRow = 1 + count($cases);

$colLetters = [];
for ($i = 1; $i <= $colCount; $i++) {
    $colLetters[$i] = Coordinate::stringFromColumnIndex($i);
}

/* ---------- Sheet 1: Cover & Instructions ---------- */
$cover = $spreadsheet->getSheet(0);
$cover->setTitle('Cover & Instructions');
$cover->getColumnDimension('A')->setWidth(24);
foreach (['B','C','D','E','F'] as $c) {
    $cover->getColumnDimension($c)->setWidth(22);
}

$cover->mergeCells('A1:F2');
$cover->setCellValue('A1', 'WBPMS — Web-Based Payroll Management System');
$cover->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A60']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

$cover->mergeCells('A3:F3');
$cover->setCellValue('A3', 'Regression Test Suite');
$cover->getStyle('A3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1A3A60']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$meta = [
    ['Document Version', '1.0'],
    ['Generated', date('Y-m-d')],
    ['System Under Test', 'WBPMS (Light Diamond Enterprises)'],
    ['Source Repositories', 'Dev: romarsares/WBPMS (branch WBPMS-dev)  |  Prod: lightdiamond0123-rgb/wbpms (branch main)'],
    ['Scope', 'End-to-end regression across all WBPMS modules: functional, security/RBAC, business rules, negative, boundary, UI.'],
    ['Modules Covered', 'Login & Authentication; Dashboard; User Management; Employee Management; Work Schedule; Attendance; Request Management; Payroll; Manage Salary; Benefits & Deductions; Reports; Employee Self-Service; System / Cross-cutting.'],
    ['How to Use', "Open the 'Regression Test Cases' sheet. Execute each test case. Use the dropdowns to set Priority, Test Type, and Status. Record Actual Result, Tester, and Date Tested. The 'Summary' sheet shows live counts via formulas."],
    ['Status Legend', 'Not Run | Pass | Fail | Blocked | In Progress'],
    ['Priority Legend', 'High | Medium | Low'],
    ['Test Type Legend', 'Functional | Security | Negative | Boundary | UI | Integration | Data'],
    ['Requirement Refs', 'REQ IDs from the canonical spec where defined (e.g., REQ047, REQ073, REQ074-REQ082, REQ076). Cases without a specific REQ ID reference their module/feature.'],
    ['Notes', 'Transaction slip print mode (REQ073) currently emits a data-only overlay intended to print onto a physical pre-printed BDO Cash Transaction Slip.'],
];
$row = 5;
foreach ($meta as $item) {
    $cover->setCellValue('A' . $row, $item[0]);
    $cover->mergeCells('B' . $row . ':F' . $row);
    $cover->setCellValue('B' . $row, $item[1]);
    $cover->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true]]);
    $cover->getStyle('A' . $row . ':F' . $row)->applyFromArray([
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
    ]);
    // estimate row height for wrapped text
    $len = strlen($item[1]);
    $cover->getRowDimension($row)->setRowHeight(min(120, max(18, ceil($len / 90) * 15)));
    $row++;
}

/* ---------- Sheet 2: Regression Test Cases ---------- */
$cs = $spreadsheet->createSheet(1);
$cs->setTitle('Regression Test Cases');

// header row
foreach ($columns as $i => $name) {
    $cell = $colLetters[$i + 1] . '1';
    $cs->setCellValue($cell, $name);
}
$cs->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A60']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
]);
$cs->getRowDimension(1)->setRowHeight(30);

// data rows
foreach ($cases as $idx => $case) {
    $r = $idx + 2; // start at row 2
    $full = array_merge($case, ['Not Run', '', '', '', '']); // 16 cols
    foreach ($full as $ci => $val) {
        $cs->setCellValue($colLetters[$ci + 1] . $r, $val);
    }
}

// column widths
$widths = [12, 20, 24, 14, 11, 14, 40, 38, 52, 46, 26, 14, 32, 14, 14, 26];
foreach ($widths as $i => $w) {
    $cs->getColumnDimension($colLetters[$i + 1])->setWidth($w);
}

// data cell styling (wrap, top/left, borders, alt fill)
$cs->getStyle('A2:' . $lastColLetter . $lastRow)->applyFromArray([
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
    'font' => ['size' => 10],
]);
// alternating row fill
for ($r = 2; $r <= $lastRow; $r++) {
    if ($r % 2 === 0) {
        $cs->getStyle('A' . $r . ':' . $lastColLetter . $r)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F7FA']],
        ]);
    }
}

// freeze + autofilter
$cs->freezePane('C2');
$cs->setAutoFilter('A1:' . $lastColLetter . $lastRow);

// data validation dropdowns (Priority E, Test Type F, Status L)
function makeDV(string $list): DataValidation
{
    $dv = new DataValidation();
    $dv->setType(DataValidation::TYPE_LIST);
    $dv->setFormula1('"' . $list . '"');
    $dv->setAllowBlank(true);
    $dv->setShowErrorMessage(true);
    $dv->setShowInputMessage(true);
    return $dv;
}

$dvPriority = makeDV('High,Medium,Low');
$dvType     = makeDV('Functional,Security,Negative,Boundary,UI,Integration,Data');
$dvStatus   = makeDV('Not Run,Pass,Fail,Blocked,In Progress');
$dvStatus->setPromptTitle('Status');
$dvStatus->setPrompt('Select the execution status.');
$dvStatus->setErrorTitle('Invalid status');
$dvStatus->setError('Choose a value from the dropdown list.');

for ($r = 2; $r <= $lastRow; $r++) {
    $cs->getCell('E' . $r)->setDataValidation(clone $dvPriority);
    $cs->getCell('F' . $r)->setDataValidation(clone $dvType);
    $cs->getCell('L' . $r)->setDataValidation(clone $dvStatus);
}

/* ---------- Sheet 3: Summary ---------- */
$sm = $spreadsheet->createSheet(2);
$sm->setTitle('Summary');
$sm->getColumnDimension('A')->setWidth(34);
$sm->getColumnDimension('B')->setWidth(16);

$rangeCol = function (string $colLetter) use ($lastRow) {
    return "'Regression Test Cases'!" . $colLetter . '2:' . $colLetter . $lastRow;
};

$sm->mergeCells('A1:B1');
$sm->setCellValue('A1', 'Regression Summary (live)');
$sm->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A60']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$r = 3;
$sm->setCellValue('A' . $r, 'Metric'); $sm->setCellValue('B' . $r, 'Count');
$sm->getStyle('A' . $r . ':B' . $r)->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']]]);

$statusMetrics = [
    ['Total Test Cases', '=COUNTA(' . $rangeCol('A') . ')', null],
    ['Pass', '=COUNTIF(' . $rangeCol('L') . ',"Pass")', 'C6EFCE'],
    ['Fail', '=COUNTIF(' . $rangeCol('L') . ',"Fail")', 'FFC7CE'],
    ['Blocked', '=COUNTIF(' . $rangeCol('L') . ',"Blocked")', 'FFEB9C'],
    ['In Progress', '=COUNTIF(' . $rangeCol('L') . ',"In Progress")', 'BDD7EE'],
    ['Not Run', '=COUNTIF(' . $rangeCol('L') . ',"Not Run")', 'D9D9D9'],
];
$r = 4;
foreach ($statusMetrics as $m) {
    $sm->setCellValue('A' . $r, $m[0]);
    $sm->setCellValue('B' . $r, $m[1]);
    if ($m[2] !== null) {
        $sm->getStyle('B' . $r)->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $m[2]]]]);
    }
    $r++;
}
// % Pass
$sm->setCellValue('A' . $r, '% Pass (of executed)');
$sm->setCellValue('B' . $r, '=IF((B4+B5+B6+B7)>0,(B5)/(B4+B5+B6+B7),0)');
$sm->getStyle('B' . $r)->getNumberFormat()->setFormatCode('0.0%');
$totalRow = 4;
$r += 2;

// By Module
$sm->setCellValue('A' . $r, 'By Module'); $sm->setCellValue('B' . $r, 'Count');
$sm->getStyle('A' . $r . ':B' . $r)->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']]]);
$r++;
$modules = array_values(array_unique(array_map(fn($c) => $c[1], $cases)));
$moduleRange = $rangeCol('B');
foreach ($modules as $mod) {
    $sm->setCellValue('A' . $r, $mod);
    $sm->setCellValue('B' . $r, '=COUNTIF(' . $moduleRange . ',"' . str_replace('"', '""', $mod) . '")');
    $r++;
}
$r++;

// By Priority
$sm->setCellValue('A' . $r, 'By Priority'); $sm->setCellValue('B' . $r, 'Count');
$sm->getStyle('A' . $r . ':B' . $r)->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']]]);
$r++;
$prioRange = $rangeCol('E');
foreach (['High', 'Medium', 'Low'] as $p) {
    $sm->setCellValue('A' . $r, $p);
    $sm->setCellValue('B' . $r, '=COUNTIF(' . $prioRange . ',"' . $p . '")');
    $r++;
}

$sm->getStyle('A1:B' . $r)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
]);

/* ---------- Save ---------- */
$spreadsheet->setActiveSheetIndex(0);
$outPath = __DIR__ . '/WBPMS_Regression_Test_Suite.xlsx';
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($outPath);

echo "Generated: " . $outPath . "\n";
echo "Test cases: " . count($cases) . "\n";
echo "Modules: " . count($modules) . "\n";
echo "Size: " . round(filesize($outPath) / 1024, 1) . " KB\n";
