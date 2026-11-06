# Payroll Computation Reference

**System:** Web-Based Payroll Management System — Light Diamond Enterprises
**Authority:** ADR-0001, ADR-0002, `PayrollService.php`, `HolidayPayCalculator.php`, `HolidayType.php`, `ApprovedOvertimeCalculator.php`, `RequestService.php`
**Last updated:** 2026-10

---

## 1. Payroll Period

| Property | Value |
|---|---|
| Cycle | Sunday → Friday (6 calendar days) |
| Pay date | The Friday at period end (same day as `period_end`) |
| Pattern stored | `SundayFriday` (legacy `LegacyFridayThursday` for historical periods) |

A period is created by `PayrollService::createPeriod()`. The `period_start`
**must be a Sunday**; `period_end = period_start + 5 days`; `pay_date = period_end`.

---

## 2. Eligible Employees

An employee is included in a payroll run when, as of `period_start`:

1. Their `employee_branch_assignment` is active for that branch.
2. An `Active` `salary` row exists with `effective_from ≤ period_start` and
   `(effective_to IS NULL OR effective_to > period_start)`.
3. Their `employee.status = 'Active'`.

Payroll membership uses the branch assignment snapshot at `period_start`.
A mid-period transfer takes effect in the **next** period.

---

## 3. Basic Pay

```
Basic Pay = days_worked × daily_rate
```

- `days_worked` = count of `attendance` rows for the employee in
  `[period_start, period_end]` where:
  - `attendance.status IN ('Complete', 'Approved', 'ReviewRequired')`, **and**
  - the linked import batch (if any) has `status IN ('Approved', 'Completed')`.
- `daily_rate` is taken from the effective `salary` row (never entered by hand).
- Rounding: half-up to two decimal places (₱ centavos).

> Incomplete attendance (`ReviewRequired`) **is** counted for basic pay
> but **not** for overtime pay. The HR must resolve biometric exceptions before
> OT is paid.

---

## 4. Overtime Pay

### 4.1 Requirements

Overtime pay is earned only when **both** conditions are met:

1. The attendance record for that date has `overtime_minutes > 0` **and**
   `status IN ('Complete', 'Approved')` (ReviewRequired is excluded).
2. An **Approved** overtime request (`request.status = 'Approved'`) covers
   an overlapping time window on that date.

### 4.2 Payable Minutes (overlap rule)

```
payable_minutes = overlap(attendance_OT_interval, approved_request_window)
```

`ApprovedOvertimeCalculator::payableMinutes()` computes the intersection:

- Attendance OT interval = `[time_out − overtime_minutes, time_out]`
- Approved request window = `[start_time, end_time]` from `overtime_request_detail`
- Multiple approved windows on the same date are merged (union of intervals)
- Result is capped at `attendance.overtime_minutes`

### 4.3 Overtime Earnings Formula

```
minute_rate   = daily_rate / standard_minutes          (standard_minutes default: 480 = 8 h)
overtime_pay  = payable_minutes × minute_rate × 1.25   (regular OT multiplier: 125%)
```

Rounded half-up to two decimal places.

> **Holiday overtime** is handled separately — see §6.3.

---

## 5. Paid Leave

### 5.1 Leave Entitlement

- Employees receive **4 paid sick leave days** per year (default entitlement).
- A `leave_entitlement` row is auto-provisioned if none exists for the year.
- `RequestService` checks the running balance before allowing submission;
  requests are blocked if the remaining balance is insufficient (REQ078).

### 5.2 Leave Days Calculation

Leave days = calendar business days (inclusive) between `start_date` and
`end_date`, aligned to the employee's work schedule.

### 5.3 Payroll Treatment

For each approved leave day that falls within the payroll period on a
scheduled working day (and no attendance record already exists for that date
and it is not a holiday):

```
paid_leave_earnings = leave_days × daily_rate
```

Inserted as a `Basic` earning type with description
`"Approved <leave_type> Leave"`.

> Only `Approved` leave requests (fully through the Owner approval) are
> counted. `HRApproved` or `Returned` requests are not yet paid.

---

## 6. Holiday Pay

Authority: `HolidayPayCalculator.php`, `HolidayType.php`, ADR-0001, Final Defense Reviewer pp. 4–5.

### 6.1 Holiday Types and Base Multipliers

| Holiday type | Absent (covered) | Worked (first 8 h) | Worked on rest day |
|---|---|---|---|
| **Regular Holiday** | `daily_rate × 1.00` (100%) | `daily_rate × 2.00` (200%) | `daily_rate × 2.60` (200% × 130%) |
| **Special Working Holiday** | `0.00` (no obligation) | `daily_rate × 1.00` (100%) | `daily_rate × 1.30` (100% + 30%) |
| **Special Non-Working Holiday** | `0.00` (no obligation) | `daily_rate × 1.30` (130%) | `daily_rate × 1.50` (130% + 30%) |

### 6.2 How Holiday Pay Is Applied in Payroll

Basic Pay already counts the holiday date as a regular worked day
(`daily_rate × 1.0`). The payroll engine posts only the **premium above Basic Pay**
as a separate `RegularHoliday` or `SpecialHoliday` earning line:

```
holiday_day_premium = holiday_day_pay − daily_rate        (if worked)
holiday_day_premium = holiday_day_pay − 0.00              (if absent, Regular holiday only)
```

This ensures earnings rows sum correctly to gross pay without double-counting.

### 6.3 Holiday Overtime

```
holiday_hourly_rate = holiday_day_pay / (standard_minutes / 60)
holiday_OT_pay      = holiday_hourly_rate × 1.30 × (OT_minutes / 60)
```

Where `holiday_day_pay` uses the holiday-type multiplier (including rest-day
adjustment if applicable). The regular OT premium (125%) already in Basic
overtime is swapped out for the holiday OT premium.

> ADR-0001 NOTE: Holiday overtime is a separate policy decision pending HR
> confirmation. The calculation is implemented but should be validated with HR
> before production use.

---

## 7. 13th Month Pay

```
13th_month = SUM(Basic earnings) across all Approved payroll runs
             in the calendar year / 12
```

- Sourced from `payroll_earnings.amount WHERE earning_type = 'Basic'`.
- Filtered to `payroll_run.status = 'Approved'` runs only.
- Can be filtered by `branch_id` or `employee_id`.
- Rounded half-up to two decimal places.
- Stored as a `ThirteenthMonth` earning type when posted to a payroll run.

---

## 8. Government Contributions (SSS, PhilHealth, Pag-IBIG)

Contributions are deducted **only on the last-Friday payroll of each calendar month**
(`isMonthlyContributionCutoff()` — the Friday whose date equals `last friday of this month`).

### 8.1 Earnings-Equivalent Monthly Rate (EEMR)

```
EEMR = (daily_rate × eemr_days_per_year) / 12
```

Default `eemr_days_per_year = 313` (ADR-0001 policy).

**Demo fixture example:** daily_rate = ₱460.00 → EEMR = (460 × 313) / 12 = **₱11,998.33**

### 8.2 SSS

Bracket-based table lookup from `sss_bracket`:

- Match the employee's EEMR to the bracket where
  `salary_from ≤ EEMR ≤ salary_to` (`salary_to NULL` = open upper bound).
- If EEMR exceeds the highest bracket ceiling, use the last bracket.
- Employee share and employer share are fixed amounts per bracket.

**Demo fixture:** Single bracket covering ₱11,000+
- Employee share: **₱540.00**
- Employer share: **₱1,140.00**

### 8.3 PhilHealth

```
basis  = clamp(EEMR, basis_floor, basis_ceiling)   (NULL floor/ceiling = no clamp)
employee_share = basis × employee_share_decimal
employer_share = basis × (rate_decimal − employee_share_decimal)
```

Rounded half-up to two decimal places.

**Demo fixture:** 4% total (`rate_decimal = 0.040000`), split equally
(`employee_share_decimal = 0.020000`), no floor/ceiling clamp.
- Employee share: EEMR × 0.02 = ₱11,998.33 × 0.02 = **₱239.97** (half-up)
- Employer share: **₱239.97**

### 8.4 Pag-IBIG

Two modes (configured in `pagibig_rate`):

**Fixed amount mode (demo fixture):**
- Employee share: **₱200.00** (fixed)
- Employer share: **₱200.00** (fixed)

**Rate mode (when `employee_fixed_amount IS NULL`):**
```
basis         = min(basis_ceiling, EEMR)
employee_share = basis × rate_decimal
```

### 8.5 Income Tax

Not withheld for documented demo employees because their annual income is
at or below the ₱250,000 threshold (`annual_tax_threshold` in
`payroll_policy_version`). The threshold is a policy configuration, not a
permanent universal exemption. `IncomeTax` deduction type is reserved in the
schema for future implementation.

---

## 9. Late Deduction

```
late_deduction = total_late_minutes × late_rate_per_minute
```

- `total_late_minutes` = sum of `attendance.late_minutes` across all counted attendance rows in the period.
- `late_rate_per_minute` = **₱1.00/minute** (ADR-0001 default; stored in `payroll_policy_version`).
- Rounded half-up to two decimal places.

---

## 10. Undertime Deduction

```
minute_rate        = daily_rate / standard_minutes   (standard_minutes default: 480)
undertime_deduction = total_undertime_minutes × minute_rate
```

- `total_undertime_minutes` = sum of `attendance.undertime_minutes` across all counted rows.
- Rounded half-up to two decimal places.

---

## 11. Absence Deduction (Unpaid)

An **unpaid absence day** is a scheduled working day where:

1. The employee has **no** attendance record for that date, **and**
2. There is **no** approved leave request covering that date, **and**
3. The date is not a holiday, **and**
4. The date is covered by an **Approved/Completed** attendance import batch
   for the employee's device/branch (meaning the device was active — absence
   is confirmed rather than a data gap), **and**
5. The date is in the past.

Unpaid absence days are **already excluded from Basic Pay** (they are not
counted in `days_worked`). A `deduction` row with type `Absence` and
`amount = 0.00` is inserted **for audit and payslip visibility only** —
it does not reduce net pay a second time.

---

## 12. Cash Advance Repayment

```
repayment = min(₱500.00, remaining_balance)
```

- ₱500.00 is the fixed weekly repayment amount (ADR-0001).
- Sourced from the most-recent `cash_advance_history` row with `status = 'Active'`.
- After deduction, the `remaining_balance` is decremented transactionally;
  if balance reaches ₱0.00, `status` is set to `'Settled'`.
- Only one active cash advance obligation is repaid per payroll run.

### Cash Advance Approval Flow

```
Employee submits → Pending
HR pre-approves  → HRApproved
Owner approves   → Approved  (cash_advance_history row created here)
HR/Owner cancels → Cancelled
```

The `cash_advance_history` row (obligation record) is created **only on Owner
final approval**, at which point repayment deductions begin on the next payroll run.

---

## 13. Leave Request Approval Flow

```
Employee submits → Pending
HR pre-approves  → HRApproved
Owner approves   → Approved  (leave_ledger debit created here)
Owner returns    → Returned  (HR must re-submit)
HR/Owner cancels → Cancelled
```

- A `leave_ledger` debit is written only on final Owner approval.
- Leave balance is checked at submission time, not at approval time.
- Leave type is currently `Sick` (4 days/year).

---

## 14. Overtime Request Approval Flow

```
Employee submits → Pending
HR pre-approves  → HRApproved
Owner approves   → Approved  (payroll engine uses this to authorize OT pay)
Owner returns    → Returned
HR/Owner cancels → Cancelled
```

Approved OT requests are matched against actual attendance by
`ApprovedOvertimeCalculator` at payroll computation time.

---

## 15. Gross Pay and Net Pay Summary

```
Gross Pay = Basic Pay
          + Paid Leave Pay
          + Overtime Pay (regular OT premium)
          + Holiday Day Premium (above Basic Pay)
          + Holiday OT Premium (above regular OT)

Total Deductions = SSS employee share      (last-Friday of month only)
                 + PhilHealth employee share (last-Friday of month only)
                 + Pag-IBIG employee share   (last-Friday of month only)
                 + Late Deduction
                 + Undertime Deduction
                 + Cash Advance Repayment
                 [+ Income Tax — not active for demo employees]

Net Pay = max(0, Gross Pay − Total Deductions)
```

Net pay is floored at ₱0.00 — it cannot go negative.

---

## 16. Payroll Run Lifecycle

```
Draft → Computed → PendingOwnerApproval → Approved
                                        → Returned → (re-Computed) → PendingOwnerApproval
     → Cancelled  (any pre-Approved state, with cancellation reason)
```

- **Approved** runs are **immutable**. Child rows (`payroll`, `payroll_earnings`,
  `deduction`, `contribution_record`, `payslip`) cannot be modified after approval.
- A **Cancelled** run releases its period+branch slot. HR can open a new run
  for the same period and branch.
- A run with **manual adjustments** (`payroll_adjustment` rows) **cannot be
  recomputed** — it must be submitted as-is or cancelled.

---

## 17. Rounding

All monetary amounts use **half-up rounding to 2 decimal places** (centavos)
at each component boundary. This is the `HalfUp` mode stored in
`payroll_policy_version.rounding_mode`.

---

## 18. Demo Fixture Reference (ADR-0001)

| Parameter | Value |
|---|---|
| Daily rate | ₱460.00 |
| EEMR (313 days / 12) | ₱11,998.33 |
| SSS employee share | ₱540.00 |
| SSS employer share | ₱1,140.00 |
| PhilHealth employee share | ₱239.97 |
| PhilHealth employer share | ₱239.97 |
| Pag-IBIG employee share | ₱200.00 (fixed) |
| Pag-IBIG employer share | ₱200.00 (fixed) |
| Income tax | ₱0.00 (below ₱250,000 threshold) |
| Late rate | ₱1.00/minute |
| Cash advance repayment | ₱500.00/week |
| Standard working minutes | 480 min (8 h) |

> ⚠ Demo values only. Production statutory rates require HR-confirmed,
> formally-approved contribution tables before use.
