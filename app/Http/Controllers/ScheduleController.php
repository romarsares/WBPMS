<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\ScheduleRepository;

/**
 * ScheduleController — HR-only work schedule management.
 *
 * Reusable schedules (no employee coupling):
 *   GET  /hr/schedules           → index()      — schedule list + stats + holiday panel
 *   POST /hr/schedules           → store()      — create reusable schedule
 *   GET  /hr/schedules/{id}/edit → editForm()   — edit schedule form
 *   POST /hr/schedules/{id}      → update()     — save schedule changes
 *
 * Employee schedule assignment (separate concern):
 *   GET  /hr/schedules/assign    → assignForm() — assignment form
 *   POST /hr/schedules/assign    → assign()     — persist assignment
 *
 * REQ025–REQ031.
 */
final class ScheduleController
{
    // =========================================================================
    // Schedule template CRUD
    // =========================================================================

    /**
     * GET /hr/schedules
     *
     * @param array<string, string> $params
     */
    public function index(array $params = []): void
    {
        $repo      = $this->repo();
        $schedules = $repo->findAll();
        $holidays  = $repo->upcomingHolidays();
        $assignments = $repo->findAllAssignments();

        $total    = count($schedules);
        $active   = count(array_filter($schedules, fn($s) => strtolower($s['status']) === 'active'));
        $hTotal   = count($holidays);
        $upcoming = count(array_filter($holidays, fn($h) => $h['holiday_date'] >= date('Y-m-d')));

        ViewRenderer::render('hr/schedules/index', [
            'schedules'   => $schedules,
            'holidays'    => $holidays,
            'assignments' => $assignments,
            'total'       => $total,
            'active'      => $active,
            'hTotal'      => $hTotal,
            'upcoming'    => $upcoming,
            'errors'      => [],
        ], 'Work Schedules');
    }

    /**
     * POST /hr/schedules
     *
     * @param array<string, string> $params
     */
    public function store(array $params = []): void
    {
        $data   = $this->extractScheduleFields();
        $errors = $this->validateSchedule($data);

        if ($errors !== []) {
            $repo = $this->repo();
            ViewRenderer::render('hr/schedules/index', [
                'schedules'   => $repo->findAll(),
                'holidays'    => $repo->upcomingHolidays(),
                'assignments' => $repo->findAllAssignments(),
                'total'       => 0, 'active' => 0, 'hTotal' => 0, 'upcoming' => 0,
                'errors'      => $errors,
                'input'       => $data,
            ], 'Work Schedules');
            return;
        }

        $this->repo()->create([
            'schedule_name'     => $data['schedule_name'],
            'working_days'      => $this->buildWorkingDaysJson($data['work_days']),
            'rest_days'         => $this->buildRestDaysJson($data['work_days']),
            'work_start_time'   => $data['time_in'],
            'work_end_time'     => $data['time_out'],
            'standard_minutes'  => $this->computeStandardMinutes($data['time_in'], $data['time_out'], (int) $data['break_minutes']),
            'break_minutes'     => (int) $data['break_minutes'],
            'grace_minutes'     => (int) $data['grace_minutes'],
            'overtime_allowed'  => (int) $data['overtime_allowed'],
            'break_start_time'  => $data['break_start'] !== '' ? $data['break_start'] : null,
            'break_end_time'    => $data['break_end'] !== '' ? $data['break_end'] : null,
            'notes'             => $data['notes'] !== '' ? $data['notes'] : null,
            'effective_from'    => $data['effective_from'] !== '' ? $data['effective_from'] : date('Y-m-d'),
            'status'            => $data['status'] !== '' ? ucfirst($data['status']) : 'Active',
        ]);

        ViewRenderer::flash('Work schedule created.');
        $this->redirect('/hr/schedules');
    }

    /**
     * GET /hr/schedules/{id}/edit
     *
     * @param array<string, string> $params
     */
    public function editForm(array $params = []): void
    {
        $id       = (int) ($params['id'] ?? 0);
        $schedule = $this->repo()->findById($id);

        if ($schedule === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ViewRenderer::render('hr/schedules/edit', [
            'schedule' => $schedule,
            'errors'   => [],
        ], 'Edit Schedule');
    }

    /**
     * POST /hr/schedules/{id}  (with _method=PUT)
     *
     * @param array<string, string> $params
     */
    public function update(array $params = []): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $repo = $this->repo();

        if ($repo->findById($id) === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $data   = $this->extractScheduleFields();
        $errors = $this->validateSchedule($data);

        if ($errors !== []) {
            ViewRenderer::render('hr/schedules/edit', [
                'schedule' => array_merge(['id' => $id], $data),
                'errors'   => $errors,
            ], 'Edit Schedule');
            return;
        }

        $repo->update($id, [
            'schedule_name'     => $data['schedule_name'],
            'working_days'      => $this->buildWorkingDaysJson($data['work_days']),
            'rest_days'         => $this->buildRestDaysJson($data['work_days']),
            'work_start_time'   => $data['time_in'],
            'work_end_time'     => $data['time_out'],
            'standard_minutes'  => $this->computeStandardMinutes($data['time_in'], $data['time_out'], (int) $data['break_minutes']),
            'break_minutes'     => (int) $data['break_minutes'],
            'grace_minutes'     => (int) $data['grace_minutes'],
            'overtime_allowed'  => (int) $data['overtime_allowed'],
            'break_start_time'  => $data['break_start'] !== '' ? $data['break_start'] : null,
            'break_end_time'    => $data['break_end'] !== '' ? $data['break_end'] : null,
            'notes'             => $data['notes'] !== '' ? $data['notes'] : null,
            'effective_from'    => $data['effective_from'] !== '' ? $data['effective_from'] : date('Y-m-d'),
            'status'            => $data['status'] !== '' ? ucfirst($data['status']) : 'Active',
        ]);

        ViewRenderer::flash('Work schedule updated.');
        $this->redirect('/hr/schedules/' . $id . '/edit');
    }

    // =========================================================================
    // Employee schedule assignment
    // =========================================================================

    /**
     * GET /hr/schedules/assign
     *
     * @param array<string, string> $params
     */
    public function assignForm(array $params = []): void
    {
        $repo = $this->repo();
        ViewRenderer::render('hr/schedules/assign', [
            'employees' => $repo->employeeList(),
            'schedules' => $repo->dropdownList(),
            'errors'    => [],
        ], 'Assign Schedule');
    }

    /**
     * POST /hr/schedules/assign
     *
     * @param array<string, string> $params
     */
    public function assign(array $params = []): void
    {
        $data   = $this->extractAssignFields();
        $errors = $this->validateAssign($data);

        if ($errors !== []) {
            $repo = $this->repo();
            ViewRenderer::render('hr/schedules/assign', [
                'employees' => $repo->employeeList(),
                'schedules' => $repo->dropdownList(),
                'errors'    => $errors,
                'input'     => $data,
            ], 'Assign Schedule');
            return;
        }

        $this->repo()->assign([
            'employee_id'    => (int) $data['employee_id'],
            'schedule_id'    => (int) $data['schedule_id'],
            'effective_from' => $data['effective_from'],
            'notes'          => $data['notes'] !== '' ? $data['notes'] : null,
        ]);

        ViewRenderer::flash('Schedule assigned to employee.');
        $this->redirect('/hr/schedules');
    }

    // =========================================================================
    // Holiday Calendar CRUD
    // =========================================================================

    /**
     * GET /hr/schedules/holidays
     * Full holiday management list.
     *
     * @param array<string, string> $params
     */
    public function holidayIndex(array $params = []): void
    {
        $holidays = $this->repo()->findAllHolidays();

        ViewRenderer::render('hr/schedules/holidays', [
            'holidays' => $holidays,
            'errors'   => [],
        ], 'Holiday Calendar');
    }

    /**
     * POST /hr/schedules/holidays
     * Create a new holiday.
     *
     * @param array<string, string> $params
     */
    public function storeHoliday(array $params = []): void
    {
        $data   = $this->extractHolidayFields();
        $errors = $this->validateHoliday($data);

        if ($errors === []) {
            try {
                $this->repo()->createHoliday([
                    'holiday_date' => $data['holiday_date'],
                    'description'  => $data['description'],
                    'holiday_type' => $data['holiday_type'],
                ]);
                ViewRenderer::flash('Holiday added.');
                $this->redirect('/hr/schedules/holidays');
                return;
            } catch (\RuntimeException $e) {
                $errors['holiday_date'] = $e->getMessage();
            }
        }

        ViewRenderer::render('hr/schedules/holidays', [
            'holidays' => $this->repo()->findAllHolidays(),
            'errors'   => $errors,
            'input'    => $data,
        ], 'Holiday Calendar');
    }

    /**
     * GET /hr/schedules/holidays/{id}/edit
     *
     * @param array<string, string> $params
     */
    public function editHoliday(array $params = []): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $holiday = $this->repo()->findHolidayById($id);

        if ($holiday === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ViewRenderer::render('hr/schedules/holiday_edit', [
            'holiday' => $holiday,
            'errors'  => [],
        ], 'Edit Holiday');
    }

    /**
     * POST /hr/schedules/holidays/{id}  (with _method=PUT)
     *
     * @param array<string, string> $params
     */
    public function updateHoliday(array $params = []): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $holiday = $this->repo()->findHolidayById($id);

        if ($holiday === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $data   = $this->extractHolidayFields();
        $errors = $this->validateHoliday($data);

        if ($errors === []) {
            try {
                $this->repo()->updateHoliday($id, [
                    'holiday_date' => $data['holiday_date'],
                    'description'  => $data['description'],
                    'holiday_type' => $data['holiday_type'],
                    'status'       => $data['status'] !== '' ? $data['status'] : 'Active',
                ]);
                ViewRenderer::flash('Holiday updated.');
                $this->redirect('/hr/schedules/holidays');
                return;
            } catch (\RuntimeException $e) {
                $errors['holiday_date'] = $e->getMessage();
            }
        }

        ViewRenderer::render('hr/schedules/holiday_edit', [
            'holiday' => array_merge($holiday, ['holiday_id' => $id], $data),
            'errors'  => $errors,
        ], 'Edit Holiday');
    }

    /**
     * POST /hr/schedules/holidays/{id}/delete
     * Soft-deactivate a holiday.
     *
     * @param array<string, string> $params
     */
    public function deleteHoliday(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($this->repo()->findHolidayById($id) === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $this->repo()->deactivateHoliday($id);
        ViewRenderer::flash('Holiday deactivated.');
        $this->redirect('/hr/schedules/holidays');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * @return array<string, string>
     */
    private function extractScheduleFields(): array
    {
        return [
            'schedule_name'    => trim((string) ($_POST['schedule_name']    ?? '')),
            'work_days'        => $_POST['work_days'] ?? [],       // array of day names
            'time_in'          => trim((string) ($_POST['time_in']          ?? '')),
            'time_out'         => trim((string) ($_POST['time_out']         ?? '')),
            'break_minutes'    => trim((string) ($_POST['break_minutes']    ?? '60')),
            'break_start'      => trim((string) ($_POST['break_start']      ?? '')),
            'break_end'        => trim((string) ($_POST['break_end']        ?? '')),
            'grace_minutes'    => trim((string) ($_POST['grace_minutes']    ?? '0')),
            'overtime_allowed' => trim((string) ($_POST['overtime_allowed'] ?? '1')),
            'notes'            => trim((string) ($_POST['notes']            ?? '')),
            'effective_from'   => trim((string) ($_POST['effective_from']   ?? '')),
            'status'           => trim((string) ($_POST['status']           ?? 'Active')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractAssignFields(): array
    {
        return [
            'employee_id'    => trim((string) ($_POST['employee_id']    ?? '')),
            'schedule_id'    => trim((string) ($_POST['schedule_id']    ?? '')),
            'effective_from' => trim((string) ($_POST['effective_from'] ?? '')),
            'notes'          => trim((string) ($_POST['notes']          ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateSchedule(array $data): array
    {
        $errors = [];

        if (trim((string) $data['schedule_name']) === '') {
            $errors['schedule_name'] = 'Schedule name is required.';
        }

        $workDays = is_array($data['work_days']) ? $data['work_days'] : [];
        if ($workDays === []) {
            $errors['work_days'] = 'At least one working day must be selected.';
        }

        if ($data['time_in'] === '') {
            $errors['time_in'] = 'Time in is required.';
        }

        if ($data['time_out'] === '') {
            $errors['time_out'] = 'Time out is required.';
        }

        if ($data['time_in'] !== '' && $data['time_out'] !== ''
            && $data['time_in'] >= $data['time_out']) {
            $errors['time_out'] = 'Time out must be after time in.';
        }

        $breakMin = (int) $data['break_minutes'];
        if ($breakMin < 0 || $breakMin > 480) {
            $errors['break_minutes'] = 'Break time must be between 0 and 480 minutes.';
        }

        $grace = (int) $data['grace_minutes'];
        if ($grace < 0 || $grace > 120) {
            $errors['grace_minutes'] = 'Grace period must be between 0 and 120 minutes.';
        }

        return $errors;
    }

    /**
     * @param  array<string, string> $data
     * @return array<string, string>
     */
    private function validateAssign(array $data): array
    {
        $errors = [];

        if ($data['employee_id'] === '') {
            $errors['employee_id'] = 'Employee is required.';
        }

        if ($data['schedule_id'] === '') {
            $errors['schedule_id'] = 'Schedule is required.';
        }

        if ($data['effective_from'] === '') {
            $errors['effective_from'] = 'Effective date is required.';
        }

        return $errors;
    }

    /**
     * Build a JSON array of working day names from a checkbox array.
     * Input is an array of full day names: ['Monday','Tuesday',...].
     *
     * @param  mixed $workDays
     */
    private function buildWorkingDaysJson(mixed $workDays): string
    {
        $valid = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $days  = is_array($workDays)
            ? array_values(array_intersect($valid, $workDays))
            : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        return json_encode($days, JSON_THROW_ON_ERROR);
    }

    /**
     * Derive rest days as the complement of working days.
     *
     * @param mixed $workDays
     */
    private function buildRestDaysJson(mixed $workDays): string
    {
        $valid = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $days  = is_array($workDays)
            ? array_values(array_intersect($valid, $workDays))
            : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $rest  = array_values(array_diff($valid, $days));
        return json_encode($rest, JSON_THROW_ON_ERROR);
    }

    /**
     * Compute net paid minutes (gross span minus unpaid break).
     */
    private function computeStandardMinutes(string $timeIn, string $timeOut, int $breakMinutes): int
    {
        if ($timeIn === '' || $timeOut === '') {
            return 480;
        }
        [$h1, $m1] = array_map('intval', explode(':', $timeIn));
        [$h2, $m2] = array_map('intval', explode(':', $timeOut));
        $total = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
        return max(0, $total - $breakMinutes);
    }

    private function repo(): ScheduleRepository
    {
        return new ScheduleRepository(new Connection(require APP_ROOT . '/config/database.php'));
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    /**
     * @return array<string, string>
     */
    private function extractHolidayFields(): array
    {
        return [
            'holiday_date' => trim((string) ($_POST['holiday_date'] ?? '')),
            'description'  => trim((string) ($_POST['description']  ?? '')),
            'holiday_type' => trim((string) ($_POST['holiday_type'] ?? '')),
            'status'       => trim((string) ($_POST['status']       ?? 'Active')),
        ];
    }

    /**
     * @param  array<string, string> $data
     * @return array<string, string>
     */
    private function validateHoliday(array $data): array
    {
        $errors = [];

        if ($data['holiday_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['holiday_date'])) {
            $errors['holiday_date'] = 'Holiday date is required (YYYY-MM-DD).';
        }
        if ($data['description'] === '') {
            $errors['description'] = 'Holiday name / description is required.';
        }
        if (!in_array($data['holiday_type'], ['Regular', 'Special'], true)) {
            $errors['holiday_type'] = 'Holiday type must be Regular or Special.';
        }

        return $errors;
    }
}
