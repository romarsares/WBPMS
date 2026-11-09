-- =============================================================================
-- WBPMS — Test-backup cleanup: remove attendance data + payroll runs
-- =============================================================================
-- Purpose : Wipe all transactional attendance and payroll-run data from a TEST
--           backup so you can re-import attendance and recompute payroll cleanly.
-- Safety  : Back up the database first. This deletes all rows in the listed
--           transactional tables. Reference/master data is preserved:
--           employee, branch, biometric_device, work_schedule, payroll_period
--           (unless you uncomment section 3C), salary, contribution_policy_version,
--           payroll_policy_version, cash_advance_history.
--
-- Why this order: every table below uses ON DELETE RESTRICT foreign keys, so
-- child rows must be removed before their parents. payroll_earnings and
-- deduction reference attendance via ON DELETE SET NULL, so deleting attendance
-- would not block — but we delete the payroll side first anyway for clarity.
-- =============================================================================
-- Run:  mysql -u <user> -p <dbname> < cleanup-attendance-payroll.sql
--       or paste into your GUI client and execute.
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- SECTION 1 — Unlock any locked contribution records (OPTIONAL)
--   Skip this if you are about to run section 2 (locked rows are deleted there).
--   Useful only if you want to KEEP contribution records but clear their lock.
-- -----------------------------------------------------------------------------
-- UPDATE contribution_record
--    SET status = 'Computed', locked_at = NULL, locked_by = NULL, updated_at = NOW()
--  WHERE status = 'Locked';

-- -----------------------------------------------------------------------------
-- SECTION 2 — Payroll run + all dependent payroll data (children first)
--    All FKs below are ON DELETE RESTRICT, hence the child→parent order.
-- -----------------------------------------------------------------------------
DELETE FROM cash_advance_repayment;   -- FK → payroll, deduction, cash_advance_history (RESTRICT)
DELETE FROM contribution_record;      -- FK → payroll, deduction, contribution_policy_version (RESTRICT)
DELETE FROM payslip;                   -- FK → payroll (RESTRICT)
DELETE FROM payroll_earnings;          -- FK → payroll (RESTRICT); source_attendance_id → attendance (SET NULL)
DELETE FROM deduction;                -- FK → payroll (RESTRICT); source_attendance_id → attendance (SET NULL)
DELETE FROM payroll;                   -- FK → payroll_run (RESTRICT, composite), payroll_period/employee/salary (RESTRICT)
DELETE FROM payroll_run;               -- FK → payroll_period, branch, payroll_policy_version (RESTRICT)

-- -----------------------------------------------------------------------------
-- SECTION 3 — Attendance data (children first)
-- -----------------------------------------------------------------------------
DELETE FROM attendance_adjustment;    -- FK → attendance (RESTRICT)
DELETE FROM attendance_punch;         -- FK → attendance, biometric_punch (RESTRICT)
DELETE FROM attendance;               -- FK → employee, branch_assignment, import_batch, schedule (RESTRICT/SET NULL)
DELETE FROM biometric_punch;          -- FK → import_batch, device (RESTRICT)
DELETE FROM attendance_import_batch;  -- FK → device, users (RESTRICT)

-- -----------------------------------------------------------------------------
-- SECTION 3A — Clear all users except owner, hrhead, and support
--    sessions and password_reset_challenge reference users via ON DELETE RESTRICT,
--    so they must be cleared for the target users before deleting from users.
-- -----------------------------------------------------------------------------
DELETE FROM sessions
 WHERE user_id IN (SELECT user_id FROM users WHERE username NOT IN ('owner', 'hrhead', 'support'));
DELETE FROM password_reset_challenge
 WHERE user_id IN (SELECT user_id FROM users WHERE username NOT IN ('owner', 'hrhead', 'support'));
DELETE FROM users
 WHERE username NOT IN ('owner', 'hrhead', 'support');

-- -----------------------------------------------------------------------------
-- SECTION 3B — OPTIONAL: attendance-derived review flags
--   These reference employee (not attendance), so they do not block the deletes
--   above. Clear them only if you want a fully clean flag slate.
-- -----------------------------------------------------------------------------
-- DELETE FROM attendance_policy_flag;

-- -----------------------------------------------------------------------------
-- SECTION 3C — OPTIONAL: clear payroll periods too
--   Skip this if you want to reuse existing periods for re-runs. Safe to run
--   only AFTER payroll_run is deleted (payroll_run → payroll_period RESTRICT).
-- -----------------------------------------------------------------------------
-- DELETE FROM payroll_period;

-- -----------------------------------------------------------------------------
-- SECTION 4 — Employee management data (children first)
--    Clears all employee records and every table that references employee.
--    Must run AFTER sections 1–3A (payroll, attendance, and users already gone).
-- -----------------------------------------------------------------------------
DELETE FROM leave_ledger;                   -- FK → leave_entitlement, request, users (RESTRICT/SET NULL)
DELETE FROM leave_entitlement;              -- FK → employee (RESTRICT)
DELETE FROM employment_contract_review;     -- FK → employee, users (RESTRICT/SET NULL)
DELETE FROM employee_hr_notice;             -- FK → employee, attendance_policy_flag, users (RESTRICT)
DELETE FROM attendance_policy_flag;         -- FK → employee (RESTRICT)
DELETE FROM employee_lifecycle_event;       -- FK → employee, users (RESTRICT/SET NULL)
DELETE FROM employee_schedule_assignment;   -- FK → employee, work_schedule (RESTRICT)
DELETE FROM employee_biometric_enrollment;  -- FK → employee, biometric_device (RESTRICT)
DELETE FROM employee_document;              -- FK → employee, users (RESTRICT/SET NULL)
DELETE FROM cash_advance_history;           -- FK → employee (RESTRICT)
DELETE FROM salary;                         -- FK → employee, users (RESTRICT/SET NULL)
DELETE FROM request;                        -- FK → employee (RESTRICT)
DELETE FROM bank_details;                   -- FK → employee (RESTRICT)
DELETE FROM employee_branch_assignment;     -- FK → employee, branch, users (RESTRICT/SET NULL)
DELETE FROM employee_employment_episode;    -- FK → employee, users (RESTRICT/SET NULL)
DELETE FROM employee;                       -- FK → address (SET NULL)
DELETE FROM address;                        -- FK → province, city, barangay (SET NULL)

COMMIT;

-- =============================================================================
-- Verification (run after, separately):
--   SELECT COUNT(*) FROM payroll_run;            -- expect 0
--   SELECT COUNT(*) FROM payroll;                -- expect 0
--   SELECT COUNT(*) FROM attendance;             -- expect 0
--   SELECT COUNT(*) FROM attendance_import_batch; -- expect 0
--   SELECT COUNT(*) FROM contribution_record;    -- expect 0
--   SELECT COUNT(*) FROM employee;               -- expect 0
--   SELECT COUNT(*) FROM users WHERE username NOT IN ('owner','hrhead','support'); -- expect 0
-- =============================================================================

-- =============================================================================
-- ALTERNATIVE — target a single payroll run by id (instead of wiping all):
--   SET @run = 123;  -- payroll_run_id to remove
--   DELETE FROM cash_advance_repayment WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = @run);
--   DELETE FROM contribution_record     WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = @run);
--   DELETE FROM payslip                  WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = @run);
--   DELETE FROM payroll_earnings        WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = @run);
--   DELETE FROM deduction                WHERE payroll_id IN (SELECT payroll_id FROM payroll WHERE payroll_run_id = @run);
--   DELETE FROM payroll        WHERE payroll_run_id = @run;
--   DELETE FROM payroll_run     WHERE payroll_run_id = @run;
-- =============================================================================
-- ALTERNATIVE — target a single attendance import batch by id:
--   SET @batch = 7;
--   DELETE FROM attendance_adjustment WHERE attendance_id IN (SELECT attendance_id FROM attendance WHERE import_batch_id = @batch);
--   DELETE FROM attendance_punch       WHERE attendance_id IN (SELECT attendance_id FROM attendance WHERE import_batch_id = @batch);
--   DELETE FROM attendance        WHERE import_batch_id = @batch;
--   DELETE FROM biometric_punch    WHERE import_batch_id = @batch;
--   DELETE FROM attendance_import_batch WHERE import_batch_id = @batch;
-- =============================================================================
