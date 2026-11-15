---
inclusion: always
---

# Product Overview

## What this is

A **Web-Based Payroll Management System** for Light Diamond Enterprises, a
multi-branch business currently running payroll manually in Excel (a process
that takes roughly a week per pay cycle). The system replaces that manual
process with a browser-based application that pulls attendance from a
biometric device, computes pay automatically, routes payroll through an
approval workflow, and gives employees self-service access to their own
records.

## Problem it solves

- Payroll is computed by hand in spreadsheets — slow, error-prone, and hard
  to audit across the company's branches.
- Attendance (from a biometric scanner) is reconciled manually against
  payroll.
- Employees have no self-service way to check attendance, leave balances,
  request leave/overtime/cash advances, or retrieve payslips.
- Government-mandated contributions (SSS, PhilHealth, Pag-IBIG) and 13th
  month pay are computed manually each cycle.

> **Operational topology clarification:** follow-up interview evidence reports
> two physical biometric devices: one at the Banga site and one at Surallah.
> The Banga device appears to serve two adjacent operational branches/business
> units (Construction Supplies and an Auto Supplies unit whose official name
> remains to be confirmed). This explains why source material can describe
> two physical locations while referring to three operational branches.
> Neither the observed branch count nor device count is a product limit:
> authorized HR users configure branches, attendance sites, biometric devices,
> and which branches each device serves. Exact branch names and employee
> distribution remain master data rather than hardcoded values.

## Users and roles

| Role | Responsibilities |
|---|---|
| **Business Owner** | Reviews and approves/returns payroll submitted by HR Head; monitors payroll costs and financial reports across branches. |
| **HR Head** | Manages employees, attendance, schedules, requests (leave/overtime/cash advance), salary structures, deductions/contributions, payroll computation, and report generation. |
| **Employee** | Views own attendance, work schedule, and payslips; submits and tracks leave, overtime, and cash advance requests. |
| **Developer** | Builds, maintains, and secures the system (not an in-app role, but a maintenance stakeholder). |

Access is role-based: each role only sees the modules and actions relevant
to it.

## Core modules (product surface)

1. Login & Authentication (role-based access, password recovery via email/OTP)
2. Dashboard (role-based summaries: employees, pending requests, payroll status, attendance alerts)
3. User Management (accounts, roles, activate/deactivate/archive)
4. Employee Management (employee records per branch)
5. Work Schedule Management (calendar-based schedules, holidays)
6. Attendance Management (HR uploads the biometric device's monthly `.xls`
   daily-log export; the system expands its date/time matrix into preserved
   punches and a per-employee timesheet, with manual adjustment and
   hours/late/undertime/overtime computation)
7. Request Management (leave, overtime, cash advance — submit/update/cancel/approve/reject)
8. Payroll Management (salary computation, deductions, 13th-month pay, payslip generation, owner approval workflow)
9. Manage Salary (salary structures, daily rates, historical rates)
10. Benefits & Deductions (SSS, PhilHealth, Pag-IBIG computation and records)
11. Reports Management (payroll, attendance, request, contribution, 13th-month reports; print/export)
12. Employee self-service front-end (attendance viewing, requests, payslip viewing/download)

## Key business rules to preserve in any implementation

- Only the HR Head manages payroll, attendance adjustments, and approves
  leave/cash advance/overtime requests.
- The Business Owner is the final approver of a completed payroll run
  (review → approve or return for revision).
- Employees get **four paid sick leaves**; a leave request must be blocked
  if the balance is insufficient.
- Payroll figures are derived from attendance + salary structure + approved
  requests + government contribution brackets — never entered by hand.
- Dates use `MM/DD/YY`, time uses 24-hour format, and English is the system
  language (per non-functional requirements).

## Out of scope (for now)

- Mobile native apps (web-responsive only, desktop/laptop targeted).
- Electronic bank integration. The current BDO workflow uses a printable
  deposit-slip preparation list, one aggregate weekly cheque, and manually
  prepared employee deposit slips.
- Multi-country payroll/tax rules (Philippines-specific: SSS, PhilHealth,
  Pag-IBIG).
