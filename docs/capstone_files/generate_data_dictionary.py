"""
generate_data_dictionary.py
Generates the WBPMS Capstone Data Dictionary (.docx) covering all 9 module
groups and ~50 tables from Canonical Database Schema v1.1 (ADR-0002).

Format matches the original STI capstone style:
  Table Name | Description | Alias | Attribute table
  Columns: Attribute Name | Description | Data Type | Default Value |
           Primary Key | Example | Null Allowed? | Length | Validation Rule
"""

from docx import Document
from docx.shared import Pt, Inches, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import os

# ---------------------------------------------------------------------------
# Schema data — (table_name, description, alias, [(attr, desc, dtype, default,
#               pk, example, null, length, validation), ...])
# ---------------------------------------------------------------------------

GROUPS = []

# ── Group 1: Identity, Access & Audit ──────────────────────────────────────
GROUPS.append(("Group 1: Identity, Access, and Audit", [

    ("role",
     "Stores the defined system roles that control access to modules and actions.",
     "None",
     [
         ("role_id",       "Role identifier",              "BIGINT UNSIGNED AUTO_INCREMENT", "None", "Yes", "1",       "No",  "—",   "[0-9]"),
         ("role_name",     "Unique name of the role",      "VARCHAR(30)",                   "None", "No",  "HRHead",  "No",  "30",  "[A-Za-z]"),
         ("created_at",    "Record creation timestamp",    "DATETIME(0)",                   "None", "No",  "2026-01-01 08:00:00", "No", "—", "UTC datetime"),
         ("updated_at",    "Record last-update timestamp", "DATETIME(0)",                   "None", "No",  "2026-01-01 08:00:00", "No", "—", "UTC datetime"),
     ]),

    ("users",
     "Stores system user accounts, credentials, and role assignments. The Business Owner user has no linked employee row.",
     "None",
     [
         ("user_id",        "User account identifier",                          "BIGINT UNSIGNED AUTO_INCREMENT", "None",     "Yes", "1",                   "No",  "—",   "[0-9]"),
         ("employee_id",    "Linked employee record (FK → employee)",           "BIGINT UNSIGNED",                "NULL",     "No",  "1",                   "Yes", "—",   "[0-9]"),
         ("role_id",        "Assigned role (FK → role)",                        "BIGINT UNSIGNED",                "None",     "No",  "2",                   "No",  "—",   "[0-9]"),
         ("username",       "Unique login username",                            "VARCHAR(50)",                    "None",     "No",  "jgamboa",             "No",  "50",  "[A-Za-z0-9_]"),
         ("account_email",  "Unique account e-mail for password recovery",      "VARCHAR(254)",                   "None",     "No",  "jgamboa@example.com", "No",  "254", "[email]"),
         ("password_hash",  "Bcrypt/argon2 hash of the account password",       "VARCHAR(255)",                   "None",     "No",  "(hashed)",            "No",  "255", "password_hash()"),
         ("status",         "Account lifecycle status",                         "ENUM('Active','Inactive','Archived')", "'Active'", "No", "Active",         "No",  "—",   "Active|Inactive|Archived"),
         ("created_at",     "Record creation timestamp",                        "DATETIME(0)",                    "None",     "No",  "2026-01-01 08:00:00", "No",  "—",   "UTC datetime"),
         ("updated_at",     "Record last-update timestamp",                     "DATETIME(0)",                    "None",     "No",  "2026-01-01 08:00:00", "No",  "—",   "UTC datetime"),
     ]),

    ("password_reset_challenge",
     "Stores one-time OTP challenges issued for password recovery. Consumed or expired challenges are retained for audit.",
     "None",
     [
         ("challenge_id",       "Challenge identifier",                          "BIGINT UNSIGNED AUTO_INCREMENT", "None",  "Yes", "1",                   "No",  "—",   "[0-9]"),
         ("user_id",            "Account requesting reset (FK → users)",         "BIGINT UNSIGNED",                "None",  "No",  "1",                   "No",  "—",   "[0-9]"),
         ("otp_hash",           "Hashed one-time password",                      "VARCHAR(255)",                   "None",  "No",  "(hashed)",            "No",  "255", "hash string"),
         ("expires_at",         "Challenge expiry timestamp (UTC)",              "DATETIME(0)",                    "None",  "No",  "2026-01-01 08:15:00", "No",  "—",   "UTC datetime"),
         ("attempts_remaining", "Remaining verification attempts",               "TINYINT UNSIGNED",               "None",  "No",  "3",                   "No",  "—",   "[0-9]"),
         ("consumed_at",        "Timestamp when challenge was successfully used","DATETIME(0)",                    "NULL",  "No",  "2026-01-01 08:05:00", "Yes", "—",   "UTC datetime"),
         ("created_at",         "Record creation timestamp",                     "DATETIME(0)",                    "None",  "No",  "2026-01-01 08:00:00", "No",  "—",   "UTC datetime"),
     ]),

    ("sessions",
     "Stores serialized PHP session data for authenticated users. Managed by the project-owned SessionHandlerInterface.",
     "None",
     [
         ("session_id", "PHP session token (primary key)",    "VARCHAR(128)", "None", "Yes", "(token)", "No", "128", "[A-Za-z0-9]"),
         ("expires_at", "Unix timestamp of session expiry",   "BIGINT UNSIGNED", "None", "No", "1751356800", "No", "—", "[0-9]"),
         ("data",       "Serialized session payload",         "MEDIUMTEXT",   "None", "No",  "(serialized)", "No", "—", "PHP serialized string"),
     ]),

    ("audit_logs",
     "Immutable log of security-relevant and data-change events. Actor user_id is nullable to support unauthenticated failed-login events.",
     "None",
     [
         ("log_id",              "Log entry identifier",                          "BIGINT UNSIGNED AUTO_INCREMENT", "None",  "Yes", "1",                   "No",  "—",   "[0-9]"),
         ("user_id",             "Acting user (FK → users); NULL if unauthenticated", "BIGINT UNSIGNED",           "NULL",  "No",  "1",                   "Yes", "—",   "[0-9]"),
         ("event_type",          "Broad event category",                          "VARCHAR(50)",                   "None",  "No",  "LOGIN_FAILED",        "No",  "50",  "[A-Za-z_]"),
         ("action_performed",    "Specific action description",                   "VARCHAR(100)",                  "None",  "No",  "Failed login attempt","No",  "100", "[A-Za-z0-9 _]"),
         ("table_affected",      "Database table affected by the action",         "VARCHAR(64)",                   "NULL",  "No",  "users",               "Yes", "64",  "[A-Za-z_]"),
         ("record_id",           "Primary key of the affected record",            "BIGINT UNSIGNED",               "NULL",  "No",  "5",                   "Yes", "—",   "[0-9]"),
         ("attempted_identifier","Email or username attempted during auth events","VARCHAR(254)",                   "NULL",  "No",  "bad@test.com",        "Yes", "254", "[email/text]"),
         ("request_id",          "UUID of the originating HTTP request",          "CHAR(36)",                      "NULL",  "No",  "(uuid)",              "Yes", "36",  "UUID v4"),
         ("ip_address",          "Client IP address",                             "VARCHAR(45)",                   "NULL",  "No",  "192.168.1.10",        "Yes", "45",  "IPv4/IPv6"),
         ("user_agent",          "Client browser/user-agent string",              "VARCHAR(500)",                  "NULL",  "No",  "Mozilla/5.0...",      "Yes", "500", "string"),
         ("description",         "Human-readable event detail",                   "VARCHAR(1000)",                 "NULL",  "No",  "IP blocked after...", "Yes", "1000","string"),
         ("action_at",           "Exact event timestamp (UTC)",                   "DATETIME(0)",                   "None",  "No",  "2026-01-01 08:00:00", "No",  "—",   "UTC datetime"),
         ("created_at",          "Record insertion timestamp",                    "DATETIME(0)",                   "None",  "No",  "2026-01-01 08:00:00", "No",  "—",   "UTC datetime"),
     ]),
]))

# ── Group 2: Organization & Branch ─────────────────────────────────────────
GROUPS.append(("Group 2: Organization and Branch", [

    ("branch",
     "Stores organizational branches of Light Diamond Enterprises. Branch names and counts are configurable master data.",
     "None",
     [
         ("branch_id",   "Branch identifier",           "BIGINT UNSIGNED AUTO_INCREMENT",        "None",     "Yes", "1",               "No",  "—",   "[0-9]"),
         ("branch_code", "Short unique code",           "VARCHAR(20)",                           "None",     "No",  "BANGA-CS",        "No",  "20",  "[A-Za-z0-9-]"),
         ("branch_name", "Full branch name",            "VARCHAR(100)",                          "None",     "No",  "Banga Construction Supplies", "No", "100", "[A-Za-z0-9 ]"),
         ("location",    "Physical address or area",    "VARCHAR(200)",                          "NULL",     "No",  "Banga, South Cotabato", "Yes", "200", "string"),
         ("status",      "Branch operational status",   "ENUM('Active','Inactive','Archived')",  "'Active'", "No",  "Active",          "No",  "—",   "Active|Inactive|Archived"),
         ("created_at",  "Record creation timestamp",   "DATETIME(0)",                           "None",     "No",  "2026-01-01 08:00:00", "No", "—", "UTC datetime"),
         ("updated_at",  "Record last-update timestamp","DATETIME(0)",                           "None",     "No",  "2026-01-01 08:00:00", "No", "—", "UTC datetime"),
     ]),

    ("attendance_site",
     "Records the physical location of a biometric device. Distinct from organizational branch; supports multiple devices per site.",
     "None",
     [
         ("site_id",   "Site identifier",              "BIGINT UNSIGNED AUTO_INCREMENT",  "None",           "Yes", "1",             "No",  "—",  "[0-9]"),
         ("site_code", "Short unique site code",       "VARCHAR(20)",                     "None",           "No",  "BANGA",         "No",  "20", "[A-Za-z0-9-]"),
         ("site_name", "Full descriptive site name",   "VARCHAR(100)",                    "None",           "No",  "Banga Site",    "No",  "100","[A-Za-z0-9 ]"),
         ("address",   "Street or location description","VARCHAR(200)",                   "NULL",           "No",  "Banga, South Cotabato","Yes","200","string"),
         ("timezone",  "IANA timezone identifier",     "VARCHAR(50)",                     "'Asia/Manila'",  "No",  "Asia/Manila",   "No",  "50", "IANA tz string"),
         ("status",    "Operational status",           "ENUM('Active','Inactive')",       "'Active'",       "No",  "Active",        "No",  "—",  "Active|Inactive"),
         ("created_at","Record creation timestamp",    "DATETIME(0)",                     "None",           "No",  "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at","Record last-update timestamp", "DATETIME(0)",                     "None",           "No",  "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("biometric_device",
     "Registered biometric scanner. Each device belongs to one attendance site and declares its export file format.",
     "None",
     [
         ("device_id",     "Device identifier",                       "BIGINT UNSIGNED AUTO_INCREMENT",      "None",  "Yes", "1",                 "No",  "—",  "[0-9]"),
         ("site_id",       "Attendance site (FK → attendance_site)",  "BIGINT UNSIGNED",                     "None",  "No",  "1",                 "No",  "—",  "[0-9]"),
         ("device_code",   "Short unique device code",                "VARCHAR(30)",                         "None",  "No",  "DEV-BANGA-01",      "No",  "30", "[A-Za-z0-9-]"),
         ("device_name",   "Descriptive device name",                 "VARCHAR(100)",                        "NULL",  "No",  "Banga Biometric 1", "Yes", "100","string"),
         ("serial_number", "Hardware serial number",                  "VARCHAR(100)",                        "NULL",  "No",  "SN-00123",          "Yes", "100","[A-Za-z0-9-]"),
         ("file_format",   "Export file format identifier",           "VARCHAR(50)",                         "None",  "No",  "LDE_XLS_DAILY_LOG_V1","No","50","[A-Za-z0-9_]"),
         ("timezone",      "IANA timezone of the device",             "VARCHAR(50)",                         "None",  "No",  "Asia/Manila",       "No",  "50", "IANA tz string"),
         ("status",        "Device status",                           "ENUM('Active','Inactive','Retired')", "None",  "No",  "Active",            "No",  "—",  "Active|Inactive|Retired"),
         ("installed_at",  "Date the device was installed",           "DATE",                                "NULL",  "No",  "2025-01-15",        "Yes", "—",  "MM/DD/YY"),
         ("retired_at",    "Date the device was retired",             "DATE",                                "NULL",  "No",  "—",                 "Yes", "—",  "MM/DD/YY"),
         ("created_at",    "Record creation timestamp",               "DATETIME(0)",                         "None",  "No",  "2026-01-01 08:00:00","No","—", "UTC datetime"),
         ("updated_at",    "Record last-update timestamp",            "DATETIME(0)",                         "None",  "No",  "2026-01-01 08:00:00","No","—", "UTC datetime"),
     ]),

    ("biometric_device_branch",
     "Effective-dated mapping of which branches a biometric device serves. Supports one device covering multiple branches.",
     "None",
     [
         ("device_branch_id","Mapping identifier",                          "BIGINT UNSIGNED AUTO_INCREMENT","None",  "Yes","1",           "No","—","[0-9]"),
         ("device_id",       "Biometric device (FK → biometric_device)",    "BIGINT UNSIGNED",               "None",  "No", "1",           "No","—","[0-9]"),
         ("branch_id",       "Branch served (FK → branch)",                 "BIGINT UNSIGNED",               "None",  "No", "2",           "No","—","[0-9]"),
         ("effective_from",  "Start date of coverage (inclusive)",          "DATE",                          "None",  "No", "01/01/26",    "No","—","MM/DD/YY"),
         ("effective_to",    "End date of coverage (exclusive); NULL = open","DATE",                         "NULL",  "No", "—",           "Yes","—","MM/DD/YY"),
         ("status",          "Coverage status",                             "ENUM('Active','Inactive')",     "None",  "No", "Active",      "No","—","Active|Inactive"),
         ("created_at",      "Record creation timestamp",                   "DATETIME(0)",                   "None",  "No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",      "Record last-update timestamp",                "DATETIME(0)",                   "None",  "No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),
]))

# ── Group 3: Employee Master Data ──────────────────────────────────────────
GROUPS.append(("Group 3: Employee Master Data", [

    ("province",
     "Lookup table for Philippine province names referenced by employee addresses.",
     "None",
     [
         ("province_id",   "Province identifier",           "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",              "No","—","[0-9]"),
         ("province_name", "Province name",                 "VARCHAR(100)",                  "None","No", "Sultan Kudarat", "No","100","[A-Za-z ]"),
         ("created_at",    "Record creation timestamp",     "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",    "Record last-update timestamp",  "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("city",
     "Lookup table for city or municipality names referenced by employee addresses.",
     "None",
     [
         ("city_id",    "City identifier",              "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",            "No","—","[0-9]"),
         ("city_name",  "City or municipality name",    "VARCHAR(100)",                  "None","No", "Tacurong City","No","100","[A-Za-z ]"),
         ("created_at", "Record creation timestamp",    "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at", "Record last-update timestamp", "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("barangay",
     "Lookup table for barangay names referenced by employee addresses.",
     "None",
     [
         ("barangay_id",   "Barangay identifier",          "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",         "No","—","[0-9]"),
         ("barangay_name", "Barangay name",                "VARCHAR(100)",                  "None","No", "San Pablo", "No","100","[A-Za-z ]"),
         ("created_at",    "Record creation timestamp",    "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",    "Record last-update timestamp", "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("address",
     "Stores employee address information using a flat geography model (province, city, and barangay IDs directly on the address row).",
     "None",
     [
         ("address_id",   "Address identifier",                        "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",   "No", "—","[0-9]"),
         ("street",       "Street, purok, or lot description",         "VARCHAR(150)",                  "NULL","No", "Purok Katilingban","Yes","150","[A-Za-z0-9 ]"),
         ("province_id",  "Province (FK → province)",                  "BIGINT UNSIGNED",               "NULL","No", "1",   "Yes","—","[0-9]"),
         ("city_id",      "City or municipality (FK → city)",          "BIGINT UNSIGNED",               "NULL","No", "1",   "Yes","—","[0-9]"),
         ("barangay_id",  "Barangay (FK → barangay)",                  "BIGINT UNSIGNED",               "NULL","No", "1",   "Yes","—","[0-9]"),
         ("created_at",   "Record creation timestamp",                 "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",   "Record last-update timestamp",              "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("employee",
     "Master record for each employee of Light Diamond Enterprises. Combines fields from original Table 86 and Figures 163–164 per canonical reconciliation.",
     "None",
     [
         ("employee_id",           "Employee identifier",                             "BIGINT UNSIGNED AUTO_INCREMENT",             "None",  "Yes","1",                "No", "—",  "[0-9]"),
         ("employee_number",       "Unique immutable employee number",                "VARCHAR(30)",                                "None",  "No", "EMP-001",          "No", "30", "[A-Za-z0-9-]"),
         ("employee_type",         "Employment classification",                       "ENUM('Regular','Contractual')",              "None",  "No", "Regular",          "No", "—",  "Regular|Contractual"),
         ("first_name",            "Employee first name",                             "VARCHAR(100)",                               "None",  "No", "Jiane Arielle",    "No", "100","[A-Za-z ]"),
         ("middle_initial",        "Employee middle initial",                         "VARCHAR(5)",                                 "NULL",  "No", "B.",               "Yes","5",  "[A-Za-z.]"),
         ("last_name",             "Employee last name",                              "VARCHAR(100)",                               "None",  "No", "Gamboa",           "No", "100","[A-Za-z ]"),
         ("email",                 "Employee personal e-mail address",                "VARCHAR(254)",                               "NULL",  "No", "jgamboa@email.com","Yes","254","[email]"),
         ("contact_number",        "Employee mobile/phone number",                    "VARCHAR(30)",                                "NULL",  "No", "09489007196",      "Yes","30", "[0-9]"),
         ("birthdate",             "Date of birth",                                   "DATE",                                       "NULL",  "No", "04/07/96",         "Yes","—",  "MM/DD/YY"),
         ("hire_date",             "Date of employment",                              "DATE",                                       "None",  "No", "01/15/24",         "No", "—",  "MM/DD/YY"),
         ("id_picture",            "File path to employee ID photo",                  "VARCHAR(500)",                               "NULL",  "No", "storage/emp/1.png","Yes","500","[A-Za-z0-9/._-]"),
         ("address_id",            "Home address (FK → address)",                     "BIGINT UNSIGNED",                            "NULL",  "No", "1",                "Yes","—",  "[0-9]"),
         ("status",                "Employment lifecycle status",                     "ENUM('Active','Inactive','Separated','Archived')", "None","No","Active",        "No", "—",  "Active|Inactive|Separated|Archived"),
         ("sss_number",            "SSS identification number",                       "VARCHAR(30)",                                "NULL",  "No", "34-1234567-8",     "Yes","30", "[0-9-]"),
         ("philhealth_number",     "PhilHealth identification number",                "VARCHAR(30)",                                "NULL",  "No", "12-345678901-2",   "Yes","30", "[0-9-]"),
         ("pagibig_number",        "Pag-IBIG (HDMF) identification number",           "VARCHAR(30)",                                "NULL",  "No", "1234-5678-9012",   "Yes","30", "[0-9-]"),
         ("tin_number",            "BIR Tax Identification Number",                   "VARCHAR(30)",                                "NULL",  "No", "123-456-789-000",  "Yes","30", "[0-9-]"),
         ("position",              "Job title or position",                           "VARCHAR(100)",                               "None",  "No", "Sales Associate",  "No", "100","[A-Za-z ]"),
         ("contract_review_date",  "Next contract review date (contractual only)",    "DATE",                                       "NULL",  "No", "07/15/26",         "Yes","—",  "MM/DD/YY"),
         ("created_at",            "Record creation timestamp",                       "DATETIME(0)",                                "None",  "No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",            "Record last-update timestamp",                    "DATETIME(0)",                                "None",  "No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("employment_contract_review",
     "Records the outcome of each contractual employee evaluation cycle. Supports regularization, contract renewal, and separation tracking.",
     "None",
     [
         ("review_id",        "Review record identifier",                       "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",             "No", "—","[0-9]"),
         ("employee_id",      "Employee reviewed (FK → employee)",              "BIGINT UNSIGNED",               "None","No", "1",             "No", "—","[0-9]"),
         ("review_due_date",  "Scheduled review date",                          "DATE",                          "None","No", "07/15/26",      "No", "—","MM/DD/YY"),
         ("outcome",          "Review result",                                  "ENUM('Regularized','Renewed','Separated')","None","No","Regularized","No","—","Regularized|Renewed|Separated"),
         ("effective_date",   "Date the outcome takes effect",                  "DATE",                          "None","No", "07/15/26",      "No", "—","MM/DD/YY"),
         ("next_review_date", "Next review date if renewed",                    "DATE",                          "NULL","No", "01/15/27",      "Yes","—","MM/DD/YY"),
         ("reviewed_by",      "HR user who recorded the outcome (FK → users)",  "BIGINT UNSIGNED",               "NULL","No", "2",             "Yes","—","[0-9]"),
         ("notes",            "Additional notes on the review",                 "VARCHAR(1000)",                 "NULL","No", "Regularized per HR memo","Yes","1000","string"),
         ("created_at",       "Record creation timestamp",                      "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("employee_branch_assignment",
     "Effective-dated history of which branch each employee belongs to. Enables auditable transfers without rewriting historical records.",
     "None",
     [
         ("branch_assignment_id","Assignment identifier",                           "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",          "No", "—","[0-9]"),
         ("employee_id",         "Employee (FK → employee)",                        "BIGINT UNSIGNED",               "None","No", "1",          "No", "—","[0-9]"),
         ("branch_id",           "Branch assigned to (FK → branch)",               "BIGINT UNSIGNED",               "None","No", "2",          "No", "—","[0-9]"),
         ("effective_from",      "Start date of assignment (inclusive)",            "DATE",                          "None","No", "01/15/24",   "No", "—","MM/DD/YY"),
         ("effective_to",        "End date of assignment (exclusive); NULL = current","DATE",                        "NULL","No", "—",          "Yes","—","MM/DD/YY"),
         ("transfer_reason",     "Reason for branch transfer",                      "VARCHAR(255)",                  "NULL","No", "Operational realignment","Yes","255","string"),
         ("transferred_by",      "HR user who recorded the transfer (FK → users)",  "BIGINT UNSIGNED",               "NULL","No", "2",          "Yes","—","[0-9]"),
         ("created_at",          "Record creation timestamp",                       "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("employee_biometric_enrollment",
     "Maps each employee to a biometric device enrollment code with effective-dated validity. Used to match punch records to employees during import.",
     "None",
     [
         ("enrollment_id",        "Enrollment identifier",                             "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",         "No", "—","[0-9]"),
         ("employee_id",          "Employee (FK → employee)",                          "BIGINT UNSIGNED",               "None","No", "1",         "No", "—","[0-9]"),
         ("device_id",            "Biometric device (FK → biometric_device)",          "BIGINT UNSIGNED",               "None","No", "1",         "No", "—","[0-9]"),
         ("device_employee_code", "Enrollment ID on the device (preserves leading zeros)","VARCHAR(50)",               "None","No", "00042",      "No", "50","[0-9]"),
         ("effective_from",       "Start date of enrollment (inclusive)",              "DATE",                          "None","No", "01/15/24",  "No", "—","MM/DD/YY"),
         ("effective_to",         "End date of enrollment (exclusive); NULL = active", "DATE",                          "NULL","No", "—",         "Yes","—","MM/DD/YY"),
         ("status",               "Enrollment status",                                 "ENUM('Active','Inactive')",     "None","No", "Active",    "No", "—","Active|Inactive"),
         ("created_at",           "Record creation timestamp",                         "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",           "Record last-update timestamp",                      "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),
]))

# ── Group 4: Work Schedule & Holidays ──────────────────────────────────────
GROUPS.append(("Group 4: Work Schedule and Holidays", [

    ("work_schedule",
     "Stores the effective-dated work schedule assigned to each employee, including shift times, break, and working/rest days.",
     "None",
     [
         ("schedule_id",      "Schedule identifier",                         "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",              "No","—","[0-9]"),
         ("employee_id",      "Employee (FK → employee)",                   "BIGINT UNSIGNED",               "None","No", "1",              "No","—","[0-9]"),
         ("working_days",     "JSON array of working day names",            "JSON",                          "None","No", "[\"Mon\",\"Tue\"]","No","—","JSON array"),
         ("rest_days",        "JSON array of rest day names",               "JSON",                          "None","No", "[\"Sat\",\"Sun\"]","No","—","JSON array"),
         ("break_minutes",    "Scheduled break duration in minutes",        "SMALLINT UNSIGNED",             "None","No", "60",             "No","—","[0-9]"),
         ("work_start_time",  "Scheduled start time",                       "TIME",                          "None","No", "08:00:00",       "No","—","HH:MM:SS"),
         ("work_end_time",    "Scheduled end time",                         "TIME",                          "None","No", "17:00:00",       "No","—","HH:MM:SS"),
         ("standard_minutes", "Expected paid working minutes per day",      "SMALLINT UNSIGNED",             "None","No", "480",            "No","—","[0-9]"),
         ("effective_from",   "Start date of this schedule (inclusive)",    "DATE",                          "None","No", "01/01/26",       "No","—","MM/DD/YY"),
         ("effective_to",     "End date of this schedule (exclusive); NULL = current","DATE",                "NULL","No", "—",              "Yes","—","MM/DD/YY"),
         ("status",           "Schedule status",                            "ENUM('Active','Archived')",     "None","No", "Active",         "No","—","Active|Archived"),
         ("created_at",       "Record creation timestamp",                  "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",       "Record last-update timestamp",               "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("holiday_calendar",
     "Stores declared public holidays with their type and pay multiplier used in payroll computation. Regular holiday = 2.00x; Special = 1.30x.",
     "None",
     [
         ("holiday_id",    "Holiday identifier",              "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",              "No","—","[0-9]"),
         ("holiday_date",  "Date of the holiday",             "DATE",                          "None","No", "01/01/26",       "No","—","MM/DD/YY"),
         ("description",   "Name or description of holiday",  "VARCHAR(100)",                  "None","No", "New Year's Day", "No","100","[A-Za-z0-9 ]"),
         ("holiday_type",  "Classification of the holiday",   "ENUM('Regular','Special')",     "None","No", "Regular",        "No","—","Regular|Special"),
         ("pay_multiplier","Pay rate multiplier when worked", "DECIMAL(4,2)",                  "None","No", "2.00",           "No","—","[0-9.]"),
         ("status",        "Calendar entry status",           "ENUM('Active','Inactive')",     "None","No", "Active",         "No","—","Active|Inactive"),
         ("created_at",    "Record creation timestamp",       "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",    "Record last-update timestamp",    "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),
]))

# ── Group 5: Attendance Import & Timesheets ─────────────────────────────────
GROUPS.append(("Group 5: Attendance Import and Timesheets", [

    ("attendance_import_batch",
     "Audit record for each uploaded biometric .xls workbook. Tracks file identity, processing status, and punch counts. One unique batch per file checksum.",
     "None",
     [
         ("import_batch_id",   "Batch identifier",                                "BIGINT UNSIGNED AUTO_INCREMENT",          "None",  "Yes","1",                    "No","—","[0-9]"),
         ("device_id",         "Source biometric device (FK → biometric_device)", "BIGINT UNSIGNED",                         "None",  "No", "1",                    "No","—","[0-9]"),
         ("uploaded_by",       "HR user who uploaded the file (FK → users)",      "BIGINT UNSIGNED",                         "None",  "No", "2",                    "No","—","[0-9]"),
         ("file_name",         "Original uploaded file name",                     "VARCHAR(255)",                            "None",  "No", "attendance_sep2026.xls","No","255","[A-Za-z0-9_.-]"),
         ("file_checksum",     "SHA-256 checksum of the uploaded file",           "CHAR(64)",                                "None",  "No", "(sha256 hex)",          "No","64","[a-f0-9]"),
         ("source_year",       "Calendar year the workbook covers",               "SMALLINT UNSIGNED",                       "None",  "No", "2026",                  "No","—","[0-9]{4}"),
         ("source_month",      "Calendar month the workbook covers (1–12)",       "TINYINT UNSIGNED",                        "None",  "No", "9",                     "No","—","[1-12]"),
         ("parser_version",    "Parser identifier used for this import",          "VARCHAR(50)",                             "None",  "No", "LDE_XLS_DAILY_LOG_V1",  "No","50","[A-Za-z0-9_]"),
         ("status",            "Import processing status",                        "ENUM('Processing','Completed','Rejected')","None", "No", "Completed",             "No","—","Processing|Completed|Rejected"),
         ("records_parsed",    "Total punch tokens extracted",                    "INT UNSIGNED",                            "0",     "No", "480",                   "No","—","[0-9]"),
         ("records_matched",   "Punch tokens matched to employees",               "INT UNSIGNED",                            "0",     "No", "470",                   "No","—","[0-9]"),
         ("records_unmatched", "Punch tokens not matched to any employee",        "INT UNSIGNED",                            "0",     "No", "5",                     "No","—","[0-9]"),
         ("duplicates_skipped","Duplicate punch tokens skipped",                  "INT UNSIGNED",                            "0",     "No", "2",                     "No","—","[0-9]"),
         ("incomplete_days",   "Attendance rows flagged as incomplete",           "INT UNSIGNED",                            "0",     "No", "3",                     "No","—","[0-9]"),
         ("multi_punch_days",  "Days with more than two punches requiring review","INT UNSIGNED",                            "0",     "No", "1",                     "No","—","[0-9]"),
         ("uploaded_at",       "Timestamp when file was uploaded (UTC)",          "DATETIME(0)",                             "None",  "No", "2026-10-01 08:00:00",   "No","—","UTC datetime"),
         ("completed_at",      "Timestamp when processing finished (UTC)",        "DATETIME(0)",                             "NULL",  "No", "2026-10-01 08:05:00",   "Yes","—","UTC datetime"),
     ]),

    ("biometric_punch",
     "Immutable record of each raw punch token from an imported workbook. Unmatched and multi-punch records are preserved here for HR review.",
     "None",
     [
         ("punch_id",              "Punch identifier",                                    "BIGINT UNSIGNED AUTO_INCREMENT",                    "None","Yes","1",                  "No","—","[0-9]"),
         ("import_batch_id",       "Parent import batch (FK → attendance_import_batch)", "BIGINT UNSIGNED",                                   "None","No", "1",                  "No","—","[0-9]"),
         ("device_id",             "Source device (FK → biometric_device)",              "BIGINT UNSIGNED",                                   "None","No", "1",                  "No","—","[0-9]"),
         ("employee_id",           "Matched employee (FK → employee); NULL if unmatched","BIGINT UNSIGNED",                                   "NULL","No", "1",                  "Yes","—","[0-9]"),
         ("branch_assignment_id",  "Resolved branch assignment (FK → employee_branch_assignment)","BIGINT UNSIGNED",                          "NULL","No", "1",                  "Yes","—","[0-9]"),
         ("device_employee_code",  "Enrollment ID as it appears in the workbook",        "VARCHAR(50)",                                       "None","No", "00042",              "No","50","[0-9]"),
         ("source_local_at",       "Punch time in the device's local timezone",          "DATETIME(0)",                                       "None","No", "2026-09-02 08:01:00","No","—","local datetime"),
         ("punched_at_utc",        "Punch time converted to UTC",                        "DATETIME(0)",                                       "None","No", "2026-09-02 00:01:00","No","—","UTC datetime"),
         ("punch_type",            "Punch direction if determinable",                    "ENUM('In','Out','Unknown')",                         "NULL","No", "In",                 "Yes","—","In|Out|Unknown"),
         ("device_transaction_id", "Device-assigned transaction ID if available",        "VARCHAR(50)",                                       "NULL","No", "TXN001",             "Yes","50","[A-Za-z0-9]"),
         ("match_status",          "Outcome of employee matching",                       "ENUM('matched','unmatched','coverage_exception','duplicate')","None","No","matched","No","—","matched|unmatched|coverage_exception|duplicate"),
         ("source_department",     "Dept column value from workbook (evidence only)",    "VARCHAR(100)",                                      "NULL","No", "Construction",       "Yes","100","string"),
         ("source_user_id",        "User ID column from workbook (evidence only)",       "VARCHAR(50)",                                       "NULL","No", "42",                 "Yes","50","string"),
         ("source_employee_name",  "Name column from workbook (evidence only)",          "VARCHAR(150)",                                      "NULL","No", "Gamboa, Jiane",      "Yes","150","string"),
         ("raw_record",            "Full raw cell value as extracted from the workbook", "TEXT",                                              "None","No", "08:01 17:03",        "No","—","string"),
         ("source_workbook_row",   "Row number in the source workbook",                  "INT UNSIGNED",                                      "None","No", "5",                  "No","—","[0-9]"),
         ("source_date_column",    "Date column header from the workbook",               "VARCHAR(20)",                                       "None","No", "09/02 Wed",          "No","20","string"),
         ("resolved_by",           "HR user who resolved an unmatched punch (FK → users)","BIGINT UNSIGNED",                                 "NULL","No", "2",                  "Yes","—","[0-9]"),
         ("resolved_at",           "Timestamp of HR resolution (UTC)",                   "DATETIME(0)",                                      "NULL","No", "2026-10-01 09:00:00","Yes","—","UTC datetime"),
         ("created_at",            "Record creation timestamp",                          "DATETIME(0)",                                       "None","No", "2026-10-01 08:01:00","No","—","UTC datetime"),
     ]),

    ("attendance",
     "One row per employee per calendar day. The authoritative timesheet grain for payroll. Unique per employee and attendance_date.",
     "None",
     [
         ("attendance_id",        "Attendance identifier",                             "BIGINT UNSIGNED AUTO_INCREMENT",                "None","Yes","1",                  "No","—","[0-9]"),
         ("employee_id",          "Employee (FK → employee)",                          "BIGINT UNSIGNED",                              "None","No", "1",                  "No","—","[0-9]"),
         ("branch_assignment_id", "Branch assignment on this date (FK → employee_branch_assignment)","BIGINT UNSIGNED",               "None","No", "1",                  "No","—","[0-9]"),
         ("schedule_id",          "Work schedule on this date (FK → work_schedule)",   "BIGINT UNSIGNED",                              "None","No", "1",                  "No","—","[0-9]"),
         ("attendance_date",      "Calendar date of attendance",                       "DATE",                                         "None","No", "09/02/26",           "No","—","MM/DD/YY"),
         ("time_in",              "Recorded time-in",                                  "TIME",                                         "NULL","No", "08:01:00",           "Yes","—","HH:MM:SS"),
         ("time_out",             "Recorded time-out",                                 "TIME",                                         "NULL","No", "17:03:00",           "Yes","—","HH:MM:SS"),
         ("hours_worked_minutes", "Total worked minutes (integer)",                    "SMALLINT UNSIGNED",                            "0",   "No", "482",               "No","—","[0-9]"),
         ("late_minutes",         "Late arrival minutes (integer)",                    "SMALLINT UNSIGNED",                            "0",   "No", "1",                 "No","—","[0-9]"),
         ("undertime_minutes",    "Early departure minutes (integer)",                 "SMALLINT UNSIGNED",                            "0",   "No", "0",                 "No","—","[0-9]"),
         ("overtime_minutes",     "Overtime minutes worked (integer)",                 "SMALLINT UNSIGNED",                            "0",   "No", "3",                 "No","—","[0-9]"),
         ("status",               "Attendance entry status",                           "ENUM('Complete','Incomplete','ReviewRequired','Approved')","None","No","Complete","No","—","Complete|Incomplete|ReviewRequired|Approved"),
         ("source",               "How the entry was created",                         "ENUM('xls_import','manual')",                  "None","No", "xls_import",        "No","—","xls_import|manual"),
         ("import_batch_id",      "Source import batch (FK → attendance_import_batch); NULL for manual","BIGINT UNSIGNED",            "NULL","No", "1",                  "Yes","—","[0-9]"),
         ("created_at",           "Record creation timestamp",                         "DATETIME(0)",                                  "None","No", "2026-10-01 08:05:00","No","—","UTC datetime"),
         ("updated_at",           "Record last-update timestamp",                      "DATETIME(0)",                                  "None","No", "2026-10-01 08:05:00","No","—","UTC datetime"),
     ]),

    ("attendance_punch",
     "Links each attendance timesheet row to the exact raw punch(es) used as evidence. Preserves full lineage for audit.",
     "None",
     [
         ("attendance_id", "Attendance row (FK → attendance; part of composite PK)", "BIGINT UNSIGNED","None","Yes","1","No","—","[0-9]"),
         ("punch_id",      "Raw punch (FK → biometric_punch; unique; part of PK)",  "BIGINT UNSIGNED","None","Yes","1","No","—","[0-9]"),
         ("evidence_role", "Role this punch plays in the timesheet row",             "ENUM('TimeIn','Intermediate','TimeOut','Unclassified')","None","No","TimeIn","No","—","TimeIn|Intermediate|TimeOut|Unclassified"),
         ("created_at",    "Record creation timestamp",                              "DATETIME(0)",    "None","No", "2026-10-01 08:05:00","No","—","UTC datetime"),
     ]),

    ("attendance_adjustment",
     "Records auditable manual corrections to an attendance entry. Original imported punch data is never overwritten.",
     "None",
     [
         ("adjustment_id",  "Adjustment identifier",                        "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",                  "No","—","[0-9]"),
         ("attendance_id",  "Attendance row adjusted (FK → attendance)",    "BIGINT UNSIGNED",               "None","No", "1",                  "No","—","[0-9]"),
         ("adjusted_by",    "HR user who made the adjustment (FK → users)", "BIGINT UNSIGNED",               "NULL","No", "2",                  "Yes","—","[0-9]"),
         ("old_time_in",    "Time-in before adjustment",                    "TIME",                          "NULL","No", "08:01:00",           "Yes","—","HH:MM:SS"),
         ("new_time_in",    "Time-in after adjustment",                     "TIME",                          "NULL","No", "08:00:00",           "Yes","—","HH:MM:SS"),
         ("old_time_out",   "Time-out before adjustment",                   "TIME",                          "NULL","No", "17:03:00",           "Yes","—","HH:MM:SS"),
         ("new_time_out",   "Time-out after adjustment",                    "TIME",                          "NULL","No", "17:00:00",           "Yes","—","HH:MM:SS"),
         ("adjustment_type","Category of adjustment",                       "VARCHAR(50)",                   "None","No", "TimeCorrection",      "No","50","[A-Za-z]"),
         ("reason",         "Justification for the adjustment",             "VARCHAR(1000)",                 "None","No", "Biometric misread",  "No","1000","string"),
         ("adjustment_at",  "Timestamp when adjustment was made (UTC)",     "DATETIME(0)",                   "None","No", "2026-10-02 09:00:00","No","—","UTC datetime"),
         ("created_at",     "Record creation timestamp",                    "DATETIME(0)",                   "None","No", "2026-10-02 09:00:00","No","—","UTC datetime"),
     ]),

    ("attendance_policy_flag",
     "HR-review alert raised when an employee crosses a tardiness or absence threshold. The system flags only; it does not automatically suspend or terminate.",
     "None",
     [
         ("flag_id",        "Flag identifier",                               "BIGINT UNSIGNED AUTO_INCREMENT",                                       "None","Yes","1",                  "No","—","[0-9]"),
         ("employee_id",    "Employee flagged (FK → employee)",              "BIGINT UNSIGNED",                                                      "None","No", "1",                  "No","—","[0-9]"),
         ("flag_type",      "Type of policy threshold crossed",              "ENUM('ConsecutiveLate','TardinessMemoCap','TwoWeekAbsence','ConsecutiveAWOL')","None","No","ConsecutiveLate","No","—","see ENUM"),
         ("triggering_date","Date the threshold was reached",                "DATE",                                                                 "None","No", "09/30/26",           "No","—","MM/DD/YY"),
         ("status",         "Review status of the flag",                    "ENUM('Pending','Reviewed','Closed')",                                  "None","No", "Pending",            "No","—","Pending|Reviewed|Closed"),
         ("reviewed_by",    "HR user who reviewed (FK → users)",            "BIGINT UNSIGNED",                                                      "NULL","No", "2",                  "Yes","—","[0-9]"),
         ("reviewed_at",    "Timestamp of HR review (UTC)",                 "DATETIME(0)",                                                          "NULL","No", "2026-10-01 10:00:00","Yes","—","UTC datetime"),
         ("action_taken",   "Description of HR action taken",               "VARCHAR(255)",                                                         "NULL","No", "Verbal warning issued","Yes","255","string"),
         ("notes",          "Additional HR notes",                          "TEXT",                                                                 "NULL","No", "—",                  "Yes","—","string"),
         ("created_at",     "Record creation timestamp",                    "DATETIME(0)",                                                          "None","No", "2026-10-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",     "Record last-update timestamp",                 "DATETIME(0)",                                                          "None","No", "2026-10-01 08:00:00","No","—","UTC datetime"),
     ]),
]))

# ── Group 6: Requests, Leave & Cash Advances ───────────────────────────────
GROUPS.append(("Group 6: Requests, Leave, and Cash Advances", [

    ("request_type",
     "Lookup table for the three supported request categories. Managed by HR; only active types are available to employees.",
     "None",
     [
         ("request_type_id","Request type identifier",          "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",        "No","—","[0-9]"),
         ("type_name",      "Request category name",           "ENUM('Leave','Overtime','CashAdvance')","None","No","Leave","No","—","Leave|Overtime|CashAdvance"),
         ("status",         "Whether this type is available",  "ENUM('Active','Inactive')",     "None","No", "Active",  "No","—","Active|Inactive"),
         ("created_at",     "Record creation timestamp",       "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",     "Record last-update timestamp",    "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("request",
     "Parent record for all employee requests. Type-specific details are stored in child tables. Declined requests use status Cancelled (no separate Rejected status).",
     "None",
     [
         ("request_id",      "Request identifier",                            "BIGINT UNSIGNED AUTO_INCREMENT",                       "None","Yes","1",                   "No","—","[0-9]"),
         ("employee_id",     "Requesting employee (FK → employee)",           "BIGINT UNSIGNED",                                      "None","No", "1",                   "No","—","[0-9]"),
         ("request_type_id", "Request category (FK → request_type)",         "BIGINT UNSIGNED",                                      "None","No", "1",                   "No","—","[0-9]"),
         ("reason",          "Employee-provided reason for the request",      "VARCHAR(1000)",                                        "None","No", "Medical consultation","No","1000","string"),
         ("status",          "Current lifecycle status of the request",       "ENUM('Pending','Approved','Rejected','Cancelled')",    "None","No", "Pending",             "No","—","Pending|Approved|Rejected|Cancelled"),
         ("submitted_at",    "Timestamp when request was submitted (UTC)",    "DATETIME(0)",                                          "None","No", "2026-09-28 09:00:00", "No","—","UTC datetime"),
         ("reviewed_by",     "HR or Owner who reviewed (FK → users)",        "BIGINT UNSIGNED",                                      "NULL","No", "2",                   "Yes","—","[0-9]"),
         ("reviewed_at",     "Timestamp of review decision (UTC)",           "DATETIME(0)",                                          "NULL","No", "2026-09-29 10:00:00", "Yes","—","UTC datetime"),
         ("review_notes",    "Notes from the reviewer",                      "VARCHAR(1000)",                                        "NULL","No", "Approved, documented","Yes","1000","string"),
         ("archived_by",     "User who archived the request (FK → users)",   "BIGINT UNSIGNED",                                      "NULL","No", "2",                   "Yes","—","[0-9]"),
         ("archived_at",     "Timestamp of archival (UTC)",                  "DATETIME(0)",                                          "NULL","No", "—",                   "Yes","—","UTC datetime"),
         ("created_at",      "Record creation timestamp",                    "DATETIME(0)",                                          "None","No", "2026-09-28 09:00:00", "No","—","UTC datetime"),
         ("updated_at",      "Record last-update timestamp",                 "DATETIME(0)",                                          "None","No", "2026-09-29 10:00:00", "No","—","UTC datetime"),
     ]),

    ("leave_request_detail",
     "Type-specific detail for Leave requests. One row per approved leave request. Primary key is the parent request_id.",
     "None",
     [
         ("request_id",     "Parent request (PK + FK → request)",   "BIGINT UNSIGNED","None","Yes","1",        "No","—","[0-9]"),
         ("leave_type",     "Type of leave",                        "ENUM('Sick')",   "None","No", "Sick",     "No","—","Sick"),
         ("start_date",     "First day of leave",                   "DATE",           "None","No", "09/30/26", "No","—","MM/DD/YY"),
         ("end_date",       "Last day of leave",                    "DATE",           "None","No", "09/30/26", "No","—","MM/DD/YY"),
         ("days_requested", "Number of leave days requested",       "DECIMAL(5,2)",   "None","No", "1.00",     "No","—","[0-9.]"),
     ]),

    ("overtime_request_detail",
     "Type-specific detail for Overtime requests. One row per approved overtime request. Primary key is the parent request_id.",
     "None",
     [
         ("request_id",        "Parent request (PK + FK → request)", "BIGINT UNSIGNED",  "None","Yes","1",         "No","—","[0-9]"),
         ("overtime_date",     "Date of the overtime work",          "DATE",             "None","No", "09/30/26",  "No","—","MM/DD/YY"),
         ("start_time",        "Overtime start time",                "TIME",             "None","No", "17:00:00",  "No","—","HH:MM:SS"),
         ("end_time",          "Overtime end time",                  "TIME",             "None","No", "19:00:00",  "No","—","HH:MM:SS"),
         ("requested_minutes", "Total overtime minutes requested",   "SMALLINT UNSIGNED","None","No", "120",       "No","—","[0-9]"),
     ]),

    ("cash_advance_request_detail",
     "Type-specific detail for Cash Advance requests. One row per cash advance request. Primary key is the parent request_id.",
     "None",
     [
         ("request_id","Parent request (PK + FK → request)","BIGINT UNSIGNED","None","Yes","1",     "No","—","[0-9]"),
         ("amount",    "Cash advance amount requested",      "DECIMAL(12,2)", "None","No", "1500.00","No","—","[0-9.]"),
     ]),

    ("leave_entitlement",
     "Annual leave balance grant per employee per leave type. The default sick leave entitlement is 4 days per year.",
     "None",
     [
         ("entitlement_id", "Entitlement identifier",                  "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",     "No","—","[0-9]"),
         ("employee_id",    "Employee (FK → employee)",                "BIGINT UNSIGNED",               "None","No", "1",     "No","—","[0-9]"),
         ("leave_type",     "Leave type granted",                      "ENUM('Sick')",                  "None","No", "Sick",  "No","—","Sick"),
         ("leave_year",     "Calendar year of the entitlement",        "SMALLINT UNSIGNED",             "None","No", "2026",  "No","—","[0-9]{4}"),
         ("entitled_days",  "Total days granted for the year",         "DECIMAL(5,2)",                  "4.00","No", "4.00",  "No","—","[0-9.]"),
         ("created_at",     "Record creation timestamp",               "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",     "Record last-update timestamp",            "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("leave_ledger",
     "Append-only ledger of leave balance changes. Used to derive current balance without overwriting history.",
     "None",
     [
         ("entry_id",       "Ledger entry identifier",                            "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",      "No","—","[0-9]"),
         ("entitlement_id", "Leave entitlement this entry applies to (FK → leave_entitlement)","BIGINT UNSIGNED","None","No","1",         "No","—","[0-9]"),
         ("request_id",     "Linked request if this is a usage entry (FK → request)","BIGINT UNSIGNED",           "NULL","No", "5",       "Yes","—","[0-9]"),
         ("entry_type",     "Type of ledger movement",                            "ENUM('Grant','Usage','Adjustment','Reversal')","None","No","Usage","No","—","Grant|Usage|Adjustment|Reversal"),
         ("days_delta",     "Days added (positive) or deducted (negative)",       "DECIMAL(5,2)",                  "None","No", "-1.00",   "No","—","[0-9.-]"),
         ("recorded_by",    "User who recorded this entry (FK → users)",          "BIGINT UNSIGNED",               "NULL","No", "2",       "Yes","—","[0-9]"),
         ("notes",          "Reason or context for this ledger entry",            "VARCHAR(500)",                  "NULL","No", "Sick leave used","Yes","500","string"),
         ("created_at",     "Record creation timestamp",                          "DATETIME(0)",                   "None","No", "2026-09-30 08:00:00","No","—","UTC datetime"),
     ]),

    ("cash_advance_history",
     "Tracks the outstanding balance of each approved cash advance obligation until fully repaid or cancelled.",
     "None",
     [
         ("history_id",       "Cash advance history identifier",              "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",      "No","—","[0-9]"),
         ("request_id",       "Source request (FK → request; unique)",        "BIGINT UNSIGNED",               "None","No", "3",      "No","—","[0-9]"),
         ("employee_id",      "Employee obligated (FK → employee)",           "BIGINT UNSIGNED",               "None","No", "1",      "No","—","[0-9]"),
         ("original_amount",  "Amount originally approved",                   "DECIMAL(12,2)",                 "None","No", "1500.00","No","—","[0-9.]"),
         ("remaining_balance","Outstanding balance",                          "DECIMAL(12,2)",                 "None","No", "500.00", "No","—","[0-9.]"),
         ("status",           "Repayment status",                             "ENUM('Active','Paid','Cancelled')","None","No","Active","No","—","Active|Paid|Cancelled"),
         ("approved_at",      "Timestamp when request was approved (UTC)",    "DATETIME(0)",                   "None","No", "2026-09-29 10:00:00","No","—","UTC datetime"),
         ("created_at",       "Record creation timestamp",                    "DATETIME(0)",                   "None","No", "2026-09-29 10:00:00","No","—","UTC datetime"),
         ("updated_at",       "Record last-update timestamp",                 "DATETIME(0)",                   "None","No", "2026-10-03 08:00:00","No","—","UTC datetime"),
     ]),

    ("cash_advance_repayment",
     "Records each weekly payroll deduction that repays a cash advance obligation. Linked to both the payroll row and the exact deduction line.",
     "None",
     [
         ("repayment_id", "Repayment identifier",                               "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",     "No","—","[0-9]"),
         ("history_id",   "Cash advance obligation (FK → cash_advance_history)","BIGINT UNSIGNED",               "None","No", "1",     "No","—","[0-9]"),
         ("payroll_id",   "Payroll row this deduction was applied to (FK → payroll)","BIGINT UNSIGNED",          "None","No", "10",    "No","—","[0-9]"),
         ("deduction_id", "Exact deduction line (FK → deduction; unique)",      "BIGINT UNSIGNED",               "None","No", "15",    "No","—","[0-9]"),
         ("amount",       "Amount deducted in this repayment",                  "DECIMAL(12,2)",                 "None","No", "500.00","No","—","[0-9.]"),
         ("created_at",   "Record creation timestamp",                          "DATETIME(0)",                   "None","No", "2026-10-03 08:00:00","No","—","UTC datetime"),
     ]),
]))

# ── Group 7: Salary & Payroll Policy ───────────────────────────────────────
GROUPS.append(("Group 7: Salary and Payroll Policy", [

    ("salary",
     "Effective-dated daily rate history per employee. The sole authoritative wage source; employee table does not duplicate the rate.",
     "None",
     [
         ("salary_id",     "Salary record identifier",                  "BIGINT UNSIGNED AUTO_INCREMENT","None",  "Yes","1",       "No","—","[0-9]"),
         ("employee_id",   "Employee (FK → employee)",                  "BIGINT UNSIGNED",               "None",  "No", "1",       "No","—","[0-9]"),
         ("daily_rate",    "Daily wage rate",                           "DECIMAL(12,2)",                 "None",  "No", "460.00",  "No","—","[0-9.]"),
         ("effective_from","Start date of this rate (inclusive)",       "DATE",                          "None",  "No", "01/01/26","No","—","MM/DD/YY"),
         ("effective_to",  "End date of this rate (exclusive); NULL = current","DATE",                  "NULL",  "No", "—",       "Yes","—","MM/DD/YY"),
         ("status",        "Record status",                             "ENUM('Active','Archived')",     "None",  "No", "Active",  "No","—","Active|Archived"),
         ("created_by",    "HR user who entered this rate (FK → users)","BIGINT UNSIGNED",               "NULL",  "No", "2",       "Yes","—","[0-9]"),
         ("created_at",    "Record creation timestamp",                 "DATETIME(0)",                   "None",  "No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",    "Record last-update timestamp",              "DATETIME(0)",                   "None",  "No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("payroll_policy_version",
     "Versioned payroll computation parameters (EEMR days, tax threshold, late rate, rounding mode). Only one Approved version is active per period.",
     "None",
     [
         ("policy_id",             "Policy version identifier",                      "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",           "No","—","[0-9]"),
         ("policy_code",           "Short code identifying the policy family",       "VARCHAR(50)",                   "None","No", "WBPMS-PAY",    "No","50","[A-Za-z0-9-]"),
         ("version",               "Version label",                                  "VARCHAR(30)",                   "None","No", "v1.0",         "No","30","[A-Za-z0-9.]"),
         ("effective_from",        "Start date of this version",                     "DATE",                          "None","No", "01/01/26",     "No","—","MM/DD/YY"),
         ("effective_to",          "End date; NULL = current",                       "DATE",                          "NULL","No", "—",            "Yes","—","MM/DD/YY"),
         ("eemr_days_per_year",    "Working days used in EEMR formula",              "SMALLINT UNSIGNED",             "313", "No", "313",          "No","—","[0-9]"),
         ("annual_tax_threshold",  "Annual income threshold below which no tax is withheld","DECIMAL(12,2)",          "None","No", "250000.00",    "No","—","[0-9.]"),
         ("late_rate_per_minute",  "Peso deduction per late minute",                 "DECIMAL(12,2)",                 "1.00","No", "1.00",         "No","—","[0-9.]"),
         ("rounding_mode",         "Rounding mode for all calculations",             "ENUM('HalfUp')",                "None","No", "HalfUp",       "No","—","HalfUp"),
         ("demo_only",             "TRUE if this version is for demo data only",     "BOOLEAN",                       "None","No", "1",            "No","—","0|1"),
         ("status",                "Version lifecycle status",                       "ENUM('Draft','Approved','Retired')","None","No","Approved",  "No","—","Draft|Approved|Retired"),
         ("approved_by",           "User who approved (FK → users)",                "BIGINT UNSIGNED",               "NULL","No", "1",            "Yes","—","[0-9]"),
         ("approved_at",           "Approval timestamp (UTC)",                      "DATETIME(0)",                   "NULL","No", "2026-01-01 08:00:00","Yes","—","UTC datetime"),
         ("created_at",            "Record creation timestamp",                     "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",            "Record last-update timestamp",                  "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("contribution_policy_version",
     "Versioned government contribution policy header. Child tables (sss_bracket, philhealth_rate, pagibig_rate) carry the rate details.",
     "None",
     [
         ("contribution_policy_id","Policy version identifier",               "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",           "No","—","[0-9]"),
         ("policy_code",           "Short code identifying the policy family","VARCHAR(50)",                   "None","No", "WBPMS-CONTRIB","No","50","[A-Za-z0-9-]"),
         ("version",               "Version label",                           "VARCHAR(30)",                   "None","No", "v1.0",         "No","30","[A-Za-z0-9.]"),
         ("effective_from",        "Start date of this version",              "DATE",                          "None","No", "01/01/26",     "No","—","MM/DD/YY"),
         ("effective_to",          "End date; NULL = current",                "DATE",                          "NULL","No", "—",            "Yes","—","MM/DD/YY"),
         ("demo_only",             "TRUE if for demo data only",              "BOOLEAN",                       "None","No", "1",            "No","—","0|1"),
         ("status",                "Version lifecycle status",                "ENUM('Draft','Approved','Retired')","None","No","Approved",  "No","—","Draft|Approved|Retired"),
         ("approved_by",           "User who approved (FK → users)",         "BIGINT UNSIGNED",               "NULL","No", "1",            "Yes","—","[0-9]"),
         ("approved_at",           "Approval timestamp (UTC)",               "DATETIME(0)",                   "NULL","No", "2026-01-01 08:00:00","Yes","—","UTC datetime"),
         ("created_at",            "Record creation timestamp",              "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",            "Record last-update timestamp",           "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("sss_bracket",
     "SSS salary bracket table. Each row defines an EEMR salary range and the corresponding employee and employer contribution amounts.",
     "None",
     [
         ("bracket_id",             "Bracket identifier",                                    "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",          "No","—","[0-9]"),
         ("contribution_policy_id", "Parent policy version (FK → contribution_policy_version)","BIGINT UNSIGNED",             "None","No", "1",          "No","—","[0-9]"),
         ("salary_from",            "Lower bound of EEMR salary range (inclusive)",          "DECIMAL(12,2)",                 "None","No", "11500.00",    "No","—","[0-9.]"),
         ("salary_to",              "Upper bound of EEMR salary range (exclusive); NULL = no cap","DECIMAL(12,2)",            "NULL","No", "12000.00",    "Yes","—","[0-9.]"),
         ("employee_share",         "Employee SSS contribution amount",                      "DECIMAL(12,2)",                 "None","No", "540.00",      "No","—","[0-9.]"),
         ("employer_share",         "Employer SSS contribution amount",                      "DECIMAL(12,2)",                 "None","No", "1140.00",     "No","—","[0-9.]"),
         ("effective_from",         "Start date of this bracket",                            "DATE",                          "None","No", "01/01/26",    "No","—","MM/DD/YY"),
         ("effective_to",           "End date; NULL = current",                              "DATE",                          "NULL","No", "—",           "Yes","—","MM/DD/YY"),
         ("created_at",             "Record creation timestamp",                             "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",             "Record last-update timestamp",                          "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("philhealth_rate",
     "PhilHealth contribution rate for a given policy version. Employee and employer each pay half of the computed total premium.",
     "None",
     [
         ("rate_id",                "Rate identifier",                                         "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",         "No","—","[0-9]"),
         ("contribution_policy_id", "Parent policy version (FK → contribution_policy_version; unique)","BIGINT UNSIGNED",      "None","No", "1",         "No","—","[0-9]"),
         ("rate_decimal",           "Total premium rate as a decimal (e.g. 0.04 = 4%)",       "DECIMAL(7,6)",                  "None","No", "0.040000",  "No","—","[0-9.]"),
         ("basis_floor",            "Minimum EEMR used as the contribution basis",             "DECIMAL(12,2)",                 "NULL","No", "10000.00",  "Yes","—","[0-9.]"),
         ("basis_ceiling",          "Maximum EEMR used as the contribution basis",             "DECIMAL(12,2)",                 "NULL","No", "100000.00", "Yes","—","[0-9.]"),
         ("employee_share_decimal", "Employee's portion of the premium as a decimal (0.02 = half)","DECIMAL(7,6)",             "None","No", "0.020000",  "No","—","[0-9.]"),
         ("created_at",             "Record creation timestamp",                               "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",             "Record last-update timestamp",                            "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("pagibig_rate",
     "Pag-IBIG (HDMF) contribution rate for a given policy version. Supports either rate/cap-based or fixed-amount modes.",
     "None",
     [
         ("rate_id",                "Rate identifier",                                         "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",         "No","—","[0-9]"),
         ("contribution_policy_id", "Parent policy version (FK → contribution_policy_version; unique)","BIGINT UNSIGNED",      "None","No", "1",         "No","—","[0-9]"),
         ("rate_decimal",           "Contribution rate as a decimal (if rate-based)",          "DECIMAL(7,6)",                  "NULL","No", "0.020000",  "Yes","—","[0-9.]"),
         ("basis_ceiling",          "Maximum EEMR used as basis (if rate-based)",              "DECIMAL(12,2)",                 "NULL","No", "5000.00",   "Yes","—","[0-9.]"),
         ("employee_fixed_amount",  "Fixed employee contribution amount (if fixed-mode)",      "DECIMAL(12,2)",                 "NULL","No", "200.00",    "Yes","—","[0-9.]"),
         ("employer_fixed_amount",  "Fixed employer contribution amount (if fixed-mode)",      "DECIMAL(12,2)",                 "NULL","No", "200.00",    "Yes","—","[0-9.]"),
         ("created_at",             "Record creation timestamp",                               "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",             "Record last-update timestamp",                            "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),
]))

# ── Group 8: Payroll Computation ───────────────────────────────────────────
GROUPS.append(("Group 8: Payroll Computation", [

    ("payroll_period",
     "Defines each recurring Friday-through-Thursday pay period. One period is shared across all branch payroll runs for that week.",
     "None",
     [
         ("payroll_period_id","Period identifier",                         "BIGINT UNSIGNED AUTO_INCREMENT",           "None","Yes","1",         "No","—","[0-9]"),
         ("period_start",     "First day of the pay period (must be Friday)","DATE",                                  "None","No", "10/03/26",  "No","—","MM/DD/YY"),
         ("period_end",       "Last day of the pay period (must be Thursday)","DATE",                                 "None","No", "10/09/26",  "No","—","MM/DD/YY"),
         ("pay_date",         "Disbursement date (Friday after period ends)","DATE",                                  "None","No", "10/10/26",  "No","—","MM/DD/YY"),
         ("status",           "Period lifecycle status",                    "ENUM('Open','AttendanceClosed','Disbursed','Closed')","None","No","Open","No","—","Open|AttendanceClosed|Disbursed|Closed"),
         ("created_at",       "Record creation timestamp",                  "DATETIME(0)",                            "None","No", "2026-10-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",       "Record last-update timestamp",               "DATETIME(0)",                            "None","No", "2026-10-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("payroll_run",
     "One branch-level payroll transaction per pay period. Owns the HR-to-Owner approval state. Run totals are the sum of all employee payroll rows.",
     "None",
     [
         ("payroll_run_id",     "Run identifier",                                        "BIGINT UNSIGNED AUTO_INCREMENT",                                  "None","Yes","1",                   "No","—","[0-9]"),
         ("payroll_period_id",  "Pay period (FK → payroll_period)",                     "BIGINT UNSIGNED",                                                 "None","No", "1",                   "No","—","[0-9]"),
         ("branch_id",          "Branch being paid (FK → branch)",                      "BIGINT UNSIGNED",                                                 "None","No", "2",                   "No","—","[0-9]"),
         ("payroll_policy_id",  "Payroll policy version applied (FK → payroll_policy_version)","BIGINT UNSIGNED",                                          "None","No", "1",                   "No","—","[0-9]"),
         ("status",             "Approval state of this run",                           "ENUM('Draft','Computed','PendingOwnerApproval','Approved','Returned')","None","No","Draft",            "No","—","Draft|Computed|PendingOwnerApproval|Approved|Returned"),
         ("gross_pay",          "Total gross pay for the branch run",                   "DECIMAL(15,2)",                                                   "NULL","No", "85000.00",            "Yes","—","[0-9.]"),
         ("total_deductions",   "Total deductions for the branch run",                  "DECIMAL(15,2)",                                                   "NULL","No", "5000.00",             "Yes","—","[0-9.]"),
         ("net_pay",            "Total net pay for the branch run",                     "DECIMAL(15,2)",                                                   "NULL","No", "80000.00",            "Yes","—","[0-9.]"),
         ("computed_by",        "User who computed the run (FK → users)",               "BIGINT UNSIGNED",                                                 "NULL","No", "2",                   "Yes","—","[0-9]"),
         ("computed_at",        "Computation timestamp (UTC)",                          "DATETIME(0)",                                                     "NULL","No", "2026-10-09 14:00:00", "Yes","—","UTC datetime"),
         ("submitted_by",       "HR user who submitted for approval (FK → users)",      "BIGINT UNSIGNED",                                                 "NULL","No", "2",                   "Yes","—","[0-9]"),
         ("submitted_at",       "Submission timestamp (UTC)",                           "DATETIME(0)",                                                     "NULL","No", "2026-10-09 16:00:00", "Yes","—","UTC datetime"),
         ("reviewed_by",        "Business Owner who reviewed (FK → users)",             "BIGINT UNSIGNED",                                                 "NULL","No", "1",                   "Yes","—","[0-9]"),
         ("reviewed_at",        "Review timestamp (UTC)",                               "DATETIME(0)",                                                     "NULL","No", "2026-10-10 09:00:00", "Yes","—","UTC datetime"),
         ("return_reason",      "Reason for returning the run (required if Returned)",  "VARCHAR(500)",                                                    "NULL","No", "Missing overtime documentation","Yes","500","string"),
         ("lock_version",       "Optimistic concurrency lock counter",                  "INT UNSIGNED",                                                    "0",   "No", "0",                   "No","—","[0-9]"),
         ("created_at",         "Record creation timestamp",                            "DATETIME(0)",                                                     "None","No", "2026-10-01 08:00:00", "No","—","UTC datetime"),
         ("updated_at",         "Record last-update timestamp",                         "DATETIME(0)",                                                     "None","No", "2026-10-10 09:00:00", "No","—","UTC datetime"),
     ]),

    ("payroll",
     "One row per employee per payroll period. Snapshots the salary rate at computation time. Unique per period and employee. Immutable once the run is Approved.",
     "None",
     [
         ("payroll_id",            "Payroll row identifier",                                "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",         "No","—","[0-9]"),
         ("payroll_run_id",        "Parent run (FK → payroll_run)",                         "BIGINT UNSIGNED",               "None","No", "1",         "No","—","[0-9]"),
         ("payroll_period_id",     "Pay period (FK → payroll_period)",                      "BIGINT UNSIGNED",               "None","No", "1",         "No","—","[0-9]"),
         ("employee_id",           "Employee being paid (FK → employee)",                   "BIGINT UNSIGNED",               "None","No", "1",         "No","—","[0-9]"),
         ("branch_assignment_id",  "Branch assignment effective at period start (FK → employee_branch_assignment)","BIGINT UNSIGNED","None","No","1",   "No","—","[0-9]"),
         ("salary_id",             "Salary record used (FK → salary)",                      "BIGINT UNSIGNED",               "None","No", "1",         "No","—","[0-9]"),
         ("daily_rate_snapshot",   "Daily rate at the time of computation (immutable copy)","DECIMAL(12,2)",                 "None","No", "460.00",    "No","—","[0-9.]"),
         ("gross_pay",             "Total earnings before deductions",                      "DECIMAL(12,2)",                 "None","No", "2760.00",   "No","—","[0-9.]"),
         ("total_deductions",      "Sum of all deductions",                                 "DECIMAL(12,2)",                 "None","No", "739.97",    "No","—","[0-9.]"),
         ("net_pay",               "Take-home pay (gross - deductions)",                    "DECIMAL(12,2)",                 "None","No", "2020.03",   "No","—","[0-9.]"),
         ("created_at",            "Record creation timestamp",                             "DATETIME(0)",                   "None","No", "2026-10-09 14:00:00","No","—","UTC datetime"),
     ]),

    ("payroll_earnings",
     "One row per earnings line item per payroll row. Stores the calculation inputs for full reproducibility.",
     "None",
     [
         ("earning_id",           "Earning line identifier",                          "BIGINT UNSIGNED AUTO_INCREMENT",                                         "None","Yes","1",        "No","—","[0-9]"),
         ("payroll_id",           "Parent payroll row (FK → payroll)",                "BIGINT UNSIGNED",                                                        "None","No", "1",        "No","—","[0-9]"),
         ("earning_type",         "Type of earning",                                  "ENUM('Basic','Overtime','RegularHoliday','SpecialHoliday','ThirteenthMonth')","None","No","Basic","No","—","see ENUM"),
         ("description",          "Human-readable earning description",               "VARCHAR(255)",                                                           "None","No", "Basic Pay","No","255","string"),
         ("source_attendance_id", "Linked attendance record if applicable (FK → attendance)","BIGINT UNSIGNED",                                                "NULL","No", "1",        "Yes","—","[0-9]"),
         ("source_request_id",    "Linked overtime request if applicable (FK → request)","BIGINT UNSIGNED",                                                    "NULL","No", "5",        "Yes","—","[0-9]"),
         ("quantity",             "Units (days, hours, or minutes) for this line",    "DECIMAL(12,4)",                                                          "None","No", "6.0000",   "No","—","[0-9.]"),
         ("unit_rate",            "Rate per unit",                                    "DECIMAL(12,4)",                                                          "None","No", "460.0000", "No","—","[0-9.]"),
         ("multiplier",           "Pay multiplier applied",                           "DECIMAL(8,4)",                                                           "None","No", "1.0000",   "No","—","[0-9.]"),
         ("amount",               "Computed earning amount",                          "DECIMAL(12,2)",                                                          "None","No", "2760.00",  "No","—","[0-9.]"),
         ("calculation_details",  "JSON snapshot of all inputs and formula steps",    "JSON",                                                                   "None","No", "{...}",    "No","—","JSON object"),
         ("created_at",           "Record creation timestamp",                        "DATETIME(0)",                                                            "None","No", "2026-10-09 14:00:00","No","—","UTC datetime"),
     ]),

    ("deduction",
     "One row per deduction line item per payroll row. Covers late, undertime, government contributions, income tax, and cash advance deductions.",
     "None",
     [
         ("deduction_id",         "Deduction line identifier",                         "BIGINT UNSIGNED AUTO_INCREMENT",                                                   "None","Yes","1",       "No","—","[0-9]"),
         ("payroll_id",           "Parent payroll row (FK → payroll)",                 "BIGINT UNSIGNED",                                                                  "None","No", "1",       "No","—","[0-9]"),
         ("deduction_type",       "Type of deduction",                                 "ENUM('Late','Undertime','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax')",   "None","No", "SSS",    "No","—","see ENUM"),
         ("description",          "Human-readable deduction description",              "VARCHAR(255)",                                                                     "None","No", "SSS Employee Share","No","255","string"),
         ("source_attendance_id", "Linked attendance record if applicable (FK → attendance)","BIGINT UNSIGNED",                                                            "NULL","No", "1",       "Yes","—","[0-9]"),
         ("quantity",             "Units for this deduction line",                     "DECIMAL(12,4)",                                                                    "None","No", "1.0000",  "No","—","[0-9.]"),
         ("unit_rate",            "Rate per unit",                                     "DECIMAL(12,4)",                                                                    "None","No", "540.0000","No","—","[0-9.]"),
         ("amount",               "Computed deduction amount",                         "DECIMAL(12,2)",                                                                    "None","No", "540.00",  "No","—","[0-9.]"),
         ("calculation_details",  "JSON snapshot of all inputs and formula steps",     "JSON",                                                                             "None","No", "{...}",   "No","—","JSON object"),
         ("created_at",           "Record creation timestamp",                         "DATETIME(0)",                                                                      "None","No", "2026-10-09 14:00:00","No","—","UTC datetime"),
     ]),

    ("contribution_record",
     "Records the EEMR basis, employee share, and employer share for each government contribution deduction. Locked once the payroll run is Approved.",
     "None",
     [
         ("contribution_id",       "Contribution record identifier",                          "BIGINT UNSIGNED AUTO_INCREMENT",            "None","Yes","1",                    "No","—","[0-9]"),
         ("payroll_id",            "Parent payroll row (FK → payroll)",                       "BIGINT UNSIGNED",                           "None","No", "1",                    "No","—","[0-9]"),
         ("deduction_id",          "Linked deduction line (FK → deduction; unique)",          "BIGINT UNSIGNED",                           "None","No", "3",                    "No","—","[0-9]"),
         ("contribution_policy_id","Policy version applied (FK → contribution_policy_version)","BIGINT UNSIGNED",                          "None","No", "1",                    "No","—","[0-9]"),
         ("contribution_type",     "Government program",                                      "ENUM('SSS','PhilHealth','PagIBIG')",         "None","No", "SSS",                  "No","—","SSS|PhilHealth|PagIBIG"),
         ("eemr_basis",            "Monthly equivalent basic salary used as contribution basis","DECIMAL(12,2)",                            "None","No", "11998.33",             "No","—","[0-9.]"),
         ("employee_share",        "Employee contribution amount",                            "DECIMAL(12,2)",                             "None","No", "540.00",               "No","—","[0-9.]"),
         ("employer_share",        "Employer contribution amount",                            "DECIMAL(12,2)",                             "None","No", "1140.00",              "No","—","[0-9.]"),
         ("deduction_date",        "Date the deduction was applied",                         "DATE",                                       "None","No", "10/10/26",             "No","—","MM/DD/YY"),
         ("calculation_details",   "JSON snapshot of all contribution formula inputs",        "JSON",                                       "None","No", "{...}",               "No","—","JSON object"),
         ("status",                "Computation and lock status",                             "ENUM('Computed','Locked')",                  "None","No", "Computed",             "No","—","Computed|Locked"),
         ("locked_at",             "Timestamp when the record was locked (UTC)",              "DATETIME(0)",                               "NULL","No", "2026-10-10 09:00:00",  "Yes","—","UTC datetime"),
         ("locked_by",             "User who locked the record (FK → users)",                "BIGINT UNSIGNED",                           "NULL","No", "1",                    "Yes","—","[0-9]"),
         ("created_at",            "Record creation timestamp",                              "DATETIME(0)",                               "None","No", "2026-10-09 14:00:00",  "No","—","UTC datetime"),
     ]),

    ("payslip",
     "One payslip per payroll row. Unique per payroll. Generated after the run is Approved and linked to the generated file path.",
     "None",
     [
         ("payslip_id",    "Payslip identifier",                              "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",                   "No","—","[0-9]"),
         ("payroll_id",    "Payroll row this payslip covers (FK → payroll; unique)","BIGINT UNSIGNED",        "None","No", "1",                   "No","—","[0-9]"),
         ("issue_date",    "Date the payslip was issued",                     "DATE",                          "None","No", "10/10/26",            "No","—","MM/DD/YY"),
         ("file_path",     "Storage path to the generated payslip file",      "VARCHAR(500)",                  "NULL","No", "storage/payslips/1.pdf","Yes","500","[A-Za-z0-9/._-]"),
         ("generated_by",  "User who generated the payslip (FK → users)",    "BIGINT UNSIGNED",               "NULL","No", "2",                   "Yes","—","[0-9]"),
         ("generated_at",  "Generation timestamp (UTC)",                     "DATETIME(0)",                   "None","No", "2026-10-10 09:05:00", "No","—","UTC datetime"),
         ("content_hash",  "SHA-256 hash of the generated file content",     "CHAR(64)",                      "NULL","No", "(sha256 hex)",         "Yes","64","[a-f0-9]"),
     ]),
]))

# ── Group 9: Bank Disbursement ──────────────────────────────────────────────
GROUPS.append(("Group 9: Bank Disbursement", [

    ("bank_details",
     "Effective-dated BDO bank account record per employee. The service enforces exactly one current active account per employee.",
     "None",
     [
         ("bank_id",        "Bank record identifier",                    "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",              "No","—","[0-9]"),
         ("employee_id",    "Account holder (FK → employee)",            "BIGINT UNSIGNED",               "None","No", "1",              "No","—","[0-9]"),
         ("bank_name",      "Name of the bank",                         "VARCHAR(100)",                  "None","No", "BDO",            "No","100","[A-Za-z0-9 ]"),
         ("account_name",   "Account holder name as it appears at bank","VARCHAR(150)",                  "None","No", "Jiane A. Gamboa","No","150","[A-Za-z .]"),
         ("account_number", "Bank account number",                      "VARCHAR(50)",                   "None","No", "001234567890",   "No","50", "[0-9]"),
         ("account_type",   "Account type (e.g., Savings)",             "VARCHAR(50)",                   "NULL","No", "Savings",        "Yes","50", "[A-Za-z ]"),
         ("effective_from", "Start date this account is valid (inclusive)","DATE",                       "None","No", "01/01/26",       "No","—","MM/DD/YY"),
         ("effective_to",   "End date; NULL = current active account",  "DATE",                          "NULL","No", "—",              "Yes","—","MM/DD/YY"),
         ("status",         "Account status",                           "ENUM('Active','Inactive')",     "None","No", "Active",         "No","—","Active|Inactive"),
         ("created_at",     "Record creation timestamp",                "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
         ("updated_at",     "Record last-update timestamp",             "DATETIME(0)",                   "None","No", "2026-01-01 08:00:00","No","—","UTC datetime"),
     ]),

    ("disbursement_batch",
     "One aggregate weekly pay-to-cash BDO cheque batch per payroll period. Created only after all included branch runs are Approved.",
     "None",
     [
         ("batch_id",          "Batch identifier",                              "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",              "No","—","[0-9]"),
         ("payroll_period_id", "Pay period (FK → payroll_period; unique)",      "BIGINT UNSIGNED",               "None","No", "1",              "No","—","[0-9]"),
         ("bank_name",         "Bank used for disbursement",                    "VARCHAR(100)",                  "'BDO'","No", "BDO",           "No","100","[A-Za-z0-9 ]"),
         ("cheque_number",     "Physical cheque number",                        "VARCHAR(50)",                   "NULL","No", "CHQ-20261010",   "Yes","50","[A-Za-z0-9-]"),
         ("cheque_total",      "Aggregate amount of the weekly cheque",         "DECIMAL(15,2)",                 "None","No", "80000.00",       "No","—","[0-9.]"),
         ("status",            "Batch processing status",                       "ENUM('Prepared','Issued','Reconciled')","None","No","Prepared","No","—","Prepared|Issued|Reconciled"),
         ("prepared_by",       "HR user who prepared the batch (FK → users)",  "BIGINT UNSIGNED",               "NULL","No", "2",              "Yes","—","[0-9]"),
         ("submitted_at",      "Timestamp when cheque was submitted (UTC)",     "DATETIME(0)",                   "NULL","No", "2026-10-10 10:00:00","Yes","—","UTC datetime"),
         ("created_at",        "Record creation timestamp",                     "DATETIME(0)",                   "None","No", "2026-10-10 09:00:00","No","—","UTC datetime"),
         ("updated_at",        "Record last-update timestamp",                  "DATETIME(0)",                   "None","No", "2026-10-10 09:00:00","No","—","UTC datetime"),
     ]),

    ("deposit_slip",
     "One BDO deposit slip per employee per payroll period. Amount must equal the employee's net pay. Linked to the disbursement batch and the employee's active bank account.",
     "None",
     [
         ("slip_id",     "Deposit slip identifier",                          "BIGINT UNSIGNED AUTO_INCREMENT","None","Yes","1",              "No","—","[0-9]"),
         ("batch_id",    "Parent disbursement batch (FK → disbursement_batch)","BIGINT UNSIGNED",             "None","No", "1",              "No","—","[0-9]"),
         ("payroll_id",  "Employee payroll row (FK → payroll; unique)",      "BIGINT UNSIGNED",               "None","No", "1",              "No","—","[0-9]"),
         ("bank_id",     "Employee's bank account used (FK → bank_details)", "BIGINT UNSIGNED",               "None","No", "1",              "No","—","[0-9]"),
         ("amount",      "Deposit amount (must equal net_pay)",              "DECIMAL(12,2)",                 "None","No", "2020.03",        "No","—","[0-9.]"),
         ("status",      "Slip preparation/deposit status",                 "ENUM('Pending','Prepared','Deposited')","None","No","Pending",  "No","—","Pending|Prepared|Deposited"),
         ("prepared_at", "Timestamp when slip was prepared (UTC)",          "DATETIME(0)",                   "NULL","No", "2026-10-10 10:00:00","Yes","—","UTC datetime"),
         ("created_at",  "Record creation timestamp",                       "DATETIME(0)",                   "None","No", "2026-10-10 09:00:00","No","—","UTC datetime"),
         ("updated_at",  "Record last-update timestamp",                    "DATETIME(0)",                   "None","No", "2026-10-10 09:00:00","No","—","UTC datetime"),
     ]),
]))


# ---------------------------------------------------------------------------
# Document generation helpers
# ---------------------------------------------------------------------------

HEADER_COLS = [
    "Attribute Name", "Description", "Data Type", "Default Value",
    "Primary Key", "Example", "Null Allowed?\n(Y/N)", "Length",
    "Validation Rule"
]

COL_WIDTHS = [
    Inches(1.15), Inches(1.55), Inches(1.20), Inches(0.75),
    Inches(0.58), Inches(0.90), Inches(0.58), Inches(0.52),
    Inches(0.97),
]


def set_cell_bg(cell, hex_color: str):
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_color)
    tcPr.append(shd)


def cell_text(cell, text: str, bold=False, size=9, color=None,
              align=WD_ALIGN_PARAGRAPH.LEFT):
    cell.text = ""
    para = cell.paragraphs[0]
    para.alignment = align
    run = para.add_run(str(text))
    run.bold = bold
    run.font.size = Pt(size)
    if color:
        run.font.color.rgb = RGBColor.from_string(color)


def add_meta_row(table, label: str, value: str):
    row = table.add_row()
    lc = row.cells[0]
    vc = row.cells[1]
    lc.merge(row.cells[0])
    cell_text(lc, label, bold=True, size=9)
    cell_text(vc, value, size=9)
    set_cell_bg(lc, "D9E1F2")


def build_table_entry(doc, tbl_name: str, description: str, alias: str,
                      attributes: list, table_number: int):
    # ── Table Number heading ────────────────────────────────────────────────
    h = doc.add_paragraph()
    h.alignment = WD_ALIGN_PARAGRAPH.LEFT
    run = h.add_run(f"Table {table_number}. Data Dictionary of {tbl_name}")
    run.bold = True
    run.font.size = Pt(10)
    h.paragraph_format.space_before = Pt(10)
    h.paragraph_format.space_after = Pt(2)

    # ── Meta rows (Table Name / Description / Alias) ────────────────────────
    meta = doc.add_table(rows=0, cols=2)
    meta.style = "Table Grid"
    meta.alignment = WD_TABLE_ALIGNMENT.LEFT
    meta.columns[0].width = Inches(1.4)
    meta.columns[1].width = Inches(6.8)

    for label, val in [("Table Name", tbl_name),
                       ("Description", description),
                       ("Alias", alias)]:
        r = meta.add_row()
        cell_text(r.cells[0], label, bold=True, size=9)
        cell_text(r.cells[1], val, size=9)
        set_cell_bg(r.cells[0], "D9E1F2")

    doc.add_paragraph()  # small spacer

    # ── Attribute table ─────────────────────────────────────────────────────
    attr_tbl = doc.add_table(rows=1, cols=len(HEADER_COLS))
    attr_tbl.style = "Table Grid"
    attr_tbl.alignment = WD_TABLE_ALIGNMENT.LEFT

    # Set column widths
    for i, w in enumerate(COL_WIDTHS):
        for cell in attr_tbl.columns[i].cells:
            cell.width = w

    # Header row
    hdr_row = attr_tbl.rows[0]
    for i, col in enumerate(HEADER_COLS):
        cell_text(hdr_row.cells[i], col, bold=True, size=8,
                  color="FFFFFF", align=WD_ALIGN_PARAGRAPH.CENTER)
        set_cell_bg(hdr_row.cells[i], "2F5496")

    # Data rows
    for idx, attr in enumerate(attributes):
        (a_name, a_desc, a_type, a_default,
         a_pk, a_example, a_null, a_length, a_validation) = attr
        row = attr_tbl.add_row()
        bg = "EBF3FB" if idx % 2 == 0 else "FFFFFF"
        vals = [a_name, a_desc, a_type, a_default,
                a_pk, a_example, a_null, a_length, a_validation]
        for i, v in enumerate(vals):
            cell_text(row.cells[i], v, size=8)
            set_cell_bg(row.cells[i], bg)

    doc.add_paragraph()  # spacing after each table


def build_document(output_path: str):
    doc = Document()

    # Page margins
    for section in doc.sections:
        section.top_margin    = Inches(1.0)
        section.bottom_margin = Inches(1.0)
        section.left_margin   = Inches(1.0)
        section.right_margin  = Inches(1.0)

    # ── Cover / title ────────────────────────────────────────────────────────
    title = doc.add_paragraph()
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    tr = title.add_run("Web-Based Payroll Management System\nfor Light Diamond Enterprises")
    tr.bold = True
    tr.font.size = Pt(14)

    sub = doc.add_paragraph()
    sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
    sr = sub.add_run("Data Dictionary — Canonical Schema v1.1\nAll 9 Module Groups")
    sr.font.size = Pt(11)

    note = doc.add_paragraph()
    note.alignment = WD_ALIGN_PARAGRAPH.CENTER
    nr = note.add_run(
        "Authority: Canonical Database Schema v1.1 (ADR-0002) · "
        "STI College Tacurong · 2026"
    )
    nr.font.size = Pt(9)
    nr.font.italic = True

    doc.add_paragraph()

    # ── Groups and tables ────────────────────────────────────────────────────
    table_counter = 1
    for group_title, tables in GROUPS:
        # Group heading
        gh = doc.add_heading(group_title, level=1)
        gh.runs[0].font.size = Pt(12)
        gh.paragraph_format.space_before = Pt(14)
        gh.paragraph_format.space_after  = Pt(4)

        for tbl_name, description, alias, attributes in tables:
            build_table_entry(doc, tbl_name, description, alias,
                              attributes, table_counter)
            table_counter += 1

    doc.save(output_path)
    print(f"Saved: {output_path}  ({table_counter - 1} tables)")


if __name__ == "__main__":
    out = os.path.join(
        os.path.dirname(os.path.abspath(__file__)),
        "WBPMS-Data-Dictionary-v1.1.docx"
    )
    build_document(out)
