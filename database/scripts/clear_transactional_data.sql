-- WBPMS — transactional-data reset (production-safe by default)
--
-- Purpose
--   Clear operational transactions while preserving employee master data and
--   application settings. The script DOES NOT drop tables or alter schema.
--
-- Preserved
--   employees and their accounts, addresses, documents, salary and bank setup;
--   branches, schedules, biometric-device configuration, job positions,
--   holidays, request types, payroll/contribution policies and rate tables;
--   Phinx migration history.
--
-- Cleared
--   attendance/import data, payroll runs and all payroll-generated earnings,
--   deductions and contributions, requests, leave/cash-advance balances,
--   HR notices, audit logs, sessions and password-reset challenges.
--
-- Safety guard
--   The default confirmation value performs no deletes. To execute, replace
--   the value below with CLEAR_TRANSACTIONS after a verified backup and
--   maintenance window.

SET @wbpms_confirmation = 'DRY_RUN';

-- Pre-flight report: review these counts before enabling the confirmation.
SELECT 'attendance' AS table_name, COUNT(*) AS rows_to_clear FROM attendance
UNION ALL SELECT 'attendance_adjustment', COUNT(*) FROM attendance_adjustment
UNION ALL SELECT 'attendance_import_batch', COUNT(*) FROM attendance_import_batch
UNION ALL SELECT 'attendance_policy_flag', COUNT(*) FROM attendance_policy_flag
UNION ALL SELECT 'attendance_punch', COUNT(*) FROM attendance_punch
UNION ALL SELECT 'biometric_punch', COUNT(*) FROM biometric_punch
UNION ALL SELECT 'request', COUNT(*) FROM request
UNION ALL SELECT 'leave_entitlement', COUNT(*) FROM leave_entitlement
UNION ALL SELECT 'leave_ledger', COUNT(*) FROM leave_ledger
UNION ALL SELECT 'cash_advance_history', COUNT(*) FROM cash_advance_history
UNION ALL SELECT 'cash_advance_repayment', COUNT(*) FROM cash_advance_repayment
UNION ALL SELECT 'payroll_period', COUNT(*) FROM payroll_period
UNION ALL SELECT 'payroll_run', COUNT(*) FROM payroll_run
UNION ALL SELECT 'payroll', COUNT(*) FROM payroll
UNION ALL SELECT 'payroll_earnings', COUNT(*) FROM payroll_earnings
UNION ALL SELECT 'deduction', COUNT(*) FROM deduction
UNION ALL SELECT 'contribution_record', COUNT(*) FROM contribution_record
UNION ALL SELECT 'payslip', COUNT(*) FROM payslip
UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs
ORDER BY table_name;

START TRANSACTION;

-- Session/security operational records.
DELETE FROM password_reset_challenge
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM sessions
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';

-- Attendance: delete dependent records before their parents.
DELETE FROM employee_hr_notice
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM attendance_punch
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM attendance_adjustment
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM attendance_policy_flag
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM attendance
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM biometric_punch
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM attendance_import_batch
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';

-- Payroll/disbursement: delete detail records before payroll headers/periods.
DELETE FROM deposit_slip
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM disbursement_batch
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM cash_advance_repayment
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM payroll_adjustment
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM contribution_record
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM payslip
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM payroll_earnings
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM deduction
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM payroll
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM payroll_run
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM payroll_period
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';

-- Requests and derived leave/cash-advance balances.
DELETE FROM leave_ledger
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM cash_advance_history
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM leave_request_detail
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM overtime_request_detail
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM cash_advance_request_detail
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM request
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';
DELETE FROM leave_entitlement
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';

-- Operational history, retaining employee/account master records themselves.
DELETE FROM audit_logs
WHERE @wbpms_confirmation = 'CLEAR_TRANSACTIONS';

COMMIT;

SELECT CASE
    WHEN @wbpms_confirmation = 'CLEAR_TRANSACTIONS'
        THEN 'Transactional-data reset completed. Review the post-flight counts.'
    ELSE 'Dry run only. No data was deleted; set @wbpms_confirmation to CLEAR_TRANSACTIONS to execute.'
END AS result;

-- Post-flight verification. Every table below should be zero after execution.
SELECT 'attendance' AS table_name, COUNT(*) AS remaining_rows FROM attendance
UNION ALL SELECT 'attendance_adjustment', COUNT(*) FROM attendance_adjustment
UNION ALL SELECT 'attendance_import_batch', COUNT(*) FROM attendance_import_batch
UNION ALL SELECT 'attendance_policy_flag', COUNT(*) FROM attendance_policy_flag
UNION ALL SELECT 'attendance_punch', COUNT(*) FROM attendance_punch
UNION ALL SELECT 'biometric_punch', COUNT(*) FROM biometric_punch
UNION ALL SELECT 'request', COUNT(*) FROM request
UNION ALL SELECT 'leave_entitlement', COUNT(*) FROM leave_entitlement
UNION ALL SELECT 'leave_ledger', COUNT(*) FROM leave_ledger
UNION ALL SELECT 'cash_advance_history', COUNT(*) FROM cash_advance_history
UNION ALL SELECT 'cash_advance_repayment', COUNT(*) FROM cash_advance_repayment
UNION ALL SELECT 'payroll_period', COUNT(*) FROM payroll_period
UNION ALL SELECT 'payroll_run', COUNT(*) FROM payroll_run
UNION ALL SELECT 'payroll', COUNT(*) FROM payroll
UNION ALL SELECT 'payroll_earnings', COUNT(*) FROM payroll_earnings
UNION ALL SELECT 'deduction', COUNT(*) FROM deduction
UNION ALL SELECT 'contribution_record', COUNT(*) FROM contribution_record
UNION ALL SELECT 'payslip', COUNT(*) FROM payslip
UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs
ORDER BY table_name;
