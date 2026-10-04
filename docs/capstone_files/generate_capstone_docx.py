"""
generate_capstone_docx.py
Generates the WBPMS capstone-format Word document (.docx) covering all
9 module groups.  Run with:  python generate_capstone_docx.py
Output: WBPMS-Capstone-Modules.docx in the same directory.
"""

from docx import Document
from docx.shared import Pt, Cm, RGBColor, Inches
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import os

OUTPUT_PATH = os.path.join(os.path.dirname(__file__), "WBPMS-Capstone-Modules.docx")

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def set_cell_bg(cell, hex_color: str):
    """Set a table cell background colour (hex, e.g. '4472C4')."""
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_color)
    tcPr.append(shd)


def add_table_header(table, headers: list, bg_hex: str = "1F3864", fg_hex: str = "FFFFFF"):
    """Populate the first row of a table with bold white-on-dark headers."""
    hdr_row = table.rows[0]
    for i, text in enumerate(headers):
        cell = hdr_row.cells[i]
        cell.text = text
        run = cell.paragraphs[0].runs[0]
        run.bold = True
        run.font.color.rgb = RGBColor(
            int(fg_hex[0:2], 16), int(fg_hex[2:4], 16), int(fg_hex[4:6], 16)
        )
        run.font.size = Pt(10)
        set_cell_bg(cell, bg_hex)
        cell.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER


def add_data_row(table, values: list, bold_first: bool = False, shade_alt: bool = False, row_idx: int = 0):
    """Append a data row to a table."""
    row = table.add_row()
    for i, val in enumerate(values):
        cell = row.cells[i]
        cell.text = str(val)
        p = cell.paragraphs[0]
        if p.runs:
            run = p.runs[0]
            run.font.size = Pt(10)
            if bold_first and i == 0:
                run.bold = True
        if shade_alt and row_idx % 2 == 1:
            set_cell_bg(cell, "DCE6F1")
    return row


def heading(doc, text: str, level: int = 1):
    p = doc.add_heading(text, level=level)
    p.paragraph_format.space_before = Pt(12 if level == 1 else 6)
    p.paragraph_format.space_after = Pt(4)
    return p


def body(doc, text: str):
    p = doc.add_paragraph(text)
    p.paragraph_format.space_after = Pt(4)
    p.runs[0].font.size = Pt(11) if p.runs else None
    return p


def bullet(doc, text: str, level: int = 0):
    p = doc.add_paragraph(text, style="List Bullet")
    p.paragraph_format.space_after = Pt(2)
    return p


def spacer(doc):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(2)


# ---------------------------------------------------------------------------
# Document construction
# ---------------------------------------------------------------------------

def build_document():
    doc = Document()

    # ---- Page margins ----
    for section in doc.sections:
        section.top_margin = Cm(2.5)
        section.bottom_margin = Cm(2.5)
        section.left_margin = Cm(3.0)
        section.right_margin = Cm(2.5)

    # ====================================================================
    # TITLE PAGE
    # ====================================================================
    doc.add_paragraph()
    title = doc.add_heading("Web-Based Payroll Management System", 0)
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER

    sub = doc.add_paragraph("for Light Diamond Enterprises")
    sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
    sub.runs[0].font.size = Pt(14)
    sub.runs[0].bold = True

    doc.add_paragraph()
    ver = doc.add_paragraph("Capstone Module Documentation — All Nine Module Groups")
    ver.alignment = WD_ALIGN_PARAGRAPH.CENTER
    ver.runs[0].font.size = Pt(12)

    doc.add_paragraph()
    meta = doc.add_paragraph("Version 1.1  |  Date: 2026-08-31  |  Status: Approved Implementation Baseline")
    meta.alignment = WD_ALIGN_PARAGRAPH.CENTER
    meta.runs[0].font.size = Pt(10)
    meta.runs[0].italic = True

    doc.add_page_break()

    # ====================================================================
    # ABSTRACT
    # ====================================================================
    heading(doc, "Abstract", level=1)
    body(doc,
         "Light Diamond Enterprises currently performs multi-branch payroll and attendance reconciliation "
         "manually through spreadsheets. The process is slow, hard to audit, and vulnerable to inconsistent "
         "calculations, especially when biometric attendance, employee transfers, government contributions, "
         "requests, and payroll approval are handled separately.")
    body(doc,
         "The proposed Web-Based Payroll Management System (WBPMS) is a browser-based, role-controlled "
         "information system that centralises employee master data, effective work schedules, biometric "
         "attendance imports, requests, payroll computation, approvals, payslips, and reports. HR uploads "
         "the biometric device's monthly legacy .xls daily-log export; the system validates the document, "
         "preserves each raw punch as evidence, resolves the employee and historical branch at the punch "
         "time, generates a daily timesheet, and makes the resulting attendance available to payroll.")
    body(doc,
         "The implementation adopts frameworkless PHP 8.5, MySQL 8.4, PDO, Phinx, PhpSpreadsheet, "
         "native server-rendered views, PHPUnit, and PHPStan. The design uses explicit transactions, "
         "effective-dated records, immutable payroll and attendance evidence, role-based access control, "
         "and audit logging to make payroll processing reproducible and reviewable.")

    doc.add_page_break()

    # ====================================================================
    # SECTION 1 — BACKGROUND AND PROBLEM STATEMENT
    # ====================================================================
    heading(doc, "1. Background and Problem Statement", level=1)
    body(doc,
         "Light Diamond Enterprises needs a consistent way to manage payroll across its operational "
         "branches. The current spreadsheet workflow takes roughly a week per pay cycle and requires "
         "manual reconciliation of biometric attendance, salary data, requests, deductions, and "
         "statutory contributions. This creates four operational problems:")
    bullet(doc, "Manual calculations can be delayed, inconsistent, or difficult to verify.")
    bullet(doc, "Raw biometric data is not reliably connected to employee, schedule, and branch history.")
    bullet(doc, "Employees have no self-service access to their attendance, requests, or payslips.")
    bullet(doc,
           "Management approval and the reason for changes are not consistently captured as durable "
           "system evidence.")
    body(doc,
         "The system therefore replaces manual spreadsheet reconciliation with a controlled web workflow "
         "while retaining HR review wherever the source data is incomplete, unmatched, or exceptional.")

    # ====================================================================
    # SECTION 2 — PROJECT GOAL AND OBJECTIVES
    # ====================================================================
    heading(doc, "2. Project Goal and Objectives", level=1)
    heading(doc, "2.1 Goal", level=2)
    body(doc,
         "Develop a web-based payroll management system that integrates biometric attendance imports with "
         "employee, schedule, request, contribution, payroll, approval, payslip, and report records for "
         "Light Diamond Enterprises.")

    heading(doc, "2.2 Objectives", level=2)
    objectives = [
        "Maintain centralised employee, branch, schedule, and biometric-enrollment records with effective dates.",
        "Import and validate monthly legacy biometric .xls daily logs without a live device integration.",
        "Generate daily attendance records and calculate worked hours, lateness, undertime, and overtime from the effective schedule.",
        "Compute payroll from attendance, salary history, approved requests, and approved contribution policies.",
        "Route payroll from HR preparation to Business Owner approval or return for revision.",
        "Give employees secure access to only their own attendance, requests, and payslips.",
        "Produce auditable payroll, attendance, request, contribution, and thirteenth-month reports.",
    ]
    for obj in objectives:
        bullet(doc, obj)

    # ====================================================================
    # SECTION 3 — SCOPE AND DELIMITATIONS
    # ====================================================================
    heading(doc, "3. Scope and Delimitations", level=1)
    heading(doc, "3.1 Included Scope", level=2)
    in_scope = [
        "Authentication, password recovery, and role-based access control.",
        "Employee, branch, schedule, holiday, and biometric-device/enrollment management.",
        "Attendance import from an approved legacy .xls daily-log format.",
        "Leave, overtime, and cash-advance request workflow.",
        "Salary history, approved contribution policies, payroll computation, owner approval, payslips, and reports.",
        "Employee self-service views for attendance, requests, and payslips.",
        "Audit logging, safe validation errors, and automated testing.",
    ]
    for s in in_scope:
        bullet(doc, s)

    heading(doc, "3.2 Delimitations", level=2)
    out_scope = [
        "No mobile native application, live biometric device API, bank upload, ATM payroll integration, or multi-country payroll rules.",
        "The BDO disbursement process remains a printable preparation-list workflow: one weekly aggregate cheque and manually prepared employee deposit slips.",
        "Manual attendance adjustment is an auditable HR function; it never replaces the immutable imported punch evidence.",
        "Official branch display names and employee distribution are configurable master data, not hardcoded assumptions.",
    ]
    for s in out_scope:
        bullet(doc, s)

    # ====================================================================
    # SECTION 4 — USERS AND ACCESS BOUNDARIES
    # ====================================================================
    heading(doc, "4. Users and Access Boundaries", level=1)
    body(doc, "The system supports three in-application roles with strictly enforced access boundaries:")

    tbl = doc.add_table(rows=1, cols=3)
    tbl.style = "Table Grid"
    add_table_header(tbl, ["Role", "Primary Responsibilities", "Access Boundaries"])
    rows_data = [
        ("Business Owner",
         "Reviews, approves, or returns payroll; approves or cancels any employee request; "
         "monitors cross-branch summaries and reports.",
         "Cannot operate HR maintenance screens. Cannot approve own payroll computation."),
        ("HR Head",
         "Manages employees, attendance, schedules, requests, salary structures, "
         "deductions/contributions, payroll preparation, and reports.",
         "Cannot give final approval to payroll; that is reserved for the Business Owner."),
        ("Employee",
         "Views personal attendance, work schedule, and payslips; submits and tracks "
         "leave, overtime, and cash-advance requests.",
         "Portal queries are always scoped to the authenticated session; a URL parameter "
         "must never permit access to another employee's data."),
    ]
    for i, (role, resp, boundary) in enumerate(rows_data):
        add_data_row(tbl, [role, resp, boundary], bold_first=True, shade_alt=True, row_idx=i)

    spacer(doc)
    body(doc,
         "The Business Owner account does not require an employee profile because the Owner "
         "does not draw an employee salary. User accounts are email-addressed; no separate "
         "username field exists.")

    doc.add_page_break()

    # ====================================================================
    # MODULE SECTIONS — helper for common subheadings
    # ====================================================================

    def module_section(number, name, overview, user_stories, acceptance_criteria,
                       db_tables, key_rules, php_components):
        heading(doc, f"Module {number}: {name}", level=1)

        heading(doc, "Overview", level=2)
        body(doc, overview)

        heading(doc, "User Stories", level=2)
        for story in user_stories:
            bullet(doc, story)

        heading(doc, "Acceptance Criteria", level=2)
        for ac in acceptance_criteria:
            bullet(doc, ac)

        heading(doc, "Database Tables", level=2)
        tbl = doc.add_table(rows=1, cols=3)
        tbl.style = "Table Grid"
        add_table_header(tbl, ["Table", "Purpose", "Key Columns / Notes"])
        for i, (t, purpose, notes) in enumerate(db_tables):
            add_data_row(tbl, [t, purpose, notes], bold_first=True, shade_alt=True, row_idx=i)
        spacer(doc)

        heading(doc, "Key Business Rules", level=2)
        for rule in key_rules:
            bullet(doc, rule)

        heading(doc, "PHP Components", level=2)
        for comp in php_components:
            bullet(doc, comp)

        doc.add_page_break()

    # ====================================================================
    # MODULE 1 — LOGIN & AUTHENTICATION
    # ====================================================================
    module_section(
        1, "Login and Authentication",
        overview=(
            "Provides secure, role-based access to the system. Employees, HR Heads, and the "
            "Business Owner log in using their registered email address and password. A "
            "forgot-password workflow delivers a time-limited OTP to the user's registered "
            "email; after verification the user sets a new password and is redirected back to "
            "the login screen. Sessions are persisted in MySQL and expire after 30 minutes of "
            "inactivity or an absolute 12-hour limit."
        ),
        user_stories=[
            "As a system user, I want to log in with my email and password so that I can access the features permitted for my role. [REQ001]",
            "As a system user, I want to recover a forgotten password via email OTP so that I am not permanently locked out. [REQ002]",
            "As the system, I want every protected operation to require authentication so that unauthorised access is prevented. [REQN007, REQN011]",
        ],
        acceptance_criteria=[
            "WHEN a user submits valid credentials THEN the system authenticates and redirects to the role-appropriate dashboard. [REQ001]",
            "IF credentials do not match THEN the system rejects the login without revealing which field is incorrect.",
            "WHEN 'Forgot Password' is selected THEN an OTP is sent to the account email; OTP must be verified before the new password can be set. [REQ002]",
            "WHEN a password reset completes THEN the user is redirected back to the login screen.",
            "WHEN authenticated THEN RBAC enforces that each role sees only its permitted modules.",
            "WHEN a Business Owner account is created THEN the account may exist without an employee profile.",
            "THE SYSTEM shall use email as the only login credential; no separate username field exists. [HR interview 2026-09-21]",
            "OTPs and passwords are stored only as hashes; reset challenges expire after use or timeout. [ADR-0002]",
        ],
        db_tables=[
            ("role", "Stores system roles (Business Owner, HR Head, Employee).", "role_id PK, role_name UQ"),
            ("users", "User accounts linked optionally to employee identity.", "user_id PK, employee_id NULL UQ FK, role_id FK, account_email UQ, password_hash, status"),
            ("password_reset_challenge", "Tracks OTP-based reset challenges.", "challenge_id PK, user_id FK, otp_hash, expires_at, attempts_remaining, consumed_at NULL"),
            ("sessions", "MySQL-persisted PHP sessions.", "session_id PK, expires_at, data"),
            ("audit_logs", "Immutable event log including failed logins.", "log_id PK, user_id NULL FK, event_type, action_at, ip_address, user_agent"),
        ],
        key_rules=[
            "Login credential is email address; no username field.",
            "Passwords are hashed with password_hash(PASSWORD_DEFAULT).",
            "Sessions persist in MySQL via a project-owned SessionHandlerInterface; 30-minute idle / 12-hour absolute expiry.",
            "CSRF tokens protect all state-changing forms.",
            "Failed logins are written to audit_logs with a null user reference.",
            "Reset OTPs are hashed before storage and consumed exactly once.",
        ],
        php_components=[
            "AuthController — handles login/logout HTTP actions.",
            "AuthService — validate credentials, manage OTP lifecycle, call password_hash/verify.",
            "DatabaseSessionHandler — project-owned SessionHandlerInterface against sessions table.",
            "AuthMiddleware — resolves session role_id, gates controller actions.",
            "password_reset_challenge repository — upsert/consume OTP challenges.",
        ],
    )

    # ====================================================================
    # MODULE 2 — DASHBOARD
    # ====================================================================
    module_section(
        2, "Role-Based Dashboard",
        overview=(
            "The dashboard provides each role with an at-a-glance view of the most operationally "
            "relevant information. The HR Head sees employee counts, pending requests, payroll status, "
            "and attendance alerts. The Business Owner sees payroll runs pending Owner review and "
            "cross-branch employee/payroll summaries. The Employee sees a personal summary. "
            "The dashboard must render within 5 seconds under normal load."
        ),
        user_stories=[
            "As the HR Head, I want a role dashboard so I can quickly see pending approvals, payroll status, and attendance alerts. [REQ003]",
            "As the Business Owner, I want a cross-branch summary so I can monitor payroll and workforce status without navigating multiple screens.",
            "As an Employee, I want a personal summary so I can see my latest attendance, requests, and pay information.",
        ],
        acceptance_criteria=[
            "WHEN the HR Head opens the Dashboard THEN overview analytics, pending approvals, and payroll summaries are displayed. [REQ003]",
            "WHEN the Business Owner opens the Dashboard THEN payroll runs pending Owner review and cross-branch summaries are displayed.",
            "WHEN there are attendance entries missing time-in or time-out THEN they are surfaced as dashboard alerts.",
            "WHEN the dashboard loads THEN it renders within 5 seconds under normal load. [REQN004]",
        ],
        db_tables=[
            ("users / role", "Determines which dashboard variant to render.", "Resolved via session role_id."),
            ("attendance", "Supplies incomplete-entry alerts.", "status IN ('Incomplete','ReviewRequired')"),
            ("request", "Pending request counts per type.", "status = 'Pending'"),
            ("payroll_run", "Payroll runs pending Owner approval.", "status = 'PendingOwnerApproval'"),
            ("employee", "Active employee count per branch.", "status = 'Active'"),
        ],
        key_rules=[
            "Each role sees only its permitted dashboard widgets.",
            "Attendance alerts are driven by the attendance.status column.",
            "Dashboard data is read-only aggregation; no writes from the dashboard.",
            "Performance target: render within 5 seconds. [REQN004]",
        ],
        php_components=[
            "DashboardController — HTTP handler; selects the role-specific dashboard view.",
            "DashboardQueryRepository — read-only aggregation queries across modules.",
            "Resources/views/dashboard/ — role-specific native PHP templates.",
        ],
    )

    # ====================================================================
    # MODULE 3 — USER MANAGEMENT
    # ====================================================================
    module_section(
        3, "User Management",
        overview=(
            "Allows the HR Head (and Business Owner) to create, edit, activate, deactivate, and archive "
            "user accounts. Each account is assigned a role that controls access throughout the system. "
            "Employee accounts are auto-provisioned when an employee record is created; the HR Head is "
            "notified of the temporary password in a one-time dismissible dialog. Archived accounts are "
            "removed from active listings but retained for audit."
        ),
        user_stories=[
            "As the HR Head, I want to manage system user accounts so that access is limited to authorised, correctly-roled people. [REQ004–REQ008]",
            "As the HR Head, when I add an employee, I want a linked login account created automatically so the employee can access the portal immediately. [REQ010 extension]",
        ],
        acceptance_criteria=[
            "WHEN User Management is opened THEN all user accounts are displayed in a sorted table. [REQ004]",
            "WHEN a new-user form is submitted with valid data THEN the account is created with the assigned role. [REQ005]",
            "WHEN a user's role or details are edited and confirmed THEN the update persists immediately. [REQ006]",
            "WHEN a user's status is toggled THEN the account is activated or deactivated; inactive accounts cannot log in. [REQ007]",
            "WHEN a user account is archived THEN it is removed from active listings; the record is retained for audit. [REQ008]",
            "WHEN a new employee record is created THEN the system auto-provisions a linked user account with the Employee role in the same transaction.",
            "WHEN the auto-provisioned account is created THEN a temporary password is displayed to the HR Head exactly once in a dismissible dialog.",
            "WHEN an employee first logs in with requires_password_change = true THEN the system forces a password change before granting dashboard access.",
            "WHEN an employee is archived THEN the linked user account is simultaneously deactivated.",
            "WHEN an archived employee is rehired THEN the linked account is reactivated with a new temporary password.",
        ],
        db_tables=[
            ("users", "Stores all user accounts.", "user_id PK, employee_id NULL UQ FK, role_id FK, account_email UQ, password_hash, status"),
            ("role", "Role lookup: Business Owner, HR Head, Employee.", "role_id PK, role_name UQ"),
            ("audit_logs", "Records account create/update/archive/activate events.", "event_type, user_id, action_at"),
        ],
        key_rules=[
            "At most one active user account per employee identity; at most one employee profile per user account.",
            "The Business Owner account may exist without an employee profile.",
            "Email is the only login credential; no separate username.",
            "Temporary passwords are never sent by email in the MVP; HR relays them manually.",
            "The requires_password_change flag forces a password update on first login.",
        ],
        php_components=[
            "UserController — HTTP actions for list/create/edit/toggle/archive.",
            "UserService — business logic including auto-provisioning, status transitions.",
            "UserRepository — PDO queries against users table.",
        ],
    )

    # ====================================================================
    # MODULE 4 — EMPLOYEE AND BRANCH MANAGEMENT
    # ====================================================================
    module_section(
        4, "Employee and Branch Management",
        overview=(
            "Maintains the complete employee master record and its effective-dated relationships: "
            "branch assignments, work schedules, biometric enrollments, salary structures, and bank "
            "details. Employee transfers preserve history through non-overlapping effective-dated branch "
            "assignments; archived employees are soft-deleted with all history retained. Contractual "
            "employees have a six-month evaluation period after which HR records regularisation, renewal, "
            "or separation."
        ),
        user_stories=[
            "As the HR Head, I want to maintain employee records across branches so that payroll and attendance always reference accurate data. [REQ009–REQ017]",
            "As the HR Head, I want to transfer an employee to another branch without rewriting historical attendance or payroll. [ADR-0002]",
            "As the HR Head, I want to archive a separated employee and rehire them later without losing history. [REQ012]",
        ],
        acceptance_criteria=[
            "WHEN Employee Management is opened THEN records display name, position, branch, and daily rate. [REQ009]",
            "WHEN a new employee form is submitted THEN the employee record is created. [REQ010]",
            "WHEN an employee record is edited THEN changes persist. [REQ011]",
            "WHEN an employee is separated THEN the HR Head archives the record; history is retained. [REQ012]",
            "WHEN the HR Head searches by name or ID THEN matching records are returned. [REQ013, REQ014]",
            "WHEN HR permanently transfers an employee THEN the prior branch assignment is closed and a non-overlapping assignment is created.",
            "FOR any calendar date, exactly one effective branch assignment per employee is enforced.",
            "WHEN employment type is recorded THEN Regular and Contractual are distinguished; contractual has a six-month evaluation lifecycle.",
            "WHEN a contractual review occurs THEN outcome, effective date, reviewer, and notes are preserved as history. [ADR-0002]",
            "WHEN an employee is created THEN one stable employee identity and at most one linked login account are maintained.",
        ],
        db_tables=[
            ("employee", "Core employee identity record.", "employee_id PK, employee_number UQ, employee_type, name fields, hire_date, status, government IDs, position"),
            ("employee_branch_assignment", "Effective-dated branch history.", "branch_assignment_id PK, employee_id FK, branch_id FK, effective_from, effective_to"),
            ("branch", "Organisational branch master.", "branch_id PK, branch_code UQ, branch_name, location, status"),
            ("address / province / city / barangay", "Flat geography linked to employee.", "address_id PK, street, province_id, city_id, barangay_id"),
            ("employee_biometric_enrollment", "Device-employee code mapping with effective dates.", "enrollment_id PK, employee_id FK, device_id FK, device_employee_code, effective_from, effective_to"),
            ("employment_contract_review", "Contractual review outcomes.", "review_id PK, employee_id FK, review_due_date, outcome, effective_date"),
            ("employee_employment_episode", "Append-only employment episodes for rehire tracking.", "episode_id PK, employee_id FK, started_on, ended_on NULL"),
            ("employee_lifecycle_event", "Audit of archive/rehire/status change events.", "event_id PK, employee_id FK, event_type, prior_status, new_status"),
        ],
        key_rules=[
            "employee_number is immutable after creation.",
            "Transfers use effective-dated branch assignments; historical attendance and payroll are never rewritten.",
            "Archive is triggered by separation; rehire reuses the same employee identity and appends a new episode.",
            "Archiving is blocked if the employee has pending requests, unresolved cash advances, or a mutable payroll membership.",
            "One open employment episode per employee at any given time.",
            "employee.branch_id does not exist; branch is resolved through employee_branch_assignment.",
        ],
        php_components=[
            "EmployeeController — HTTP actions.",
            "EmployeeService — create/update/transfer/archive/rehire logic, effective-date validation.",
            "EmployeeRepository — PDO queries including effective-date resolution.",
            "BranchService / BranchRepository — branch CRUD and status management.",
            "BiometricDeviceService — device registration and branch-coverage configuration.",
        ],
    )

    # ====================================================================
    # MODULE 5 — WORK SCHEDULE MANAGEMENT
    # ====================================================================
    module_section(
        5, "Work Schedule Management",
        overview=(
            "Assigns and maintains calendar-based work schedules per employee. Schedules are "
            "effective-dated; updates create a new period rather than overwriting the current one. "
            "The system also manages the company holiday calendar, which provides regular-holiday "
            "(200%) and special-holiday (130%) pay multipliers used during payroll computation. "
            "The current Light Diamond configurations are Sunday–Thursday (5 days) or "
            "Friday–Thursday (6 days) with Saturday as the rest day."
        ),
        user_stories=[
            "As the HR Head, I want to assign and maintain calendar-based work schedules so attendance is validated against expected hours. [REQ025–REQ031]",
            "As the HR Head, I want to manage holiday calendars so holiday pay multipliers are applied correctly during payroll.",
        ],
        acceptance_criteria=[
            "WHEN a work schedule is assigned THEN working days, rest days, and start/end times are stored. [REQ025]",
            "WHEN a schedule is updated THEN the new period is saved while the prior period is preserved. [REQ026]",
            "WHEN a schedule is archived THEN it is removed from active use; history is retained. [REQ027]",
            "WHEN the HR Head searches schedules THEN matching results are returned. [REQ028]",
            "WHEN schedules are viewed THEN a calendar-based view including holidays is presented. [REQ029, REQ030]",
            "FOR any calendar instant, at most one effective work schedule per employee is enforced. [ADR-0002]",
            "WHEN a holiday is entered as Regular THEN pay_multiplier = 2.00; Special = 1.30.",
        ],
        db_tables=[
            ("work_schedule", "Effective-dated schedule per employee.", "schedule_id PK, employee_id FK, working_days JSON, rest_days JSON, break_minutes, work_start_time, work_end_time, standard_minutes, effective_from, effective_to"),
            ("holiday_calendar", "Company holidays with pay multipliers.", "holiday_id PK, holiday_date UQ, description, holiday_type ENUM('Regular','Special'), pay_multiplier, status"),
        ],
        key_rules=[
            "Effective periods are half-open: effective_from inclusive, effective_to exclusive/NULL.",
            "A current_guard generated column enforces at most one open row per employee.",
            "Regular holiday work pays daily_rate × 2.00; Special holiday work pays daily_rate × 1.30.",
            "Working-day patterns are configurable; Saturday is the rest day in current Light Diamond configuration.",
            "Individual shift windows are 07:00–16:00 or 08:00–17:00.",
        ],
        php_components=[
            "ScheduleController — HTTP actions for CRUD and calendar view.",
            "ScheduleService — assign/update/archive schedules, validate effective-date overlaps.",
            "ScheduleRepository — PDO queries with effective-date resolution.",
            "HolidayRepository — manage holiday_calendar rows.",
        ],
    )

    # ====================================================================
    # MODULE 6 — ATTENDANCE MANAGEMENT
    # ====================================================================
    module_section(
        6, "Attendance Management",
        overview=(
            "The HR Head uploads the biometric device's monthly .xls daily-log export. "
            "The system validates the file (extension, OLE signature, SHA-256, resource limits), "
            "parses it with the versioned LDE_XLS_DAILY_LOG_V1 parser, matches each punch to an "
            "effective biometric enrollment, resolves the employee's historical branch assignment, "
            "and generates one attendance record per employee per day. The entire import is atomic: "
            "a validation failure rolls back everything. HR reviews unmatched punches and incomplete "
            "entries before payroll computation."
        ),
        user_stories=[
            "As the HR Head, I want to upload a monthly biometric .xls workbook and have the system generate a timesheet, so payroll is computed from accurate records. [REQ018–REQ024]",
            "As the HR Head, I want to see an import summary showing matched, unmatched, incomplete, and duplicate counts.",
            "As the HR Head, I want to manually adjust a timesheet entry with a recorded reason and actor. [REQ023]",
        ],
        acceptance_criteria=[
            "WHEN an .xls workbook is uploaded THEN the system parses Dept, User ID, Name, Enroll ID, MM/DD ddd date columns, and all HH:mm tokens. [REQ018, REQ019]",
            "IF the file fails extension, OLE signature, header, date-context, or resource checks THEN the upload is rejected with no partial import. [ADR-0003]",
            "WHEN the workbook is successfully parsed THEN each punch is matched using device + Enroll ID + punch time against an effective enrollment. [REQ018]",
            "IF a punch's enrollment code does not match any employee THEN it is flagged as unmatched for HR review; it is never silently discarded.",
            "WHEN a date cell has one punch THEN the entry is Incomplete; two punches use earliest/latest; more than two punches preserve all and flag for review.",
            "WHEN the HR Head adjusts an entry THEN the adjustment is saved with actor, reason, old values, and new values. [REQ023]",
            "IF a re-import has the same SHA-256 checksum or same device/enrollment/local-timestamp THEN it is skipped; duplicate count is reported. [REQ024]",
            "WHEN import completes THEN a summary of parsed/matched/unmatched/duplicates-skipped/incomplete/multi-punch counts is shown to the HR Head.",
            "WHEN three consecutive late arrivals are detected THEN the employee is flagged for an HR memorandum. [Supplemental HR answer, p. 1]",
            "WHEN three such memoranda are recorded THEN the employee is flagged for HR review of a one-week suspension; the system does NOT impose discipline automatically.",
        ],
        db_tables=[
            ("attendance_import_batch", "Tracks each import: device, uploader, checksum, status, outcome counts.", "import_batch_id PK, device_id FK, file_checksum UQ, source_year, source_month, parser_version, status"),
            ("biometric_punch", "Immutable raw punch evidence from the workbook.", "punch_id PK, import_batch_id FK, device_employee_code, source_local_at, punched_at_utc, match_status, raw_record"),
            ("attendance", "Generated daily timesheet record per employee.", "attendance_id PK, employee_id FK, attendance_date, time_in, time_out, hours_worked_minutes, late_minutes, undertime_minutes, overtime_minutes, status"),
            ("attendance_punch", "Links each raw punch to the generated attendance row.", "PK (attendance_id, punch_id), evidence_role"),
            ("attendance_adjustment", "Auditable manual adjustments to timesheet entries.", "adjustment_id PK, attendance_id FK, adjusted_by FK, old_time_in, new_time_in, reason, adjustment_at"),
            ("attendance_policy_flag", "HR-review flags for consecutive lates, AWOL, etc.", "flag_id PK, employee_id FK, flag_type, triggering_date, status"),
            ("biometric_device / biometric_device_branch", "Registered devices and their branch coverage periods.", "device_id PK, site_id FK; device_branch_id PK, device_id FK, branch_id FK, effective_from, effective_to"),
        ],
        key_rules=[
            "Parser (LDE_XLS_DAILY_LOG_V1) has NO database, HTTP, session, or payroll dependency.",
            "AttendanceImportService owns the single atomic transaction for parsing, matching, and persistence.",
            "Uploaded files are stored in a randomized private path outside public/; the file is deleted in a finally block.",
            "MIME detection is supplementary; OLE compound-file signature is the authoritative type check.",
            "Enroll ID is preserved as a string (leading zeroes retained).",
            "Attendance grain: exactly one row per employee per attendance date (unique constraint).",
            "Historical imports use the punch timestamp to resolve branch and enrollment, not the current date.",
            "Attendance period closes Friday through Thursday; HR closes on Thursday before payroll.",
        ],
        php_components=[
            "AttendanceController — upload HTTP endpoint, adjustment actions, review list.",
            "LdeXlsDailyLogParser — pure DTO/error output, no side effects.",
            "AttendanceImportService — upload validation, transaction orchestration, summary.",
            "AttendanceService — timesheet computation (late/undertime/overtime), incomplete flags.",
            "AttendancePolicyService — consecutive-late/AWOL flag evaluation.",
            "AttendanceRepository / BiometricPunchRepository — PDO persistence.",
        ],
    )

    # ====================================================================
    # MODULE 7 — REQUEST MANAGEMENT
    # ====================================================================
    module_section(
        7, "Request Management",
        overview=(
            "Employees submit leave, overtime, and cash-advance requests through the portal. "
            "Requests start as Pending; employees may update or cancel them only while pending. "
            "The HR Head and Business Owner can approve or cancel any request. Sick leave defaults "
            "to four paid days per year; an insufficient balance blocks submission. Cash-advance "
            "approvals create an obligation tracked through payroll repayments. Declined requests "
            "carry status Cancelled (no separate Rejected status)."
        ),
        user_stories=[
            "As an Employee, I want to submit leave, overtime, and cash-advance requests and track their status. [REQ076–REQ080]",
            "As the HR Head or Business Owner, I want to review, approve, or cancel requests so all requests are centrally tracked and auditable. [REQ032–REQ046]",
        ],
        acceptance_criteria=[
            "WHEN a request is submitted THEN it is recorded with status Pending. [REQ076, REQ077, REQ079, REQ080]",
            "IF the sick-leave balance is insufficient THEN submission is blocked with the current balance shown. [REQ078]",
            "WHEN an employee updates or cancels a request THEN the change applies only while status is Pending.",
            "WHEN the HR Head or Business Owner approves or cancels a request THEN status is updated to Approved or Cancelled and visible to the employee. [REQ033, REQ038, REQ043]",
            "WHEN the HR Head archives a resolved request THEN it is removed from active queues; the record is retained for reporting. [REQ034, REQ039, REQ044]",
            "WHEN a request is stored THEN exactly one detail row of the matching type is created (leave/overtime/cash-advance). [ADR-0002]",
            "WHEN leave entitlement changes or sick leave is consumed THEN a leave-ledger entry is appended; the balance is derived from the ledger.",
            "WHEN a cash advance is approved THEN exactly one request-linked obligation is created; each payroll repayment creates a separate repayment row.",
        ],
        db_tables=[
            ("request_type", "Lookup: Leave, Overtime, CashAdvance.", "request_type_id PK, type_name UQ"),
            ("request", "Header row for any request.", "request_id PK, employee_id FK, request_type_id FK, reason, status, submitted_at, reviewed_by NULL FK, archived_by NULL FK"),
            ("leave_request_detail", "Leave-specific fields (1:1 with request).", "request_id PK/FK, leave_type, start_date, end_date, days_requested"),
            ("overtime_request_detail", "Overtime-specific fields.", "request_id PK/FK, overtime_date, start_time, end_time, requested_minutes"),
            ("cash_advance_request_detail", "Cash-advance amount.", "request_id PK/FK, amount"),
            ("leave_entitlement", "Annual leave entitlement per employee.", "entitlement_id PK, employee_id FK, leave_type, leave_year, entitled_days DEFAULT 4.00"),
            ("leave_ledger", "Append-only balance authority for leave.", "entry_id PK, entitlement_id FK, request_id NULL FK, entry_type, days_delta"),
            ("cash_advance_history", "Approved cash-advance obligations.", "history_id PK, request_id UQ FK, original_amount, remaining_balance, status"),
            ("cash_advance_repayment", "One row per payroll repayment instalment.", "repayment_id PK, history_id FK, payroll_id FK, deduction_id UQ FK, amount"),
        ],
        key_rules=[
            "Declined requests use status Cancelled — there is no separate Rejected status. [HR interview 2026-09-21]",
            "Sick leave default entitlement: 4 paid days per year. Balance is derived from the append-only leave ledger.",
            "The HR Head and the Business Owner may both approve or cancel requests. [REQN009, REQN010]",
            "Archival metadata (archived_by, archived_at) is separate from the decision status.",
            "Cash-advance repayment is tracked through payroll deduction rows; remaining_balance is updated on each repayment.",
        ],
        php_components=[
            "RequestController — submit/update/cancel/approve/cancel/archive HTTP actions.",
            "RequestService — state transitions, leave-balance check, leave-ledger append.",
            "LeaveBalanceService — derives current balance from leave_ledger.",
            "CashAdvanceService — creates obligation, tracks repayment history.",
            "RequestRepository — PDO queries with type-specific detail joins.",
        ],
    )

    # ====================================================================
    # MODULE 8 — PAYROLL PROCESSING
    # ====================================================================
    module_section(
        8, "Payroll Processing",
        overview=(
            "The HR Head computes weekly payroll for a selected branch and period. The system "
            "derives all figures from attendance, salary history, approved requests, and approved "
            "contribution policies — no manual entry of pay amounts is permitted. The payroll run "
            "passes through states Draft → Computed → PendingOwnerApproval → Approved (or Returned). "
            "Government contributions (SSS, PhilHealth, Pag-IBIG) are deducted only on the last "
            "Friday of the month. Approved payroll, earnings, deductions, and payslips are immutable."
        ),
        user_stories=[
            "As the HR Head, I want payroll computed automatically from attendance, salary, and approved requests. [REQ047]",
            "As the HR Head, I want to submit a computed payroll run to the Business Owner for final approval. [REQ050]",
            "As the Business Owner, I want to approve or return a payroll run with a reason. [REQ051]",
            "As an Employee, I want a digital payslip generated for each pay period. [REQ048]",
        ],
        acceptance_criteria=[
            "WHEN the HR Head triggers payroll computation THEN gross pay, overtime, late/undertime deductions, cash-advance deductions, and (on last-Friday months) government contributions are calculated per employee. [REQ047]",
            "WHEN payroll computation completes THEN a digital payslip is generated per employee. [REQ048]",
            "WHEN the applicable period is reached THEN 13th-month pay is computed as total basic salary ÷ 12. [REQ049]",
            "WHEN the HR Head finishes preparing a payroll run THEN it is submitted to the Business Owner for approval. [REQ050]",
            "WHEN the Business Owner reviews a submitted payroll THEN the Owner can approve it or return it with a reason. [REQ051]",
            "WHILE a run is PendingOwnerApproval THEN it cannot be marked paid/final.",
            "WHEN payroll computation runs THEN it completes within 5 seconds. [REQN005]",
            "FOR a given payroll period and branch, at most one payroll run is permitted.",
            "FOR a given payroll period and employee, the employee appears in at most one branch run.",
            "WHEN a payroll run becomes Approved THEN all its detail rows, contribution records, and payslips are immutable. [ADR-0002]",
            "WHEN pay components are computed: overtime = hourly_rate × 1.25 × hours; regular holiday = daily_rate × 2.00; special holiday = daily_rate × 1.30; lateness deducts ₱1 per minute late. [Final Defense Reviewer, pp. 4–5]",
        ],
        db_tables=[
            ("payroll_period", "Friday-to-Thursday period with pay_date on following Friday.", "payroll_period_id PK, period_start UQ, period_end, pay_date UQ, status"),
            ("payroll_run", "Branch-level run with approval state.", "payroll_run_id PK, payroll_period_id FK, branch_id FK, payroll_policy_id FK, status, gross_pay, total_deductions, net_pay, lock_version"),
            ("payroll", "Per-employee pay header with rate snapshot.", "payroll_id PK, payroll_run_id FK, payroll_period_id FK, employee_id FK, salary_id FK, daily_rate_snapshot, gross_pay, total_deductions, net_pay"),
            ("payroll_earnings", "Itemised earnings (Basic, Overtime, RegularHoliday, SpecialHoliday, ThirteenthMonth).", "earning_id PK, payroll_id FK, earning_type, quantity, unit_rate, multiplier, amount, calculation_details JSON"),
            ("deduction", "Itemised deductions (Late, Undertime, CashAdvance, SSS, PhilHealth, PagIBIG, IncomeTax).", "deduction_id PK, payroll_id FK, deduction_type, quantity, unit_rate, amount, calculation_details JSON"),
            ("payslip", "One payslip per approved payroll row.", "payslip_id PK, payroll_id UQ FK, issue_date, file_path, generated_by FK, generated_at, content_hash"),
            ("payroll_policy_version", "Configurable payroll policy (EEMR days, tax threshold, late rate, rounding).", "policy_id PK, policy_code, version, effective_from, effective_to, eemr_days_per_year DEFAULT 313, annual_tax_threshold, demo_only, status"),
        ],
        key_rules=[
            "Payroll period: Friday start, Thursday end, pay_date = immediately following Friday. [ADR-0002]",
            "payroll_run is the sole approval-state authority; employee payroll rows do not duplicate approval state.",
            "Zero income-tax withholding while projected annual taxable income ≤ ₱250,000 (configurable threshold). [Supplemental HR answer]",
            "Government contributions are deducted only on the last Friday of the month. [Final Defense Reviewer, p. 4]",
            "Overtime on a holiday: multipliers are NOT compounded until HR confirms that policy.",
            "EEMR = (daily_rate × 313) ÷ 12; used as Monthly Basic Salary for SSS/PhilHealth computation.",
            "Payroll figures are derived—never manually entered.",
            "Approved payroll rows and all child records are immutable.",
        ],
        php_components=[
            "PayrollController — HTTP actions for generation, submission, approval/return.",
            "PayrollService — orchestrates attendance pull, salary lookup, earnings/deduction computation, payslip generation.",
            "ContributionEngine — EEMR computation, SSS/PhilHealth/PagIBIG bracket lookup, last-Friday determination.",
            "PayrollRepository / PayrollPeriodRepository — PDO persistence with composite FK enforcement.",
            "PayslipRepository — payslip record creation and file reference.",
            "DisbursementService — deposit-slip preparation list and cheque batch recording.",
        ],
    )

    # ====================================================================
    # MODULE 9 — BENEFITS AND DEDUCTIONS (GOVERNMENT CONTRIBUTIONS)
    # ====================================================================
    module_section(
        9, "Benefits and Deductions (Government Contributions)",
        overview=(
            "Manages the policy tables and computation logic for SSS, PhilHealth, and Pag-IBIG "
            "contributions. Each program is versioned in a contribution_policy_version that is "
            "approved before use. The EEMR (daily_rate × 313 ÷ 12) is the salary basis for SSS "
            "and PhilHealth. Employee shares are payroll deductions; employer shares are recorded "
            "for reporting. Contributions are posted only on the last Friday of the month. The MVP "
            "ships only the documented ADR-0001 demo fixture and rejects unsupported EEMR/policy "
            "inputs rather than inventing bracket boundaries."
        ),
        user_stories=[
            "As the HR Head, I want SSS, PhilHealth, and Pag-IBIG contributions computed automatically from current brackets so deductions are accurate and compliant. [REQ058–REQ064]",
            "As the HR Head, I want to view and, where permitted, edit contribution records. [REQ058–REQ060]",
        ],
        acceptance_criteria=[
            "WHEN payroll is computed for an employee THEN SSS contribution is computed using the salary-bracket table in effect. [REQ061, REQ062]",
            "WHEN payroll is computed THEN PhilHealth and Pag-IBIG contributions are computed per current policy rates. [REQ063, REQ064]",
            "WHEN the HR Head views contribution records THEN all employee contribution records are displayed. [REQ058]",
            "WHEN the HR Head edits a contribution record THEN the change persists, unless the record is locked. [REQ059, REQ060]",
            "WHEN SSS or PhilHealth is computed THEN EEMR = (daily_rate × 313) ÷ 12 is used as the Monthly Basic Salary basis. [Final Defense Reviewer, p. 4]",
            "WHEN the weekly pay date is NOT the last Friday THEN no SSS/PhilHealth/PagIBIG deductions are posted.",
            "WHEN the weekly pay date IS the last Friday THEN employee shares are deducted and employer shares are recorded for reporting. [Final Defense Reviewer, p. 4]",
            "WHEN a contribution is posted THEN exactly one row per payroll/program is created; the employee share is linked to exactly one deduction row. [ADR-0002]",
            "WHEN an unsupported EEMR or policy combination is encountered THEN the system fails visibly rather than extrapolating brackets. [ADR-0001]",
            "Government contributions are deductions, not benefits. [Final Defense Reviewer DFD Level 0]",
        ],
        db_tables=[
            ("contribution_policy_version", "Versioned contribution policy (Draft/Approved/Retired).", "contribution_policy_id PK, policy_code, version, effective_from, effective_to, demo_only, status"),
            ("sss_bracket", "SSS salary-range brackets with employee and employer shares.", "bracket_id PK, contribution_policy_id FK, salary_from, salary_to NULL, employee_share, employer_share, effective_from, effective_to"),
            ("philhealth_rate", "PhilHealth rate per approved policy.", "rate_id PK, contribution_policy_id UQ FK, rate_decimal, basis_floor NULL, basis_ceiling NULL, employee_share_decimal"),
            ("pagibig_rate", "Pag-IBIG rate or fixed amount per approved policy.", "rate_id PK, contribution_policy_id UQ FK, rate_decimal NULL, basis_ceiling NULL, employee_fixed_amount NULL, employer_fixed_amount NULL"),
            ("contribution_record", "Posted contribution per payroll row with locked status.", "contribution_id PK, payroll_id FK, deduction_id UQ FK, contribution_policy_id FK, contribution_type, eemr_basis, employee_share, employer_share, deduction_date, status"),
            ("salary", "Employee salary history (daily_rate, effective_from, effective_to).", "salary_id PK, employee_id FK, daily_rate, effective_from, effective_to, status"),
            ("payroll_policy_version", "Payroll policy including EEMR days per year (default 313).", "policy_id PK, eemr_days_per_year DEFAULT 313, annual_tax_threshold"),
        ],
        key_rules=[
            "EEMR formula: (daily_rate × eemr_days_per_year) ÷ 12; eemr_days_per_year defaults to 313. [Final Defense Reviewer, p. 4]",
            "SSS bracket lookup uses EEMR as Monthly Basic Salary.",
            "PhilHealth: rate_decimal applied to EEMR within basis_floor/basis_ceiling.",
            "Pag-IBIG: fixed employee/employer amounts in the approved MVP demo fixture.",
            "Contributions are posted only on the last Friday of the month.",
            "At most one contribution_record per payroll_id and contribution_type (unique constraint).",
            "Employer shares are stored in contribution_record for reporting but do not become payroll deductions.",
            "The orphan source benefit table is excluded; government contributions are deduction rows backed by contribution_record.",
            "MVP ships only the ADR-0001 demo fixture, clearly labelled demo_only = TRUE.",
        ],
        php_components=[
            "ContributionEngine — EEMR computation, isContributionDeductionRun(), SSS/PhilHealth/PagIBIG lookups.",
            "ContributionController — view/edit contribution records, lock/unlock.",
            "ContributionPolicyRepository — load approved, effective policy versions.",
            "SssBracketRepository / PhilHealthRateRepository / PagIbigRateRepository — PDO bracket/rate lookups.",
            "ContributionRecordRepository — persistence and lock enforcement.",
        ],
    )

    # ====================================================================
    # SECTION 5 — SYSTEM ARCHITECTURE
    # ====================================================================
    doc.add_page_break()
    heading(doc, "5. System Architecture and Technology", level=1)

    heading(doc, "5.1 Three-Tier Architecture", level=2)
    body(doc,
         "The solution follows a three-tier architecture: a browser-based presentation tier, "
         "a frameworkless PHP application tier, and a MySQL 8.4 database tier. Requests flow "
         "Presentation → Application → Database; results (computed salaries, reports, payslips) "
         "flow back the same path.")

    heading(doc, "5.2 Technology Stack", level=2)
    tech_tbl = doc.add_table(rows=1, cols=2)
    tech_tbl.style = "Table Grid"
    add_table_header(tech_tbl, ["Area", "Approved Implementation"])
    tech_rows = [
        ("Server-side runtime", "PHP 8.5, strict types, PSR-4 namespaces under Wbpms\\"),
        ("Application style", "Frameworkless front controller; explicit route table; thin controllers; application/domain services; PDO repositories; escaped native PHP templates"),
        ("Framework / ORM", "None — no Laravel, no full-stack framework, no ORM"),
        ("Database", "MySQL 8.4 LTS, InnoDB, utf8mb4, strict SQL mode"),
        ("Migrations", "Phinx migrations and seeders"),
        ("XLS decoding", "phpoffice/phpspreadsheet, explicit Reader\\Xls, data-only mode"),
        ("Authentication", "password_hash() / password_verify(); MySQL-backed native PHP sessions; CSRF tokens"),
        ("Testing", "PHPUnit 12, PHPStan"),
        ("Reporting", "Print-ready HTML; PDF/CSV via adapters"),
        ("Time handling", "Business time: Asia/Manila; stored instants: UTC; dates: MM/DD/YY; time: 24-hour HH:mm"),
    ]
    for i, (area, impl) in enumerate(tech_rows):
        add_data_row(tech_tbl, [area, impl], bold_first=True, shade_alt=True, row_idx=i)
    spacer(doc)

    heading(doc, "5.3 Security Controls", level=2)
    sec_items = [
        "HttpOnly, SameSite=Lax, and production Secure session cookies.",
        "CSRF tokens on all state-changing forms.",
        "Escaped native PHP templates prevent XSS.",
        "PDO prepared statements prevent SQL injection.",
        "Private file storage (storage/private/) for uploads and payslips; public/ is the sole web root.",
        "Audit logging for all significant create/update/approval/archive/access/adjustment events.",
        "Failed logins and unauthenticated events are logged with a null user reference.",
        "Passwords stored with password_hash(PASSWORD_DEFAULT); OTPs hashed before storage.",
    ]
    for item in sec_items:
        bullet(doc, item)

    # ====================================================================
    # SECTION 6 — DATA MODEL SUMMARY
    # ====================================================================
    doc.add_page_break()
    heading(doc, "6. Canonical Data Model Summary", level=1)
    body(doc,
         "The authoritative table definitions, foreign keys, enums, checks, and indexes are "
         "maintained in Canonical Database Schema v1.1. The table below provides a high-level "
         "domain grouping for reference.")

    dm_tbl = doc.add_table(rows=1, cols=3)
    dm_tbl.style = "Table Grid"
    add_table_header(dm_tbl, ["Domain", "Core Entities", "Notes"])
    dm_rows = [
        ("Identity and Audit", "role, users, sessions, password_reset_challenge, audit_logs", "Email-based login; MySQL session handler; immutable audit trail"),
        ("Organisation", "branch, attendance_site, biometric_device, biometric_device_branch", "Configurable topology; N–M device-to-branch coverage via effective dates"),
        ("Employee History", "employee, employee_branch_assignment, employee_biometric_enrollment, work_schedule, holiday_calendar", "Effective-dated relationships; transfers preserve history"),
        ("Attendance", "attendance_import_batch, biometric_punch, attendance, attendance_punch, attendance_adjustment, attendance_policy_flag", "Atomic import; raw punch lineage; one row per employee per date"),
        ("Requests", "request_type, request, leave/overtime/cash-advance details, leave_entitlement, leave_ledger, cash_advance_history, cash_advance_repayment", "Append-only leave ledger; request has exactly one typed detail row"),
        ("Salary", "salary", "Effective-dated daily rate history; no overwrite"),
        ("Contributions", "contribution_policy_version, sss_bracket, philhealth_rate, pagibig_rate, contribution_record", "Policy-versioned; EEMR basis; last-Friday deduction cadence"),
        ("Payroll", "payroll_policy_version, payroll_period, payroll_run, payroll, payroll_earnings, deduction, payslip", "Branch-run approval; immutable on Approved"),
        ("Disbursement", "bank_details, disbursement_batch, deposit_slip", "One aggregate cheque; one deposit slip per employee; BDO-only"),
    ]
    for i, (domain, entities, notes) in enumerate(dm_rows):
        add_data_row(dm_tbl, [domain, entities, notes], bold_first=True, shade_alt=True, row_idx=i)

    # ====================================================================
    # SECTION 7 — CROSS-ROW INTEGRITY RULES
    # ====================================================================
    doc.add_page_break()
    heading(doc, "7. Cross-Row Integrity Rules", level=1)
    body(doc,
         "The following rules require transactional service validation in addition to database "
         "keys and checks (from Canonical Schema v1.1):")

    integrity_rules = [
        "Lock the employee/device/policy parent key before inserting or closing an effective period; reject any intersecting half-open interval.",
        "Resolve attendance branch and schedule using attendance_date; resolve biometric identity using source_local_at; resolve payroll membership using payroll_period.period_start.",
        "A payroll.branch_assignment_id must belong to its employee and to the run's branch on period start. Its salary_id must be effective on that date.",
        "Approved payroll runs and their payroll, earning, deduction, contribution, and payslip rows are immutable.",
        "Payroll totals equal the sum of detail lines; contribution employee shares equal their linked deduction amounts; disbursement totals equal included net pay and deposit-slip amounts.",
        "Overtime earning requires both attendance evidence and an Approved overtime request. Holiday and overtime multipliers are not compounded.",
        "The contribution policy must be Approved, effective on pay_date, and able to resolve the EEMR; unsupported inputs fail visibly.",
        "A request has exactly one detail row matching its request type; archival requires a resolved/cancelled status.",
        "One current active BDO account is selected per employee; effective bank histories may not overlap for the same use case.",
    ]
    for i, rule in enumerate(integrity_rules, 1):
        p = doc.add_paragraph(f"{i}. {rule}")
        p.paragraph_format.space_after = Pt(3)

    # ====================================================================
    # SECTION 8 — NON-FUNCTIONAL REQUIREMENTS
    # ====================================================================
    doc.add_page_break()
    heading(doc, "8. Non-Functional Requirements", level=1)

    nfr_tbl = doc.add_table(rows=1, cols=3)
    nfr_tbl.style = "Table Grid"
    add_table_header(nfr_tbl, ["Category", "Requirement", "Reference"])
    nfr_rows = [
        ("Operational", "Runs on Windows 10 or later for server/admin workstations.", "REQN001"),
        ("Operational", "Accessible from Chrome, Firefox, and Edge.", "REQN002"),
        ("Performance", "Data retrieval within 1 second over a stable connection.", "REQN003"),
        ("Performance", "Dashboard loads within 5 seconds.", "REQN004"),
        ("Performance", "Payroll computation completes within 5 seconds.", "REQN005"),
        ("Performance", "Report generation completes within 5 seconds.", "REQN006"),
        ("Security", "All access restricted to authenticated, authorised users.", "REQN007"),
        ("Security", "Only the HR Head role may manage payroll.", "REQN008"),
        ("Security", "HR Head and Business Owner may approve or cancel leave, overtime, and cash-advance requests.", "REQN009, REQN010"),
        ("Security", "Hashed passwords and session/token-based authentication.", "REQN011"),
        ("Localisation", "English primary language.", "REQN012"),
        ("Localisation", "Dates displayed in MM/DD/YY format.", "REQN013"),
        ("Localisation", "Time displayed in 24-hour format.", "REQN014"),
    ]
    for i, (cat, req, ref) in enumerate(nfr_rows):
        add_data_row(nfr_tbl, [cat, req, ref], shade_alt=True, row_idx=i)

    # ====================================================================
    # SECTION 9 — TESTING STRATEGY
    # ====================================================================
    doc.add_page_break()
    heading(doc, "9. Testing Strategy", level=1)

    test_items = [
        ("Unit Tests",
         "Services with no DB dependency: AttendanceService.computeHours, "
         "ContributionEngine.computeSSS/PhilHealth/PagIBIG, PayrollService.compute13thMonthPay. "
         "Parser unit/fixture tests with synthetic .xls workbooks covering valid input, leading-zero "
         "enrollment IDs, empty/multi-punch cells, invalid headers, wrong year/month/weekday, "
         "formulas, renamed .xlsx, corrupt/truncated input, and resource-limit rejection."),
        ("Integration Tests",
         "Each controller against a seeded test database: login/RBAC enforcement, "
         "request submit→approve→archive lifecycle, shared-device multi-branch import, "
         "permanent transfer with late historical upload, and branch payroll "
         "compute→submit→approve/return lifecycle."),
        ("Performance Checks",
         "Dashboard load, payroll computation, and report generation timing against "
         "REQN003–REQN006 targets under representative data volume."),
        ("Manual / UAT",
         "Scenarios mirrored from original documentation screenshots: login, password reset, "
         "payroll submission/approval, employee payslip download, and three-role RBAC verification."),
    ]

    for test_type, description in test_items:
        heading(doc, test_type, level=2)
        body(doc, description)

    # ====================================================================
    # SECTION 10 — ACCEPTANCE VERIFICATION MATRIX
    # ====================================================================
    doc.add_page_break()
    heading(doc, "10. Acceptance Verification Matrix", level=1)

    av_tbl = doc.add_table(rows=1, cols=3)
    av_tbl.style = "Table Grid"
    add_table_header(av_tbl, ["Area", "Required Verification", "Test Type"])
    av_rows = [
        ("Access Control", "Valid login, invalid-login handling, role denial, Employee A denied Employee B data", "Integration / Manual"),
        ("XLS Import", "Valid workbook, leading-zero code, bad extension/signature, formula, invalid header/time, duplicate, unmatched, rollback", "Unit / Integration"),
        ("Attendance", "On-time, late, undertime, overtime, one-punch incomplete, multi-punch review", "Unit / Integration"),
        ("Payroll", "Golden gross/net cases, contribution boundary cases, period/branch uniqueness, immutable approval state", "Unit / Integration"),
        ("Persistence", "Repeatable migrate/seed and transaction rollback", "Integration"),
        ("User Journey", "HR setup → import → review → payroll → Owner approval → employee payslip access", "End-to-End / Manual"),
        ("Contributions", "Last-Friday detection, EEMR bracket lookup, contribution-to-deduction reconciliation", "Unit / Integration"),
        ("Reports", "Payroll, attendance, request, contributions, 13th-month; print/export; within 5 s", "Performance / Manual"),
        ("Security", "CSRF, XSS escaping, SQL injection, private file storage, session expiry", "Integration / Manual"),
    ]
    for i, (area, verification, test_type) in enumerate(av_rows):
        add_data_row(av_tbl, [area, verification, test_type], shade_alt=True, row_idx=i)

    # ====================================================================
    # SECTION 11 — IMPLEMENTATION SEQUENCE
    # ====================================================================
    doc.add_page_break()
    heading(doc, "11. Implementation Sequence", level=1)
    body(doc, "The recommended P0 delivery sequence is:")

    impl_steps = [
        "Scaffold the shared PHP/Composer runtime, environment configuration, Phinx migrations, seeders, PHPUnit, and PHPStan checks per ADR-0001 through ADR-0003 and Canonical Schema v1.1.",
        "Implement authentication, session handling, RBAC middleware, and audit logging before any business module.",
        "Wire employee, branch, schedule, biometric-device, and enrollment persistence to the HR setup forms.",
        "Connect the secure upload boundary to the LDE_XLS_DAILY_LOG_V1 parser; complete matching, raw-punch persistence, attendance generation, and HR review.",
        "Integrate the generated attendance read model with payroll calculation, salary lookup, contribution computation, and Owner approval.",
        "Implement request management (leave/overtime/cash-advance) and the Employee self-service portal.",
        "Add reports, payslip export, and deposit-slip preparation list.",
        "Perform clean-database, three-role, RBAC, browser compatibility, and acceptance testing.",
    ]
    for i, step in enumerate(impl_steps, 1):
        p = doc.add_paragraph(f"{i}. {step}")
        p.paragraph_format.space_after = Pt(3)

    # ====================================================================
    # SECTION 12 — DOCUMENT REFERENCES
    # ====================================================================
    doc.add_page_break()
    heading(doc, "12. Document References", level=1)

    refs = [
        ("Product Overview", "docs/product.md"),
        ("Requirements and Acceptance Criteria", "docs/requirements.md"),
        ("System Design", "docs/design.md"),
        ("Technology Stack", "docs/tech.md"),
        ("Canonical Database Schema v1.1", "docs/capstone_files/Canonical-Database-Schema-v1.1.md"),
        ("Revised Capstone Final v1.1", "docs/capstone_files/Capstone-1-Final-Revised-v1.1.md"),
        ("Frameworkless PHP and XLS Parser ADR (ADR-0003)", "docs/adr/0003-frameworkless-php-and-xls-parser.md"),
        ("Development Baseline ADR (ADR-0001)", "docs/adr/0001-development-baseline.md"),
        ("Schema Integrity Corrections ADR (ADR-0002)", "docs/adr/0002-schema-integrity-corrections.md"),
        ("Implementation Tasks", "docs/tasks.md"),
        ("Five-Day MVP Roadmap", "docs/five-day-development-roadmap.md"),
        ("Employee Lifecycle Specification", "docs/employee-lifecycle-archive-rehire-spec.md"),
        ("Database Schema Audit", "docs/database-schema.md"),
    ]
    ref_tbl = doc.add_table(rows=1, cols=2)
    ref_tbl.style = "Table Grid"
    add_table_header(ref_tbl, ["Document", "Path"])
    for i, (doc_name, path) in enumerate(refs):
        add_data_row(ref_tbl, [doc_name, path], shade_alt=True, row_idx=i)

    # ====================================================================
    # SAVE
    # ====================================================================
    doc.save(OUTPUT_PATH)
    print(f"Document saved: {OUTPUT_PATH}")


if __name__ == "__main__":
    build_document()
