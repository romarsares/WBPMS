# UI/UX Change Log

This document records confirmed UI/UX changes from stakeholder interviews.
Changes here must be implemented before the MVP is considered complete.

---

## 2026-09-21 HR Interview Changes

### Employee Portal — General

#### Remove HR Notice Module
- The HR Notice module is removed from the Employee portal entirely.
- There should be no HR Notice navigation item, page, or widget visible to employees.
- Any existing migration or view code for `employee_hr_notice` must be disabled from
  the employee-facing UI (the table may remain for data integrity but the feature is
  not surfaced to employees).

#### Remove Quick Actions from Employee Dashboard
- The Quick Actions section/widget on the Employee dashboard is removed.
- The employee dashboard should show relevant summaries (attendance, requests, payslip
  status) without a quick-action shortcut panel.

#### Highlight Active Navbar Item
- The currently active navigation item in the Employee portal navbar must be visually
  highlighted (e.g., distinct background color, bold text, or active indicator) to
  show the user where they are.
- This applies to all employee-facing navigation links.

---

### Employee Portal — My Attendance

#### Add Status Column
- The My Attendance table must include a Status column for each attendance record.
- The status reflects whether the entry is: `Complete`, `Incomplete` (missing time-in
  or time-out), `Flagged` (multi-punch or adjustment pending), or as otherwise defined
  by the attendance computation.

#### Auto-Clear Searchbar
- The search/filter bar in My Attendance must auto-clear when the employee navigates
  away from the page and returns.
- The searchbar must not persist a previous search query across page navigations.

---

### Employee Portal — My Requests

#### Remove `Rejected` Status — Replace with `Cancelled`
- The `Rejected` status label is removed from the employee-facing My Requests view.
- All declined requests display as `Cancelled` regardless of whether the decliner was
  HR or the Business Owner.
- This is a system-wide status rename: the `Rejected` enum/value in the database and
  business logic must also be renamed to `Cancelled` for consistency.
- Both HR-declined and Owner-cancelled requests map to the same `Cancelled` display
  status.

---

### Login Page

#### Email-Only Login
- The login form must use an email address field (not a username field).
- The label should be `Email` or `Email Address`, not `Username`.
- Validation must enforce valid email format on the login form.
- The Forgot Password flow already uses email — this change makes the login form
  consistent with it.

---

### Color Palette

#### Align with Logo
- The application color palette must be aligned with the Light Diamond Enterprises
  logo colors.
- Extract the primary and accent colors from `public/assets/light-diamond-logo.png`
  and apply them consistently across the UI (navbar, buttons, highlights, headings).
- Document the chosen hex values below once confirmed with the client.

| Token | Hex | Usage |
|---|---|---|
| `--color-primary` | TBD | Navbar background, primary buttons |
| `--color-accent` | TBD | Active nav highlight, links |
| `--color-text-on-primary` | TBD | Text on primary-colored backgrounds |

---

## Implementation Notes

- Employee portal changes affect `resources/views/employee/`, the employee dashboard
  view, and `public/assets/app.css`.
- The `Rejected` → `Cancelled` status rename also affects:
  - `app/Application/RequestService.php` — decision enum/values
  - The `request` table status column enum or check constraint
  - Any PHP constants, string comparisons, or switch/match cases referencing
    `'rejected'` or `'Rejected'`
  - Any HR-facing views that display request statuses
- The email-only login change affects `resources/views/auth/login.php` and the
  `AuthService` / `AuthController` credential lookup.
- See `docs/requirements.md` Requirement 7 and Requirement 12 for the corresponding
  requirement updates.
- See `docs/requirements.md` Requirement 1 and Requirement 13 for the email login
  requirement updates.
