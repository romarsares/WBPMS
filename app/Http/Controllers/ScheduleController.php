<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\ScheduleRepository;

/**
 * ScheduleController — HR-only work schedule management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/schedules      → index()  — list + inline create form
 *   POST /hr/schedules      → store()  — persist new schedule
 *
 * REQ025–REQ031 (P0 subset): list/create only.
 * Full calendar view, holiday CRUD, and schedule archive are deferred.
 */
final class ScheduleController
{
    /**
     * GET /hr/schedules
     *
     * @param array<string, string> $params
     */
    public function index(array $params = []): void
    {
        $repo      = $this->makeRepo();
        $schedules = $repo->findAll();
        $employees = $repo->employeeList();

        ViewRenderer::render('hr/schedules/index', [
            'schedules' => $schedules,
            'employees' => $employees,
            'errors'    => [],
        ], 'Work Schedules');
    }

    /**
     * POST /hr/schedules
     *
     * @param array<string, string> $params
     */
    public function store(array $params = []): void
    {
        $data   = $this->extractPostFields();
        $errors = $this->validate($data);

        if ($errors !== []) {
            $repo      = $this->makeRepo();
            $schedules = $repo->findAll();
            $employees = $repo->employeeList();
            ViewRenderer::render('hr/schedules/index', [
                'schedules' => $schedules,
                'employees' => $employees,
                'errors'    => $errors,
            ], 'Work Schedules');
            return;
        }

        $this->makeRepo()->create([
            'employee_id'       => (int) $data['employee_id'],
            'working_days'      => $this->buildWorkingDaysJson($data['work_days'] ?? ''),
            'rest_days'         => '["Saturday","Sunday"]', // default; full editor deferred
            'work_start_time'   => $data['time_in'],
            'work_end_time'     => $data['time_out'],
            'standard_minutes'  => $this->computeStandardMinutes($data['time_in'], $data['time_out']),
            'break_minutes'     => 60,
            'effective_from'    => $data['effective_from'] !== '' ? $data['effective_from'] : date('Y-m-d'),
        ]);

        ViewRenderer::flash('Work schedule created.');
        $this->redirect('/hr/schedules');
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function extractPostFields(): array
    {
        return [
            'employee_id'   => trim((string) ($_POST['employee_id']   ?? '')),
            'name'          => trim((string) ($_POST['name']          ?? '')),
            'work_days'     => trim((string) ($_POST['work_days']     ?? '')),
            'time_in'       => trim((string) ($_POST['time_in']       ?? '')),
            'time_out'      => trim((string) ($_POST['time_out']      ?? '')),
            'grace_minutes' => trim((string) ($_POST['grace_minutes'] ?? '0')),
            'effective_from'=> trim((string) ($_POST['effective_from']?? '')),
        ];
    }

    /**
     * @param  array<string, string> $data
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        if ($data['employee_id'] === '') {
            $errors['employee_id'] = 'Employee is required.';
        }

        if ($data['time_in'] === '') {
            $errors['time_in'] = 'Time in is required.';
        }

        if ($data['time_out'] === '') {
            $errors['time_out'] = 'Time out is required.';
        }

        if ($data['time_in'] !== '' && $data['time_out'] !== '' && $data['time_in'] >= $data['time_out']) {
            $errors['time_out'] = 'Time out must be after time in.';
        }

        return $errors;
    }

    /**
     * Convert a human-readable work days string into a JSON array.
     * e.g. "Mon–Fri" → '["Monday","Tuesday","Wednesday","Thursday","Friday"]'
     */
    private function buildWorkingDaysJson(string $workDays): string
    {
        // If it looks like a day range abbreviation, expand it
        $all = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        // Try to detect "Mon-Fri" or "Mon–Fri" patterns and expand
        if (preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)[–\-–](Mon|Tue|Wed|Thu|Fri|Sat|Sun)$/i', $workDays, $m)) {
            $abbr = ['Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4, 'Sat' => 5, 'Sun' => 6];
            $from = $abbr[ucfirst(strtolower($m[1]))] ?? 0;
            $to   = $abbr[ucfirst(strtolower($m[2]))] ?? 4;
            $days = [];
            for ($i = $from; $i <= $to; $i++) {
                $days[] = $all[$i];
            }
            return json_encode($days, JSON_THROW_ON_ERROR);
        }

        // Default: Monday–Friday
        return json_encode(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], JSON_THROW_ON_ERROR);
    }

    /**
     * Compute net paid minutes from start to end (in HH:mm format).
     * Subtracts the standard 60-minute break.
     */
    private function computeStandardMinutes(string $timeIn, string $timeOut): int
    {
        if ($timeIn === '' || $timeOut === '') {
            return 480; // 8 hours default
        }
        [$h1, $m1] = array_map('intval', explode(':', $timeIn));
        [$h2, $m2] = array_map('intval', explode(':', $timeOut));
        $totalMinutes = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
        return max(0, $totalMinutes - 60); // subtract 1-hour break
    }

    private function makeRepo(): ScheduleRepository
    {
        return new ScheduleRepository($this->makeConnection());
    }

    private function makeConnection(): Connection
    {
        return new Connection(require APP_ROOT . '/config/database.php');
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
