<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Adds before/after overtime evidence to the append-only attendance adjustment log. */
final class AddAttendanceAdjustmentOvertimeColumns extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('attendance_adjustment');
        if (!$table->hasColumn('old_overtime_minutes')) {
            $table->addColumn('old_overtime_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0, 'after' => 'new_time_out'])->save();
        }
        if (!$table->hasColumn('new_overtime_minutes')) {
            $table->addColumn('new_overtime_minutes', 'smallinteger', ['signed' => false, 'null' => false, 'default' => 0, 'after' => 'old_overtime_minutes'])->save();
        }
    }

    public function down(): void
    {
        $table = $this->table('attendance_adjustment');
        if ($table->hasColumn('new_overtime_minutes')) {
            $table->removeColumn('new_overtime_minutes')->save();
        }
        if ($table->hasColumn('old_overtime_minutes')) {
            $table->removeColumn('old_overtime_minutes')->save();
        }
    }
}
