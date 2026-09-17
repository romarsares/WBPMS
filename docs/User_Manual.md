# Web-Based Payroll Management System (WBPMS)
## User Manual

**Organization:** Light Diamond Enterprises
**System:** Web-Based Payroll Management System
**Audience:** Business Owner, HR Head, Employee
**Date:** September 2026

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started — Login and Password](#2-getting-started--login-and-password)
3. [Business Owner Guide](#3-business-owner-guide)
   - 3.1 [Owner Dashboard](#31-owner-dashboard)
   - 3.2 [User Management](#32-user-management)
   - 3.3 [Payroll Approval](#33-payroll-approval)
   - 3.4 [Request Approval](#34-request-approval)
   - 3.5 [Reports (Owner)](#35-reports-owner)
4. [HR Head Guide](#4-hr-head-guide)
   - 4.1 [HR Dashboard](#41-hr-dashboard)
   - 4.2 [Employee Management](#42-employee-management)
   - 4.3 [Work Schedules](#43-work-schedules)
   - 4.4 [Attendance Management](#44-attendance-management)
   - 4.5 [Request Management](#45-request-management)
   - 4.6 [Salary Management](#46-salary-management)
   - 4.7 [Benefits and Deductions](#47-benefits-and-deductions)
   - 4.8 [Payroll Management](#48-payroll-management)
   - 4.9 [Reports Management](#49-reports-management)
   - 4.10 [Settings](#410-settings)
5. [Employee Guide](#5-employee-guide)
   - 5.1 [Employee Dashboard](#51-employee-dashboard)
   - 5.2 [My Attendance](#52-my-attendance)
   - 5.3 [My Requests](#53-my-requests)
   - 5.4 [My Payslips](#54-my-payslips)
   - 5.5 [HR Notices](#55-hr-notices)
6. [Reference Tables](#6-reference-tables)
7. [Access Permissions Summary](#7-access-permissions-summary)
8. [Demo Credentials](#8-demo-credentials)

---

## 1. Introduction

The WBPMS is a browser-based payroll and employee self-service system for Light Diamond Enterprises. It replaces the manual spreadsheet payroll process by:

- Importing biometric attendance data from a `.xls` daily-log workbook exported from the biometric device.
- Computing hours worked, lateness, undertime, overtime, and government-mandated deductions (SSS, PhilHealth, Pag-IBIG) automatically.
- Routing completed payroll runs through an HR Head → Business Owner approval workflow.
- Giving employees read-only access to their own attendance records, payslips, and request status.

The system has three roles. Each role sees only the modules relevant to its function:

| Role | What they do |
|---|---|
| Business Owner | Approves or returns payroll runs; approves escalated employee requests; monitors financial reports. |
| HR Head | Manages employees, schedules, attendance, requests, salary, deductions, payroll, and reports. |
| Employee | Views own attendance and payslips; submits leave, overtime, and cash-advance requests. |

**Display conventions used throughout this manual:**
- Dates are shown as `MM/DD/YY`.
- Times are in 24-hour format (e.g., `17:00`).
- Currency is Philippine Peso (₱).

---

## 2. Getting Started — Login and Password

### 2.1 Logging In

1. Open Chrome, Firefox, or Edge and navigate to the WBPMS address provided by your administrator.
2. The **Login** page appears, showing the Light Diamond Enterprises logo.
3. Enter your **Username** in the first field.
4. Enter your **Password** in the second field. Click the eye icon on the right to show or hide the password characters.
5. Click **Login**.
6. If your credentials are correct, the system redirects you to your role's dashboard automatically.
7. If you see a red error banner ("Invalid username or password"), check the spelling of your username and password, then click **Login** again.

> All login attempts are logged and audited. Do not share your credentials with anyone.

### 2.2 Mandatory First-Login Password Change

When your account is newly created, or after an administrator resets your password, the system redirects you to the **Change Password** page immediately after login. You cannot access any other page until this step is completed.

1. Enter a new password in the **New Password** field.
2. Re-enter the exact same password in the **Confirm Password** field.
3. Click **Change Password**.
4. The system saves the new password and redirects you to your dashboard.

### 2.3 Changing Your Password at Any Time

Any user can change their password while logged in:

1. Navigate to `/change-password` in your browser address bar, or use any navigation link labeled **Change Password** if present.
2. Enter your new password and confirm it.
3. Click **Change Password**.

### 2.4 Logging Out

Click **Logout** in the navigation menu at any time. The system immediately ends your session and returns you to the Login page. Always log out when using a shared computer.

---

## 3. Business Owner Guide

### 3.1 Owner Dashboard

**URL:** `/owner/dashboard`

After logging in as Business Owner, you land on the Owner Dashboard.

**Stat cards across the top of the page:**

| Card | What it shows |
|---|---|
| Payroll Pending Approval | Number of payroll runs submitted by HR waiting for your decision. Shown in amber when non-zero. Click **Review payroll →** to go to the payroll list. |
| Requests Awaiting Approval | Employee requests that require your approval. Shown in indigo when non-zero. Click **Review requests →** to go to the request queue. |
| Approved This Period | Payroll runs you have already approved in the current pay period. |
| Active Branches | Total number of active company branches configured in the system. |

**Payroll Runs Awaiting Your Approval** — a table showing every submitted run with its branch, pay period, employee count, submission date, status badge (Pending Approval), and a **Review** button. Click **Review** to open that run's detail page.

**Recently Approved** — a table of runs you have already approved, showing branch, period, gross total, net total, and approval date.

---

### 3.2 User Management

**URL:** `/users`
**Access:** Business Owner (full access), HR Head (view list + reset Employee passwords only)

The User Management page lists all system accounts.

**Summary cards:** Total Users | Active | Inactive | Archived

**Search:** Type any text in the search box to filter the table instantly by username, email, role, or linked employee name.

#### 3.2.1 Creating a New User Account

Only the Business Owner can create user accounts.

1. Click **+ New User** at the top right.
2. Fill in the user form:
   - **Username** — the login name, must be unique (required).
   - **Email** — the account email address (required).
   - **Role** — select one: `BusinessOwner`, `HRHead`, or `Employee`.
   - **Linked Employee** — required when the role is `Employee`. Select the employee record this account belongs to from the dropdown.
   - **Password** — a temporary initial password. The user will be forced to change it on first login.
3. Click **Save**.
4. The new account appears in the table with Active status.

#### 3.2.2 Editing a User Account

1. Find the user in the table.
2. Click **Edit** on that row.
3. Update the fields as needed.
4. Click **Save**.

#### 3.2.3 Deactivating or Activating a User

- To **deactivate** (block login while keeping the account): click **Deactivate** on the row, then confirm the dialog. The status changes to Inactive.
- To **reactivate**: click **Activate** on the row, then confirm. The status returns to Active.

The account record and all its data are preserved in both cases.

#### 3.2.4 Archiving a User

Use archive when the account is permanently no longer needed (e.g., employee has left and the account will never be used again).

1. Click **Archive** on the row.
2. Confirm the prompt ("Archive this user? This cannot be undone.").
3. The account is permanently deactivated. It appears in the list with an Archived badge but cannot be restored through the interface.

> Archiving a user account does not archive the linked employee record. Use Employee Management to archive the employee separately.

#### 3.2.5 Resetting a User's Password

1. Click **Reset Password** on the row of the user whose password needs to be reset.
2. Confirm the prompt.
3. The system generates a new temporary password and displays it once on screen. Record this password and provide it securely to the user. The user will be required to change it on next login.

> The Business Owner can reset any account's password. The HR Head can only reset passwords for Employee-role accounts.

---

### 3.3 Payroll Approval

**URL:** `/owner/payroll`

This page lists all payroll runs that have been submitted to you by the HR Head. Runs with **Pending Approval** status require your action.

#### 3.3.1 Viewing the Payroll List

The table shows each run's branch, pay period, employee count, gross total, net total, status, created date, and a **Review** button. If a run was previously returned by you, the return reason appears in red below the row.

#### 3.3.2 Reviewing a Payroll Run

1. Click **Review** next to the run you want to act on.
2. The review page shows a summary strip at the top:
   - Number of employees included.
   - Gross Total — total earnings before deductions.
   - Total Deductions — all SSS, PhilHealth, Pag-IBIG, cash advances, and manual deductions combined.
   - Net Total — total take-home pay.
   - Submission date.
3. **Earnings Breakdown** table — lists each earning type (Basic Pay, Overtime, Holiday Premium, manual additions) with the number of records and total amount.
4. **Deductions Breakdown** table — lists each deduction type (SSS, PhilHealth, Pag-IBIG, Cash Advance, manual deductions) with counts and totals.
5. **Per-employee table** — each employee row shows name and number, gross pay, total deductions, and net pay. Click **View [N] items** under Earning Details or Deduction Details to expand the line-item breakdown for that employee.
6. Two supporting document buttons are available at the top right:
   - **Cut-off Timesheet** — opens a printable attendance detail for all employees in this run.
   - **Disbursement Summary** — opens the BDO deposit-slip preparation list (available once the run is Approved).

#### 3.3.3 Approving a Payroll Run

After reviewing the figures:

1. Scroll down to the **Approve Payroll** card.
2. Click **✓ Approve Payroll**.
3. Confirm the dialog ("Approve this payroll run? This action cannot be undone.").
4. The run status changes to **Approved**. Employee payslips become visible to employees immediately.

> Approval is permanent and irreversible.

#### 3.3.4 Returning a Payroll Run for Revision

If figures need correction:

1. Scroll down to the **Return for Revision** card.
2. Type your reason in the **Return Reason** text area (required).
3. Click **↩ Return to HR**.
4. Confirm the dialog.
5. The run status changes to **Returned**. The HR Head sees the reason on the payroll detail page and must recompute and resubmit.

---

### 3.4 Request Approval

**URL:** `/owner/requests`

Employee requests that have been **pre-approved by HR** (status: HRApproved) are forwarded to you here for final approval. Requests still Pending with HR do not appear in this queue.

The two-stage workflow is:
1. Employee submits → **Pending** (HR acts)
2. HR approves → **HRApproved** (Owner acts)
3. Owner approves → **Approved** (effects applied) — or Owner returns → **Returned** (HR revises)

#### 3.4.1 Viewing the Request List

The list shows the employee name, request type (Leave / Overtime / Cash Advance), submission date, HR approval date, and current status. Click **View** on any row to open the full request detail.

#### 3.4.2 Viewing a Request Detail

The detail page shows:
- Employee name, employee number, and branch.
- Request type and type-specific details:
  - **Leave:** from date, to date, and number of days.
  - **Overtime:** date, start time, end time, and total hours.
  - **Cash Advance:** amount requested.
- Reason or notes entered by the employee.
- Approval history showing previous actions by HR.

#### 3.4.3 Approving a Request (Final Approval)

1. On the detail page, click **Approve**.
2. Confirm the prompt.
3. The request status changes to **Approved**.
4. All side-effects are applied immediately: leave balance is deducted, overtime is flagged for payroll matching, and cash advance is queued for payroll deduction.

#### 3.4.4 Returning a Request to HR

If the request needs revision or clarification before you can approve it:

1. On the detail page, click **Return**.
2. Enter a reason in the **Owner Notes** text field (required).
3. Click **Return**.
4. The request status changes to **Returned** and HR sees your notes. HR can revise and re-approve to send it back to your queue.

---

### 3.5 Reports (Owner)

**URL:** `/hr/reports`

The Business Owner has read-only access to all reports. See [Section 4.9 Reports Management](#49-reports-management) for full step-by-step instructions. Everything described there applies to the Business Owner as well — you can view all report types and print them, but you cannot modify the underlying data.

---

## 4. HR Head Guide

### 4.1 HR Dashboard

**URL:** `/hr/dashboard`

After logging in as HR Head, you land on the HR Dashboard.

**Stat cards:**

| Card | What it shows |
|---|---|
| Active Employees | Total active employees across all branches. Click **View all →** to go to the employee list. |
| Pending Requests | Leave, overtime, and cash-advance requests awaiting your action. Shown in amber when non-zero. Click **Review →** to go to the request list. |
| Incomplete Attendance | Employees with incomplete or unreviewed attendance records in the current period. Shown in red when non-zero. Click **Review →** to go to attendance. |
| Payroll Drafts | Payroll runs currently in Draft or Computed state waiting to be submitted. Click **View →** to go to payroll. |

**Recent Requests table** — the latest requests showing employee name, type, submission date, and status. Click **All requests** to open the full list.

**Payroll Runs table** — current runs with branch, period, employee count, and status badge. Click **View** on a row to open that run. Click **New run** to create a payroll run immediately.

**Quick Actions row at the bottom:**

| Button | Goes to |
|---|---|
| Add Employee | `/hr/employees/new` |
| Import Attendance | `/hr/attendance/import` |
| Manage Schedules | `/hr/schedules` |
| Generate Payroll | `/hr/payroll/create` |

---

### 4.2 Employee Management

**URL:** `/hr/employees`

#### 4.2.1 Viewing the Employee List

The list shows each employee's number, full name (Last, First), branch, current work schedule, effective-from date, and status badge (Active / Inactive / Archived).

**Filtering the list:**

1. Enter a name or employee number in the **Search** box.
2. Select a **Branch** from the dropdown to show only that branch.
3. Select a **Status** (Active, Inactive, or Archived) from the dropdown.
4. Click **Filter**.
5. To clear all filters and show everyone, click **Clear**.

#### 4.2.2 Adding a New Employee

1. Click **Add Employee** at the top right.
2. Fill in the **Personal Information** section:
   - **First name** (required)
   - **Middle name** (optional)
   - **Last name** (required)
   - **Birthdate** (optional)
   - **Contact number** (optional, format: 09XXXXXXXXX)
   - **Email** (optional)
3. Fill in the **Position and Type** section:
   - **Position / Job title** — select from the dropdown. If the title is not listed, select **Other (type below)** and type the title in the field that appears. To add a new title permanently, click **Add it in Settings →** which opens the Positions settings in a new tab.
   - **Employee type** — select `Regular` or `Contractual`.
4. Fill in the **Employment** section:
   - **Employee number** (required, must be unique)
   - **Effective from / Hire date** (required)
5. Fill in the **Government IDs** section (all optional):
   - SSS number, PhilHealth number, Pag-IBIG number, TIN
6. Fill in the **Assignment & Salary** section:
   - **Branch** (required) — select the employee's assigned branch.
   - **Work schedule** (required) — select the shift template.
   - **Daily rate (₱)** (required) — the employee's starting daily rate.
   - **Status** — Active or Inactive.
7. Fill in the **Biometric Enrollment** section:
   - **Biometric device** (required) — select the device this employee uses to punch in/out.
   - **Enroll ID** (required) — the numeric enrollment code assigned to this employee on the biometric device.
8. Click **Create Employee**.
9. A confirmation page appears showing the new employee number. Note it for reference.

> After creating an employee, if they need system login access, go to User Management to create their user account and link it to this employee record.

#### 4.2.3 Viewing an Employee Profile

Click the employee's name in the list, or click **View** on their row.

The profile page shows five information cards:
- **Personal Information** — name, birthdate, contact, email.
- **Employment** — employee number, type, position, hire date, current status.
- **Government IDs** — SSS, PhilHealth, Pag-IBIG, TIN.
- **Assignment & Pay** — current branch (with branch-since date), work schedule and hours, current daily rate (with rate-since date).
- **Biometric & Login** — device name, enrollment code, linked username and account status. Shows a "Password change required" badge if the user has not yet changed their first-login password.

Below the cards, if the employee has been transferred, a **Branch History** table shows each branch assignment with from/to dates and transfer reason.

If salary records exist, a **Salary History** table shows each daily rate with effective dates and status (Active / Superseded).

**Action buttons at the top of the profile:**
- **Edit** — opens the edit form.
- **Documents** — opens the documents page. Shows a blue count badge if documents exist.
- **Transfer** — opens the branch transfer form (active/inactive employees only).
- **Archive** — archives the employee (active/inactive employees only).
- **Rehire** — rehires the employee (archived employees only).

#### 4.2.4 Editing an Employee

1. Click **Edit** on the employee list or profile page.
2. All fields are editable except **Employee number** and **Hire date** (shown in gray, read-only to preserve history).
3. The **Branch** field is also read-only on the edit form — to change branches, use the **Transfer Branch** button instead so the effective date is recorded.
4. Update any field as needed.
5. Click **Save Changes**.

#### 4.2.5 Transferring an Employee to Another Branch

1. On the employee profile or list, click **Transfer**.
2. On the transfer form:
   - Select the **Destination Branch** from the dropdown.
   - Enter the **Transfer Effective Date** (the date the new assignment begins).
   - Optionally enter a **Transfer Reason** (e.g., "Reassigned by management").
3. Click **Transfer**.

The system closes the old branch assignment on the day before the effective date and opens a new one. Payroll for a given period uses the branch assignment active on the period start date.

#### 4.2.6 Archiving an Employee

Use this when an employee separates from the company (resignation, termination, retirement, etc.).

1. On the employee profile or list, click **Archive**.
2. On the archive form:
   - Enter the **Separation Date**.
   - Enter the **Reason** (e.g., Resigned, Terminated, Retired).
3. Click **Archive**.

Archived employees are excluded from new payroll runs and do not appear in the default Active filter. All historical data (payslips, attendance, requests, salary records) is preserved.

#### 4.2.7 Rehiring an Archived Employee

1. On the employee list, set the Status filter to **Archived** and click **Filter**.
2. Find the employee and click **Rehire**.
3. On the rehire form:
   - Enter the new **Hire Date**.
   - Select the new **Branch**.
   - Select or type the new **Job Position**.
   - Enter the new **Daily Rate** and **Effective From** date.
4. Click **Rehire**.

The employee becomes active again. Their prior history remains intact and linked to the same employee record.

#### 4.2.8 Managing Employee Documents

1. On the employee profile, click **Documents** (or the Documents button with its count badge).
2. The documents page shows a table of all uploaded files with document type, upload date, version, and verification status.

**Uploading a new document:**
1. Click **Upload Document**.
2. Select the **Document Type** from the dropdown (e.g., NBI Clearance, SSS ID, Contract).
3. Click **Choose File** and select the file from your computer.
4. Click **Upload**.

**Verifying a document:**
Click **Verify** next to the document to mark it as verified by HR. The verified badge appears immediately.

**Replacing a document (new version):**
1. Click **Replace** next to the document.
2. Upload the new file.
3. The old file is retained as a prior version; the new file becomes the current version.

**Archiving a document:**
Click **Archive** next to the document and confirm. The document is marked as archived and no longer shown as the current version.

---

### 4.3 Work Schedules

**URL:** `/hr/schedules`

**Stat cards:** Total Schedules | Active | Holidays on Record | Upcoming Holidays

#### 4.3.1 Understanding Schedules

A **Schedule Template** defines a named shift configuration:
- Working days (any combination of Monday–Sunday).
- Time In and Time Out (24-hour format, e.g., `08:00` and `17:00`).
- Break minutes (total unpaid break per day, subtracted when computing hours worked).
- Effective From date and optional Effective To date.

Templates are reusable. Create a template once (e.g., "Day Shift"), then assign it to as many employees as needed. When an employee's schedule changes, create a new assignment — do not delete the old one.

The **Assignments** section below the templates table shows every current employee-to-schedule link.

#### 4.3.2 Creating a Schedule Template

1. Click **+ New Schedule**.
2. A modal dialog opens. Fill in:
   - **Schedule Name** (required, e.g., "Day Shift", "Night Shift").
   - **Working Days** — check each day that is a regular working day.
   - **Time In** — shift start time in 24-hour format.
   - **Time Out** — shift end time in 24-hour format.
   - **Break (minutes)** — total unpaid break time per day (e.g., `60` for a 1-hour lunch break).
   - **Effective From** — the date this schedule takes effect.
3. Click **Save**.
4. The new template appears in the Schedule Templates table.

#### 4.3.3 Editing a Schedule Template

1. Find the template in the Schedule Templates table.
2. Click **Edit** on that row.
3. An edit form opens. Update any field.
4. Click **Save**.

> Editing an existing template changes how attendance is calculated for all employees currently assigned to it. If only some employees are changing their hours, create a new template instead and reassign those employees to it.

#### 4.3.4 Assigning a Schedule to an Employee

1. Click **Assign to Employee**.
2. On the assignment form:
   - Select the **Employee** from the dropdown (shows name and employee number).
   - Select the **Work Schedule** template.
   - Set the **Effective From** date.
   - Optionally enter **Notes** (e.g., "Transferred to night shift").
3. Click **Assign Schedule**.

If the employee already has an active schedule assignment, it is automatically closed the day before the new effective date. The employee will now have the new schedule for all subsequent attendance records.

#### 4.3.5 Viewing the Assignments Table

The Assignments section on the main schedules page shows every employee currently linked to a schedule, with the schedule name and effective dates. Use the inline search box to filter by employee name.

---

### 4.4 Attendance Management

**URL:** `/hr/attendance`

**Stat cards:** Total (records in range) | Complete | Incomplete | Unmatched

#### 4.4.1 Viewing Attendance Records

The attendance page shows a timesheet grid for a selected date range. Each row is one employee-day.

**Selecting the date range:**

- Click the **By Month** tab and use the month picker to select a calendar month.
- Click the **By Cut-off** tab and select a payroll cut-off period from the dropdown.

**Narrowing results:**

- Select a **Branch** from the branch dropdown.
- Type a name or employee number in the **Search** box.
- Click **Filter**.

**Printing the current view:** Click **Print Attendance** to open a print-ready version of the table.

#### 4.4.2 Importing a Biometric Attendance Workbook

The biometric device exports a monthly `.xls` (BIFF/Excel 97–2003) daily-log file. This is the primary way attendance data enters the system. The import happens in five steps.

**Step 1 — Go to the import page.**

Click **Import Workbook** on the Attendance page (top right), or click **Import Attendance** in the HR Dashboard quick actions.

**Step 2 — Upload the workbook.**

1. Select the **Biometric Device** that generated the workbook from the dropdown.
2. Select the **Source Year** (e.g., `2026`).
3. Select the **Source Month** (e.g., `August`).
4. Click **Choose File** and select the `.xls` file exported from the biometric device.
5. Click **Upload**.

The system checks the file's OLE signature, column structure, and size limits. If the file fails validation, a red error message lists each problem. Correct the file and try again — no data is saved on a failed upload.

**Step 3 — Review the preview.**

If parsing succeeds, a preview panel appears showing:
- File name and period.
- Total punches parsed and number of unique enrollment codes found.
- Date range detected in the workbook.
- A sample table of the first parsed punches (row number, punch date/time, enrollment ID, name in device).

Review the sample to confirm the file content is correct (right month, right device). If it looks wrong, click **Cancel / choose another file** to start over.

**Step 4 — Save as Draft.**

Click **Save as Draft**. The punches are stored in an import batch but attendance records are not yet generated. The batch appears in the **Recent Import Batches** table.

**Step 5 — Approve the import.**

1. In the Recent Import Batches table, find the batch just created.
2. Click **Approve**.
3. The system matches each enrollment code to an employee record, generates a timesheet row per employee per day, and computes hours worked, late minutes, undertime minutes, and overtime minutes based on the employee's assigned schedule.
4. Punches whose enrollment code does not match any employee are flagged as **Unmatched** and shown in the Unmatched count card.

To discard a draft batch before approving it, click **Cancel** on that batch row.

#### 4.4.3 Handling Unmatched Punches

Unmatched punches appear when a biometric enrollment code in the workbook does not correspond to any employee in the system. Common causes:

- A new employee was enrolled on the device but not yet added to WBPMS.
- The Enroll ID on the employee record does not match the ID in the workbook.

**To resolve:**
1. Identify which enrollment code is unmatched.
2. Go to the employee's profile, click **Edit**, and verify the **Enroll ID** field matches the code in the workbook.
3. If the employee does not yet exist, add them first (see Section 4.2.2).
4. Re-run the import approval for the batch.

#### 4.4.4 Adjusting an Attendance Record

Use this when a punch was missed, a time is incorrect, or approved overtime needs to be added manually.

1. In the attendance list, find the employee's record for the relevant date.
2. Click **Adjust** on that row.
3. The adjust page shows a summary card at the top with the employee name and number, date, schedule, current status, and current worked/overtime minutes.
4. Fill in the adjustment form:
   - **Time In** (required) — corrected clock-in time.
   - **Time Out** (required) — corrected clock-out time.
   - **Manual overtime minutes** (optional) — enter a number to override the system's overtime calculation. Leave blank to use the value derived from the corrected times. Enter `0` to explicitly remove overtime.
   - **Reason and supporting reference** (required) — describe why the adjustment is needed (e.g., "Supervisor-approved overtime, gate log reference #123").
5. Click **Save audited adjustment**.

> Adjustments are blocked if the related payroll run has already been **Approved**. If the run is only Computed or Pending, save the adjustment and then recompute the run.

All adjustments are permanently logged with the HR user's identity.

#### 4.4.5 Attendance Policy Flags

**URL:** `/hr/attendance/policy/flags`

The system can evaluate attendance records against configurable policy rules (e.g., habitual tardiness, excessive absences).

**Running an evaluation:**

1. Click **Evaluate** at the top of the flags page.
2. The system processes current attendance data and lists employees who have triggered a policy threshold.
3. Each flag row shows the employee name, violation type, count, and current flag status.

**Reviewing a flag:**

1. Click **Review** next to a flag.
2. Add a note describing the action taken (e.g., "Verbal warning issued 09/10/26").
3. Click **Save**.
4. The flag is marked as reviewed.

> The system generates alerts only. It does not automatically suspend or terminate employees. All disciplinary decisions remain with HR and management.

#### 4.4.6 Attendance Frequency Report

**URL:** `/hr/attendance/frequency-report`

The Attendance Frequency Report gives a summary of tardiness occurrences and verified absences for a selected date range. It is used for attendance review and disciplinary monitoring.

**Opening the report:**

1. On the Attendance page, click **Frequency Report** (top action row).

**Filtering the report:**

1. Enter a **From** date and a **To** date to define the period.
2. Optionally select a **Branch** to narrow results to one branch.
3. Optionally type an employee name or employee number in the **Employee** search box.
4. Click **Apply** to refresh the results. Click **Reset** to clear all filters.

**Reading the summary cards:**

| Card | What it shows |
|---|---|
| Tardy occurrences | Total number of days any employee arrived late across the period. |
| Total late minutes | Sum of all late minutes accumulated across all employees. |
| Verified absence days | Total absence days excluding approved leave, holidays, unscheduled days, and dates without approved import coverage. |
| Employees with incidents | Number of distinct employees who had at least one tardy or absence incident. |

**Highest frequency chart:**

The chart ranks the top employees by incident count. Use the toggle buttons above the chart to switch between:
- **Combined** — total tardiness + absence incidents per employee.
- **Tardiness** — tardy days only.
- **Absences** — verified absence days only.

The employee table below the chart is automatically sorted to match the selected chart category.

**Employee frequency detail table:**

| Column | Description |
|---|---|
| Employee | Employee name and employee number. |
| Branch | Employee's assigned branch. |
| Tardy days | Number of days the employee was late during the period. |
| Late minutes | Total accumulated late minutes. |
| Absence days | Number of verified absence days (exclusions applied). |
| Total frequency | Combined tardiness + absence count used for ranking. |

**Printing the report:**

Click **Print Report** at the top right. The page renders in landscape orientation with all filters and interactive controls hidden.

> Frequency counts are for attendance review purposes only. The system does not automatically apply discipline or penalties.

---

### 4.5 Request Management

**URL:** `/hr/requests`

This module handles all leave, overtime, and cash-advance requests — whether submitted by employees through their portal or entered directly by HR.

**Stat cards:** Total | Pending | Approved | Rejected

#### 4.5.1 Viewing the Request List

The table shows: request ID, employee name and number, type badge (colored: Leave = indigo, Overtime = blue, Cash Advance = amber), reason (truncated), submission date, status badge, and action buttons.

**Filtering the list:**

1. Select a **Status** (All / Pending / Approved / Rejected / Cancelled).
2. Select a **Type** (All / Leave / Overtime / CashAdvance).
3. Type an employee name or number in the **Employee** search box.
4. Click **Filter**.
5. Click **Clear** to reset all filters.

#### 4.5.2 Creating a Request on Behalf of an Employee

HR can create a request directly when an employee cannot access the portal.

1. Click **+ New Request**.
2. Select the **Employee** from the dropdown.
3. Select the **Request type**:
   - For **Leave**: enter From date and To date.
   - For **Overtime**: enter OT date, Start time, and End time (24-hour).
   - For **Cash Advance**: enter Amount requested (₱).
4. Enter a **Reason / notes** (required for all types).
5. Click **Submit Request**.

#### 4.5.3 Viewing a Request in Detail

Click **View** on any request row. The detail page shows:
- Employee name, number, and branch.
- Request type with all type-specific fields fully expanded.
- The reason/notes entered.
- Approval history timeline.

#### 4.5.4 Approving a Request (HR Pre-Approval)

Requests go through a **two-stage approval**: HR approves first, then the Business Owner gives final approval.

From the **list page:** Click **Approve** directly on any Pending row and confirm the dialog.

From the **detail page:** Click **Approve** and confirm.

After HR approves, the request status changes to **HRApproved** and it moves to the Business Owner's request queue for final approval. Leave, overtime, and cash-advance side-effects (leave balance deduction, payroll application) are **not applied yet** — they take effect only after the Owner gives final approval.

#### 4.5.5 Rejecting a Request

1. Open the request detail page.
2. Click **Reject**.
3. Enter a **Rejection Reason** in the text field (required).
4. Click **Reject**.

The rejection reason is stored and visible to the employee in their My Requests list under the **HR Note** column.

#### 4.5.6 Archiving a Request

After a request is closed (approved, rejected, or cancelled), it can be archived to keep the active list clean:

1. Open the request detail page.
2. Click **Archive**.
3. Confirm the prompt.

Archived requests are hidden from the default list view but remain in the database for audit purposes.

---

### 4.6 Salary Management

**URL:** `/hr/salary`

**Stat cards:** Records | Active | Avg Daily Rate | Highest Rate

#### 4.6.1 Viewing Salary Records

The table shows: employee name and number, branch, daily rate, effective-from date, effective-to date, and status badge (Active / Superseded / Archived).

**Filtering:**

1. Type a name or employee number in the **Search** box.
2. Select a **Branch** from the dropdown.
3. Select a **Status** (Active, Superseded, Archived, or All).
4. Click **Filter**. Click **Clear** to reset.

#### 4.6.2 Adding a New Salary Record

Use this when setting the initial daily rate for a new employee, or when you need to create a record for an employee who has none.

1. Click **+ Add Salary Record**.
2. Select the **Employee** from the dropdown.
3. Enter the **Daily Rate (₱)**.
4. Set the **Effective From** date (the date this rate takes effect).
5. Click **Create Record**.

If the employee already has an Active salary record, it is automatically closed (effective-to is set to the day before the new record's effective-from). The new record becomes Active.

#### 4.6.3 Updating an Existing Daily Rate

Use this for salary increases, corrections, or any rate change.

1. Find the employee's Active salary record in the table.
2. Click **Update Rate** on that row.
3. On the form:
   - The employee name is shown read-only.
   - Enter the new **Daily Rate (₱)**.
   - Set the **Effective From** date for the new rate.
   - A note below the date field confirms: "A new record will be created; the current record will be closed the day before this date."
4. Click **Save New Rate**.

A **Rate History** panel appears on the right side of the form showing all previous rates for this employee, with each rate's status (Active, Superseded, Archived) highlighted.

#### 4.6.4 Archiving a Salary Record

Use only for data-entry errors on records that have never been used in payroll.

1. Find the record in the table.
2. Click **Archive** and confirm.

For legitimate rate changes, always use **Update Rate** so the history is preserved correctly.

---

### 4.7 Benefits and Deductions

**URL:** `/hr/benefits`

**Stat cards:** Policy Versions | Approved Policies | Programs | Contribution Records

This module manages SSS, PhilHealth, and Pag-IBIG statutory contribution policies and the records computed from payroll runs.

#### 4.7.1 Viewing the Benefits Overview

**Active Contribution Policies** table — shows the currently approved policy for each program (SSS, PhilHealth, Pag-IBIG) with its version number and effective dates.

**Recent Contribution Records** table — shows per-employee contribution amounts computed by payroll runs. Each row shows: employee name and number, program, pay period, employee share, employer share, and status (Open or Locked).

#### 4.7.2 Managing Contribution Policies

**URL:** `/hr/benefits/policies`

1. Click **Manage Policies**.
2. The policies table lists every version of each program's contribution policy with: program code, version, effective-from, effective-to, status (Draft / Approved / Retired), and created date.
3. To approve a Draft policy, click **Approve** on that row and confirm.

When a payroll run is computed, the system automatically uses the Approved policy that was in effect for the pay period's start date. There is no manual per-employee entry needed.

> If no policies are listed, run the database seeders (`vendor/bin/phinx seed:run`) to load the demo contribution fixtures.

#### 4.7.3 Locking a Contribution Record

Once a pay period closes and contribution figures are confirmed, lock the records to prevent further changes:

1. In the Contribution Records table, find the record.
2. Click **Lock** and confirm.
3. The status changes to **Locked**.

#### 4.7.4 Unlocking a Contribution Record

If a locked record needs correction:

1. Click **Unlock** next to the Locked record and confirm.
2. Make corrections through a payroll adjustment or by recomputing the run.
3. Re-lock the record after corrections.

---

### 4.8 Payroll Management

**URL:** `/hr/payroll`

A payroll run moves through these stages in order:

```
Draft → Computed → PendingOwnerApproval → Approved
                                        ↓
                                      Returned (goes back to HR)
```

**Stat cards:** Total Runs | Draft/Computed | Pending Approval | Approved

#### 4.8.1 Viewing Payroll Runs

The table shows: branch, period, employee count, gross total, net total, status badge, created date, and **View**.

- If a run was returned by the Owner, the return reason appears in a red row below it.
- If a run was cancelled, the cancellation reason appears in a gray row below it.

#### 4.8.2 Prerequisites Before Creating a Payroll Run

Confirm the following before creating a run for a period:

1. At least one **Payroll Period** is defined in Settings → Periods covering the target dates.
2. Attendance for the period has been **imported and approved** (batches show Approved status).
3. All relevant **leave and overtime requests** for the period are Approved or Rejected (not still Pending).
4. All employees in the branch have an **Active salary record** covering the period.
5. All employees in the branch have an **Active schedule assignment** covering the period.

#### 4.8.3 Creating a New Payroll Run

1. Click **+ New Payroll Run**.
2. On the create form:
   - Use the **Month filter** dropdown (e.g., "September 2026") to filter the period list by month.
   - Select the specific **Payroll Period** (e.g., "Sep 01–Sep 15, 2026").
   - Select the **Branch**.
   - A note below the Branch field reads: "Eligible employees are those assigned to this branch at the period start date."
3. Click **Create Draft Run**.

The system creates a Draft run containing all employees assigned to the selected branch as of the period start date.

#### 4.8.4 Viewing a Payroll Run Detail

Click **View** on any run to open its detail page. The page shows:

- A **meta strip** at the top: Branch, Period, Created date, Submitted date, Reviewed date, and the status badge.
- If the run was returned, a red banner shows the return reason.
- If the run was cancelled, a gray banner shows the cancellation reason.
- **Earnings Breakdown** table — each earning type, record count, and total amount.
- **Deductions Breakdown** table — each deduction type, record count, and total amount.
- **Per-employee table** — one row per employee with: name/number, expandable earning details, gross pay, total deductions, expandable deduction details, and net pay. The footer row shows grand totals.
- Action buttons (which appear depend on the current status — see below).

#### 4.8.5 Computing a Payroll Run

Available when status is **Draft** or **Returned**.

1. Open the payroll run detail page.
2. Click **Compute**.
3. The system calculates for each employee:
   - **Basic Pay** — daily rate × number of regular working days attended (late and undertime reduce this).
   - **Overtime Pay** — approved overtime hours matched to actual attendance, at the applicable premium.
   - **Holiday Premium** — additional pay for regular or special non-working holidays worked.
   - **Manual Earnings** — any additional earning adjustments entered by HR.
   - **SSS, PhilHealth, Pag-IBIG** — employee share deductions based on the active contribution policy.
   - **Cash Advance Deduction** — from approved cash advance requests in the period.
   - **Manual Deductions** — any deduction adjustments entered by HR.
4. Status changes to **Computed**.

#### 4.8.6 Adding a Manual Adjustment to One Employee

Available when status is **Computed** (before submission).

1. On the payroll run detail page, find the employee row.
2. Click **Adjust** on that row.
3. The adjustment page shows the employee's name and number, and their current Gross / Deductions / Net figures.
4. Fill in the form:
   - **Adjustment type** — select `Additional pay / earning` or `Manual deduction`.
   - **Amount** — the peso amount (positive number).
   - **Reason and supporting reference** — describe the adjustment (required, e.g., "Approved clothing allowance", "Loan repayment per voucher #045").
5. Click **Save manual adjustment**.
6. The employee's totals are updated immediately. A log entry is created.

> Adjustments are locked after the run is Approved. The warning banner on the form reminds you of this.

#### 4.8.7 Submitting for Owner Approval

Available when status is **Computed**.

1. On the payroll run detail page, click **Submit for Approval**.
2. Confirm the prompt.
3. Status changes to **PendingOwnerApproval**.
4. The Business Owner sees this run on their dashboard and in their payroll list.

#### 4.8.8 Handling a Returned Run

When the Business Owner returns a run:

1. Status changes to **Returned**.
2. The return reason appears in a red banner at the top of the detail page.
3. Review the reason and make the necessary corrections:
   - Add or edit adjustments (click **Adjust** on affected employees).
   - If attendance data needs correcting, go to Attendance Management, adjust the record, then come back.
4. Click **Compute** to recompute with the corrections applied.
5. Click **Submit for Approval** again.

#### 4.8.9 Cancelling a Payroll Run

Available when status is **Draft** or **Computed** only. Submitted or Approved runs cannot be cancelled.

1. On the payroll run detail page, click **Cancel**.
2. Enter a **Cancellation Reason** (required).
3. Confirm.
4. Status changes to **Cancelled**. The run is permanently closed.

#### 4.8.10 Printing the Cut-off Timesheet

**URL:** `/hr/payroll/{id}/timesheet-print`

The Cut-off Timesheet is a printable supporting document that lists every attendance entry included in the payroll computation for a given run.

1. On the payroll run detail page, click **Print Timesheet** (available on Computed, PendingOwnerApproval, and Approved runs).
2. The timesheet page loads showing:
   - **Summary cards** — employee count, total payable attendance days, total worked hours, total late minutes, and total overtime minutes.
   - **Detail table** — one row per employee per attendance day with: employee name and number, date, time in, time out, worked time, late minutes, undertime minutes, overtime minutes, and status.
3. Click **Print Timesheet** at the top right, or use Ctrl+P. The page prints in landscape orientation.

> Only attendance records with Complete, Approved, or ReviewRequired status and approved import coverage are included. Future dates and unscheduled days are excluded.

#### 4.8.11 Disbursement Summary (BDO Preparation)

**URL:** `/hr/payroll/disbursement-summary`

The Disbursement Summary generates the manual BDO deposit-slip preparation list for all approved branch payroll runs in a cut-off. This document supports the current BDO workflow: one aggregate cheque is drawn and individual deposit slips are prepared per employee.

**Accessing the disbursement summary:**

1. From the payroll run detail page of an **Approved** run, click **Disbursement Summary**.

**Reading the summary:**

- **Forwarding note** — a pre-filled text summary you can copy (click **Copy Forwarding Note**) to paste into emails or physical forwarding documents.
- **Summary stats** — employee count, gross payroll total, total deductions, and the **one aggregate BDO cheque amount** (sum of all net pay).
- **Approved branch payroll summary** — a table of all approved branch runs in the cut-off showing gross, deductions, and net per branch.
- **Manual BDO deposit-slip preparation list** — one row per employee with their name, employee number, BDO account name, BDO account number, and exact net pay amount. Use this list to prepare individual deposit slips.

**Not ready state:**

If any non-cancelled branch run in the cut-off is not yet Approved, the summary shows a "Not ready for disbursement" message with the blocking run statuses. All branch runs must be approved before the preparation list is available.

**Printing:**

Click **Print Package**. The page renders in landscape orientation. Save as PDF using your browser's print dialog if needed.

---

### 4.9 Reports Management

**URL:** `/hr/reports`

**Stat cards:** Active Employees | Approved Payroll Runs | Total Net Pay Approved | Total Requests

#### 4.9.1 Available Report Types

The overview page shows six report tiles:

| Report | What it contains |
|---|---|
| Payroll Summary | All approved payroll runs with branch, period, employee count, gross, and net totals. |
| Attendance Report | Employee attendance records (worked hours, late, undertime, overtime) for a selected period. |
| Leave/Request Report | Summary of all submitted leave, overtime, and cash-advance requests with statuses. |
| Contributions Report | SSS, PhilHealth, and Pag-IBIG contribution amounts by employee and period. |
| 13th Month Pay | Computed 13th-month pay per employee based on approved payroll runs for a given year. |
| Employee List | Directory of all employees with branch, position, schedule, and status. |

#### 4.9.2 Viewing a Report

1. Click the report tile on the overview page.
2. On the preview page, use the available filters (period range, branch, employee, year) to narrow the data.
3. The filtered data is displayed in a table on screen.

#### 4.9.3 Exporting or Printing a Report

1. From any report preview page, use your browser's Print function (Ctrl+P / Cmd+P or File → Print).
2. To save as PDF, select **Save as PDF** as the printer destination in the browser's print dialog.
3. The **Export / Print** button on the Reports overview page opens an export form where you can select report type, period start, period end, and format (Print/HTML or CSV Download).

#### 4.9.4 13th Month Pay Report

**URL:** `/hr/reports/13th-month`

1. Click the **13th Month Pay** tile on the Reports overview.
2. Select the **Year** for the computation.
3. The table shows each employee's computed 13th-month pay, calculated as total basic pay earned across all approved payroll runs in that year, divided by twelve.

---

### 4.10 Settings

**URL:** `/hr/settings`

The Settings page has three tabs accessible via the tab strip at the top: **Job Positions**, **Payroll Periods**, and **Holidays**.

#### 4.10.1 Job Positions Tab

**URL:** `/hr/settings/positions`

**Stat cards:** Total | Active | Inactive

Job positions are the titles available in the employee form's Position dropdown (e.g., Cashier, Warehouse Staff, Driver).

**Adding a new position:**

1. Click the **Job Positions** tab (or navigate to `/hr/settings/positions`).
2. In the **Add Position** form at the top:
   - Enter the **Position Name** (required).
   - Optionally enter a **Department**.
3. Click **Save**.
4. The new position appears in the table and is immediately available in the employee form.

**Editing a position:**

1. Find the position in the table.
2. Click **Edit** on that row.
3. The row becomes editable inline. Update the name or department.
4. Click **Save**.

**Activating or deactivating a position:**

- Click **Deactivate** to hide it from the employee form dropdown. Existing employees that already have this position assigned are not affected.
- Click **Activate** to restore it to the dropdown.

#### 4.10.2 Payroll Periods Tab

**URL:** `/hr/settings/periods`

Payroll periods are the specific date ranges (cut-offs) used when creating payroll runs and filtering attendance. The system follows a Sunday-to-Friday schedule by default.

**Adding a payroll period:**

1. Click the **Payroll Periods** tab.
2. In the Add Period form:
   - Enter the **Period Start** date.
   - The system pre-fills the standard period end date. Adjust only if needed.
3. Click **Save**.

The new period appears in the periods table with Open status and is immediately available in the payroll run creation form.

> Periods must not overlap with existing ones. The system rejects overlapping entries. Periods tied to an approved payroll run cannot be deleted.

**Filtering periods by month:**

Use the **Month** filter above the periods table to show only periods within a specific month.

#### 4.10.3 Holidays Tab

**URL:** `/hr/settings/holidays`

The holiday calendar drives payroll premium calculations. Working on a Regular Holiday pays 200% of the daily rate; a Special Non-Working Holiday pays 130%.

**Adding a holiday:**

1. Click the **Holidays** tab.
2. In the Add Holiday form:
   - Enter the **Holiday Name** (e.g., "Independence Day").
   - Enter the **Date**.
   - Select the **Holiday Type**: `Regular Holiday` or `Special Non-Working`.
3. Click **Save**.

**Editing a holiday:**

1. Click **Edit** next to the holiday.
2. Update the name, date, or type.
3. Click **Save**.

**Deleting a holiday:**

1. Click **Delete** next to the holiday.
2. Confirm the prompt.

> Holidays that have already been used in a computed or approved payroll run cannot be deleted.

---

## 5. Employee Guide

### 5.1 Employee Dashboard

**URL:** `/employee/dashboard`

After logging in as an Employee, you land on your personal dashboard showing today's date.

**Stat cards:**

| Card | What it shows |
|---|---|
| Branch | Your currently assigned branch name. |
| Schedule | Your current work schedule name. |
| Sick Leave Balance | Remaining sick leave days this year. Shown in red when fewer than 2 days remain. |
| Pending Requests | Number of your requests not yet actioned. Shown in amber when non-zero. Click **View →** to go to your requests. |
| HR Notices | Number of unacknowledged HR notices addressed to you. Shown in amber when non-zero. Click **View notices** to go to the notices page. |

**Recent Payslips** panel — the last few approved payslips showing pay period, net pay, and an Approved badge. Click **View** on any row to open that payslip. Click **All payslips** to see the full list.

**My Requests** panel — your recent requests showing type, submission date, and status badge. Click **New request** in the panel header to submit a new request.

**Quick Actions row at the bottom:**

| Button | Goes to |
|---|---|
| View Attendance | Your attendance history page |
| HR Notices | Your HR notices page |
| Submit Request | New request form |
| My Payslips | Your payslips list |

---

### 5.2 My Attendance

**URL:** `/employee/attendance`

This page shows your personal attendance records for the last 90 days.

**Stat cards:** Total Days | Complete | Incomplete | Total Overtime

**The table columns:**

| Column | Description |
|---|---|
| Date | The attendance date. |
| Time In | Your recorded clock-in time (biometric punch). |
| Time Out | Your recorded clock-out time. |
| Worked | Total worked time in hours and minutes (h mm format). |
| Late | How many hours/minutes late you were (shown in red). |
| Undertime | How many hours/minutes short of the scheduled time out (shown in amber). |
| Overtime | Approved and computed overtime hours/minutes (shown in green). |
| Status | **OK** (green) = complete record with both punches. **Incomplete** (red) = missing one or both punches. |

**Filtering:** Type a date in the search box at the top to filter the table by date.

> Attendance records are read-only for employees. You cannot edit your own attendance. If a record is incorrect (e.g., a punch is missing), contact your HR Head to request a manual adjustment.

---

### 5.3 My Requests

**URL:** `/employee/requests`

This page lists all requests you have ever submitted.

**Filtering by status:**

1. Select a status from the **Status** dropdown: All statuses / Pending / Approved / Rejected / Cancelled.
2. Click **Filter**.
3. Click **Clear** to show all again.

**The table columns:**

| Column | Description |
|---|---|
| Type | Leave, Overtime, or Cash Advance. |
| Details | A summary of the request (e.g., date range for leave, date/time for OT, amount for cash advance). |
| Submitted | The date you submitted the request. |
| Status | Current status badge. |
| HR Note | The HR Head's rejection reason (shown only for Rejected requests). |
| (Action) | **Cancel** button for Pending requests only. |

#### 5.3.1 Submitting a New Request

1. Click **New Request** (top right of the My Requests page, or from the dashboard).
2. If you have 1 or fewer sick leave days remaining, a yellow warning banner appears at the top reminding you of your balance. A leave request will be blocked if your balance is insufficient at approval time.
3. Select the **Request type** from the dropdown:

**For Leave:**
- The leave fields appear after selecting the type.
- Enter the **From date** (start of the leave period).
- Enter the **To date** (end of the leave period).
- Enter a **Reason / notes** (required).
- Click **Submit Request**.

**For Overtime:**
- The overtime fields appear after selecting the type.
- Enter the **OT date** (the date overtime will be worked).
- Enter the **Start time** in 24-hour format (e.g., `18:00`).
- Enter the **End time** in 24-hour format (e.g., `21:00`).
- Enter a **Reason / notes** (required).
- Click **Submit Request**.

**For Cash Advance:**
- The cash advance field appears after selecting the type.
- Enter the **Amount requested (₱)** as a number (e.g., `2500.00`).
- Enter a **Reason / notes** (required).
- Click **Submit Request**.

4. After submitting, you are returned to the My Requests list and your new request appears at the top with **Pending** status.

#### 5.3.2 Cancelling a Pending or Returned Request

You can cancel requests that are **Pending** or **Returned** (returned to HR by the Business Owner).

1. On the My Requests list, find the Pending or Returned request.
2. Click the **Cancel** button on that row.
3. Confirm the dialog ("Cancel this request?").
4. The request status changes to **Cancelled** immediately.

Once a request has been Approved, HRApproved, or Rejected, it cannot be cancelled.

---

### 5.4 My Payslips

**URL:** `/employee/payslips`

This page lists all your payslips from approved payroll runs.

**The table columns:** Period | Net Pay | Status | Action (View link)

Only **Approved** payslips are visible here. If a payslip for an expected period is missing, the payroll run for that period has not yet been approved by the Business Owner.

#### 5.4.1 Viewing a Payslip

1. Click **View** next to the payslip.
2. The payslip detail page shows:
   - Three summary cards at the top: **Gross Pay**, **Total Deductions**, **Net Pay**.
   - A line-item table showing every earning and deduction individually. Common line items include:
     - Basic Pay
     - Holiday Premium (if applicable)
     - Overtime Pay (if applicable)
     - SSS Contribution
     - PhilHealth Contribution
     - Pag-IBIG Contribution
     - Cash Advance Deduction (if applicable)
     - Any manual earning or deduction adjustments
3. Click **← Back** or the browser back button to return to the payslips list.

#### 5.4.2 Printing a Payslip

1. On the payslip detail page, click **Print Payslip** if the button is present, or use your browser's print function (Ctrl+P / Cmd+P).
2. The page renders in a clean, print-ready layout.
3. Select your printer or choose **Save as PDF** in the print dialog.

---

### 5.5 HR Notices

**URL:** `/employee/notices`

HR can send notices directly to you — for example, reminders about attendance policy, return-to-work instructions after leave, or contract renewal notices. This page lists all notices addressed to your account.

**The table shows:** Notice title or message, date sent, and acknowledgment status.

**Unacknowledged notices** are highlighted (shown in amber on the dashboard's HR Notices stat card).

#### 5.5.1 Acknowledging a Notice

1. On the notices page, click **Acknowledge** next to the notice.
2. Confirm the dialog.
3. The notice status changes to **Acknowledged** and the amber highlight is removed.
4. Your HR Head can see that you have acknowledged the notice.

You can acknowledge multiple notices one at a time. There is no bulk-acknowledge option.

---

## 6. Reference Tables

### 6.1 Payroll Run Statuses

| Status | Meaning | Who can act next |
|---|---|---|
| Draft | Run created; no figures computed yet. | HR Head: Compute or Cancel. |
| Computed | Figures calculated and ready for review. | HR Head: Adjust individual employees, Submit, or Cancel. |
| PendingOwnerApproval | Submitted to Business Owner. | Business Owner: Approve or Return. |
| Approved | Owner approved; payslips visible to employees. | No further changes. Read-only. |
| Returned | Owner returned for revision with a reason. | HR Head: Correct figures, Recompute, then Resubmit. |
| Cancelled | HR cancelled the run. | No further changes. |

### 6.2 Request Statuses

| Status | Meaning |
|---|---|
| Pending | Submitted and awaiting HR action. |
| HRApproved | HR has pre-approved the request; awaiting Business Owner final approval. |
| Approved | Business Owner gave final approval. Side-effects (leave balance, payroll) are applied. |
| Rejected | Request denied by HR. HR note visible to the employee. |
| Returned | Business Owner returned the request to HR with notes for revision. |
| Cancelled | Cancelled by the employee while Pending or Returned. |

### 6.3 Salary Record Statuses

| Status | Meaning |
|---|---|
| Active | The current effective daily rate for the employee. |
| Superseded | A previous rate that was replaced by a newer record. |
| Archived | Manually archived; typically a data-entry error. |

### 6.4 User Account Statuses

| Status | Meaning |
|---|---|
| Active | Can log in normally. |
| Inactive | Login is blocked; record is preserved. |
| Archived | Permanently deactivated; cannot be restored via UI. |

### 6.5 Contribution Record Statuses

| Status | Meaning |
|---|---|
| Open | Contribution computed; still adjustable via payroll. |
| Locked | Finalized; changes require Unlock first. |

---

## 7. Access Permissions Summary

| Module / Action | Business Owner | HR Head | Employee |
|---|:---:|:---:|:---:|
| Login / Logout | ✓ | ✓ | ✓ |
| Change own password | ✓ | ✓ | ✓ |
| User Management — create, edit, deactivate, archive users | ✓ | — | — |
| User Management — view list | ✓ | ✓ | — |
| User Management — reset Employee passwords | ✓ | ✓ | — |
| Employee Management — add, edit, archive, rehire, transfer | — | ✓ | — |
| Employee Management — manage documents | — | ✓ | — |
| Work Schedule Management | — | ✓ | — |
| Attendance — import workbook, adjust records, evaluate policy | — | ✓ | — |
| Attendance — Frequency Report | — | ✓ | — |
| Attendance — view own records | — | — | ✓ |
| Request Management — pre-approve, reject, archive (all employees) | — | ✓ | — |
| Request Management — final approve or return | ✓ | — | — |
| Request Management — submit own requests | — | — | ✓ |
| Request Management — cancel own pending or returned requests | — | — | ✓ |
| Salary Management — add, update, archive | — | ✓ | — |
| Benefits & Deductions — manage policies, lock/unlock records | — | ✓ | — |
| Payroll — create, compute, adjust, submit | — | ✓ | — |
| Payroll — print Cut-off Timesheet | ✓ | ✓ | — |
| Payroll — Disbursement Summary | ✓ | ✓ | — |
| Payroll — approve or return | ✓ | — | — |
| Owner Request Approval — final approve or return | ✓ | — | — |
| Reports — view all report types | ✓ | ✓ | — |
| Settings — Job Positions, Payroll Periods, Holidays | — | ✓ | — |
| My Payslips — view own payslips | — | — | ✓ |
| HR Notices — receive and acknowledge | — | — | ✓ |

---

## 8. Demo Credentials

The following accounts are loaded by the database seeders for **local development and testing only**. Remove or change these before deploying to any shared or production environment.

| Role | Username | Password |
|---|---|---|
| Business Owner | `owner` | `owner-demo-pass` |
| HR Head | `hrhead` | `hrhead-demo-pass` |
| Employee | `employee` | `employee-demo-pass` |

Passwords are stored using `password_hash(PASSWORD_DEFAULT)`. All three accounts will prompt for a password change on first login.

---

*End of User Manual*
