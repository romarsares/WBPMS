<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migration 002: Organization and Employee master data.
 *
 * Creates: branch, attendance_site, biometric_device, biometric_device_branch,
 *          province, city, barangay, address, employee, employment_contract_review,
 *          employee_branch_assignment, employee_biometric_enrollment,
 *          work_schedule, holiday_calendar
 *
 * Also adds the deferred FK: users.employee_id → employee.employee_id
 *
 * ADR-0001: configurable topology; no hardcoded counts.
 * ADR-0002 §8: effective periods use half-open [from, to); current_guard unique index.
 * Canonical schema v1.1: Organization and employee master data section.
 */
final class CreateOrganizationEmployeeTables extends AbstractMigration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // branch
        // -------------------------------------------------------------------
        $this->table('branch', [
            'id'          => false,
            'primary_key' => ['branch_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Configurable organizational branches',
        ])
            ->addColumn('branch_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('branch_code', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('branch_name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('location', 'string', ['limit' => 200, 'null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive', 'Archived'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['branch_code'], ['unique' => true, 'name' => 'uq_branch_code'])
            ->create();

        // -------------------------------------------------------------------
        // attendance_site
        // -------------------------------------------------------------------
        $this->table('attendance_site', [
            'id'          => false,
            'primary_key' => ['site_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Physical locations where biometric devices are installed',
        ])
            ->addColumn('site_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('site_code', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('site_name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('address', 'string', ['limit' => 200, 'null' => true, 'default' => null])
            ->addColumn('timezone', 'string', ['limit' => 50, 'null' => false, 'default' => 'Asia/Manila'])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['site_code'], ['unique' => true, 'name' => 'uq_site_code'])
            ->create();

        // -------------------------------------------------------------------
        // biometric_device
        // -------------------------------------------------------------------
        $this->table('biometric_device', [
            'id'          => false,
            'primary_key' => ['device_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Registered biometric devices; one device may cover multiple branches',
        ])
            ->addColumn('device_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('site_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('device_code', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('device_name', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('serial_number', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('file_format', 'string', ['limit' => 50, 'null' => false, 'default' => 'LDE_XLS_DAILY_LOG_V1'])
            ->addColumn('timezone', 'string', ['limit' => 50, 'null' => false, 'default' => 'Asia/Manila'])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive', 'Retired'], 'null' => false, 'default' => 'Active'])
            ->addColumn('installed_at', 'date', ['null' => true, 'default' => null])
            ->addColumn('retired_at', 'date', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['device_code'], ['unique' => true, 'name' => 'uq_device_code'])
            ->addIndex(['serial_number'], ['unique' => true, 'name' => 'uq_device_serial'])
            ->addForeignKey('site_id', 'attendance_site', 'site_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // biometric_device_branch — effective-dated device-to-branch coverage
        // -------------------------------------------------------------------
        $this->table('biometric_device_branch', [
            'id'          => false,
            'primary_key' => ['device_branch_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Effective-dated coverage: which branches a device serves',
        ])
            ->addColumn('device_branch_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('device_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('branch_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['device_id', 'branch_id', 'effective_from'], ['unique' => true, 'name' => 'uq_dbb_device_branch_from'])
            ->addForeignKey('device_id', 'biometric_device', 'device_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('branch_id', 'branch', 'branch_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE biometric_device_branch ADD CONSTRAINT chk_dbb_period "
            . "CHECK (effective_to IS NULL OR effective_to > effective_from)");

        // -------------------------------------------------------------------
        // province / city / barangay / address  (flat geography)
        // -------------------------------------------------------------------
        $this->table('province', [
            'id'          => false,
            'primary_key' => ['province_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('province_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('province_name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['province_name'], ['unique' => true, 'name' => 'uq_province_name'])
            ->create();

        $this->table('city', [
            'id'          => false,
            'primary_key' => ['city_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('city_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('city_name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['city_name'], ['name' => 'idx_city_name'])
            ->create();

        $this->table('barangay', [
            'id'          => false,
            'primary_key' => ['barangay_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('barangay_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('barangay_name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['barangay_name'], ['name' => 'idx_barangay_name'])
            ->create();

        $this->table('address', [
            'id'          => false,
            'primary_key' => ['address_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Flat address structure; all geography references optional',
        ])
            ->addColumn('address_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('street', 'string', ['limit' => 150, 'null' => true, 'default' => null])
            ->addColumn('province_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('city_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('barangay_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('province_id', 'province', 'province_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('city_id', 'city', 'city_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('barangay_id', 'barangay', 'barangay_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // employee
        // -------------------------------------------------------------------
        $this->table('employee', [
            'id'          => false,
            'primary_key' => ['employee_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Core employee record; employee_number is immutable after creation',
        ])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_number', 'string', ['limit' => 30, 'null' => false, 'comment' => 'Immutable after creation'])
            ->addColumn('employee_type', 'enum', ['values' => ['Regular', 'Contractual'], 'null' => false])
            ->addColumn('first_name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('middle_initial', 'string', ['limit' => 5, 'null' => true, 'default' => null])
            ->addColumn('last_name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->addColumn('contact_number', 'string', ['limit' => 30, 'null' => true, 'default' => null])
            ->addColumn('birthdate', 'date', ['null' => true, 'default' => null])
            ->addColumn('hire_date', 'date', ['null' => false])
            ->addColumn('id_picture', 'string', ['limit' => 500, 'null' => true, 'default' => null, 'comment' => 'Path in storage/private/'])
            ->addColumn('address_id', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive', 'Separated', 'Archived'], 'null' => false, 'default' => 'Active'])
            ->addColumn('philhealth_number', 'string', ['limit' => 30, 'null' => true, 'default' => null])
            ->addColumn('pagibig_number', 'string', ['limit' => 30, 'null' => true, 'default' => null])
            ->addColumn('tin_number', 'string', ['limit' => 30, 'null' => true, 'default' => null])
            ->addColumn('position', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('contract_review_date', 'date', ['null' => true, 'default' => null, 'comment' => 'Required for Contractual; populated at creation'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_number'], ['unique' => true, 'name' => 'uq_employee_number'])
            ->addIndex(['email'], ['unique' => true, 'name' => 'uq_employee_email'])
            ->addForeignKey('address_id', 'address', 'address_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // Now add the deferred FK on users.employee_id
        $this->table('users')
            ->addForeignKey('employee_id', 'employee', 'employee_id', [
                'delete' => 'SET NULL',
                'update' => 'CASCADE',
            ])
            ->save();

        // -------------------------------------------------------------------
        // employment_contract_review
        // -------------------------------------------------------------------
        $this->table('employment_contract_review', [
            'id'          => false,
            'primary_key' => ['review_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Contractual employee review outcomes',
        ])
            ->addColumn('review_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('review_due_date', 'date', ['null' => false])
            ->addColumn('outcome', 'enum', ['values' => ['Regularized', 'Renewed', 'Separated'], 'null' => false])
            ->addColumn('effective_date', 'date', ['null' => false])
            ->addColumn('next_review_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('reviewed_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('notes', 'string', ['limit' => 1000, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'effective_date'], ['unique' => true, 'name' => 'uq_ecr_employee_effective'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('reviewed_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        // -------------------------------------------------------------------
        // employee_branch_assignment  (effective-dated; no-overlap enforced by service)
        // -------------------------------------------------------------------
        $eba = $this->table('employee_branch_assignment', [
            'id'          => false,
            'primary_key' => ['branch_assignment_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Effective-dated branch membership; one assignment per employee per date',
        ]);
        $eba
            ->addColumn('branch_assignment_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('branch_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('transfer_reason', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('transferred_by', 'biginteger', ['signed' => false, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'effective_from'], ['unique' => true, 'name' => 'uq_eba_employee_from'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('branch_id', 'branch', 'branch_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('transferred_by', 'users', 'user_id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE employee_branch_assignment ADD CONSTRAINT chk_eba_period "
            . "CHECK (effective_to IS NULL OR effective_to > effective_from)");

        // -------------------------------------------------------------------
        // employee_biometric_enrollment  (effective-dated)
        // -------------------------------------------------------------------
        $ebe = $this->table('employee_biometric_enrollment', [
            'id'          => false,
            'primary_key' => ['enrollment_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Maps device enrollment code to employee; preserves leading zeroes',
        ]);
        $ebe
            ->addColumn('enrollment_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('device_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('device_employee_code', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Enrollment ID from workbook; leading zeroes preserved'])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['device_id', 'device_employee_code', 'effective_from'], ['unique' => true, 'name' => 'uq_ebe_device_code_from'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('device_id', 'biometric_device', 'device_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE employee_biometric_enrollment ADD CONSTRAINT chk_ebe_period "
            . "CHECK (effective_to IS NULL OR effective_to > effective_from)");

        // -------------------------------------------------------------------
        // work_schedule  (effective-dated; per-employee)
        // -------------------------------------------------------------------
        $ws = $this->table('work_schedule', [
            'id'          => false,
            'primary_key' => ['schedule_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Employee work schedule; working_days and rest_days stored as JSON arrays',
        ]);
        $ws
            ->addColumn('schedule_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('employee_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('working_days', 'text', ['null' => false, 'comment' => 'JSON array of day names e.g. ["Monday","Tuesday",...]'])
            ->addColumn('rest_days', 'text', ['null' => false, 'comment' => 'JSON array of day names e.g. ["Saturday","Sunday"]'])
            ->addColumn('break_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 60])
            ->addColumn('work_start_time', 'time', ['null' => false, 'comment' => 'e.g. 07:00:00'])
            ->addColumn('work_end_time', 'time', ['null' => false, 'comment' => 'e.g. 16:00:00'])
            ->addColumn('standard_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'comment' => 'Paid minutes per day e.g. 480'])
            ->addColumn('effective_from', 'date', ['null' => false])
            ->addColumn('effective_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Archived'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['employee_id', 'effective_from'], ['unique' => true, 'name' => 'uq_ws_employee_from'])
            ->addForeignKey('employee_id', 'employee', 'employee_id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->execute("ALTER TABLE work_schedule ADD CONSTRAINT chk_ws_period "
            . "CHECK (effective_to IS NULL OR effective_to > effective_from)");

        // -------------------------------------------------------------------
        // holiday_calendar
        // -------------------------------------------------------------------
        $this->table('holiday_calendar', [
            'id'          => false,
            'primary_key' => ['holiday_id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'comment'     => 'Public holidays; Regular=200%, Special=130% pay multiplier per ADR-0001',
        ])
            ->addColumn('holiday_id', 'biginteger', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('holiday_date', 'date', ['null' => false])
            ->addColumn('description', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('holiday_type', 'enum', ['values' => ['Regular', 'Special'], 'null' => false])
            ->addColumn('pay_multiplier', 'decimal', ['precision' => 4, 'scale' => 2, 'null' => false])
            ->addColumn('status', 'enum', ['values' => ['Active', 'Inactive'], 'null' => false, 'default' => 'Active'])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['holiday_date'], ['unique' => true, 'name' => 'uq_holiday_date'])
            ->create();

        // Check: Regular holiday must have pay_multiplier=2.00; Special=1.30
        $this->execute("ALTER TABLE holiday_calendar ADD CONSTRAINT chk_holiday_multiplier "
            . "CHECK ((holiday_type='Regular' AND pay_multiplier=2.00) "
            . "OR (holiday_type='Special' AND pay_multiplier=1.30))");
    }

    public function down(): void
    {
        // Remove deferred FK before dropping employee
        $this->table('users')->dropForeignKey('employee_id')->save();

        $tables = [
            'holiday_calendar',
            'work_schedule',
            'employee_biometric_enrollment',
            'employee_branch_assignment',
            'employment_contract_review',
            'employee',
            'address',
            'barangay',
            'city',
            'province',
            'biometric_device_branch',
            'biometric_device',
            'attendance_site',
            'branch',
        ];

        foreach ($tables as $t) {
            if ($this->hasTable($t)) {
                $this->table($t)->drop()->save();
            }
        }
    }
}
