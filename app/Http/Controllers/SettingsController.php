<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use RuntimeException;
use Wbpms\Application\PayrollService;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\PositionRepository;
use Wbpms\Infrastructure\Persistence\ScheduleRepository;

/**
 * SettingsController — unified HR Settings hub.
 *
 * Three sub-sections, all under /hr/settings, each with its own active tab:
 *   Positions   — job position CRUD (extracted from PositionController)
 *   Periods     — payroll period management (extracted from PayrollController)
 *   Holidays    — holiday calendar CRUD (extracted from ScheduleController)
 *
 * Routes (all require HRHead):
 *   GET  /hr/settings                           → index()         — redirect to positions tab
 *   GET  /hr/settings/positions                 → positions()     — list / add / edit positions
 *   POST /hr/settings/positions                 → storePosition() — create position
 *   POST /hr/settings/positions/{id}/toggle     → togglePosition()— activate/deactivate
 *   POST /hr/settings/positions/{id}            → updatePosition()— edit position
 *   GET  /hr/settings/periods                   → periods()       — list periods + add form
 *   POST /hr/settings/periods                   → storePeriod()   — create period
 *   GET  /hr/settings/holidays                  → holidays()      — list all holidays + add form
 *   POST /hr/settings/holidays                  → storeHoliday()  — create holiday
 *   GET  /hr/settings/holidays/{id}/edit        → editHoliday()   — edit form
 *   POST /hr/settings/holidays/{id}             → updateHoliday() — save holiday changes
 *   POST /hr/settings/holidays/{id}/delete      → deleteHoliday() — soft-deactivate
 */
final class SettingsController
{
    // =========================================================================
    // Hub redirect
    // =========================================================================

    /** GET /hr/settings → always land on positions tab */
    public function index(array $params = []): void
    {
        $this->redirect('/hr/settings/positions');
    }

    // =========================================================================
    // Job Positions
    // =========================================================================

    /** GET /hr/settings/positions */
    public function positions(array $params = []): void
    {
        $repo      = $this->posRepo();
        $positions = $repo->findAll();
        $total     = count($positions);
        $active    = count(array_filter($positions, fn($p) => $p['status'] === 'Active'));

        ViewRenderer::render('hr/settings/index', [
            'activePage' => 'settings',
            'tab'        => 'positions',
            'positions'  => $positions,
            'total'      => $total,
            'active'     => $active,
            'inactive'   => $total - $active,
            'errors'     => [],
            'editRow'    => null,
        ], 'Settings');
    }

    /** POST /hr/settings/positions */
    public function storePosition(array $params = []): void
    {
        ['title' => $title, 'department' => $dept, 'sort_order' => $sort, 'errors' => $errors]
            = $this->extractPosition();

        if ($errors !== []) {
            $this->renderPositions($errors, null);
            return;
        }

        try {
            $this->posRepo()->create($title, $dept, $sort);
            ViewRenderer::flash("Position \"{$title}\" created.");
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/settings/positions');
    }

    /** POST /hr/settings/positions/{id} */
    public function updatePosition(array $params = []): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $repo = $this->posRepo();

        if ($repo->findById($id) === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ['title' => $title, 'department' => $dept, 'sort_order' => $sort, 'errors' => $errors]
            = $this->extractPosition();

        if ($errors !== []) {
            $this->renderPositions($errors, $id);
            return;
        }

        try {
            $repo->update($id, $title, $dept, $sort);
            ViewRenderer::flash("Position \"{$title}\" updated.");
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/settings/positions');
    }

    /** POST /hr/settings/positions/{id}/toggle */
    public function togglePosition(array $params = []): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $repo = $this->posRepo();
        $row  = $repo->findById($id);

        if ($row === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $repo->toggleStatus($id);
        $newStatus = $row['status'] === 'Active' ? 'Inactive' : 'Active';
        ViewRenderer::flash("\"{$row['position_title']}\" set to {$newStatus}.");
        $this->redirect('/hr/settings/positions');
    }

    // =========================================================================
    // Payroll Periods
    // =========================================================================

    /** GET /hr/settings/periods */
    public function periods(array $params = []): void
    {
        ViewRenderer::render('hr/settings/index', [
            'activePage' => 'settings',
            'tab'        => 'periods',
            'periods'    => $this->payrollService()->listPeriods(),
            'errors'     => [],
        ], 'Settings');
    }

    /** POST /hr/settings/periods */
    public function storePeriod(array $params = []): void
    {
        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $service     = $this->payrollService();

        try {
            $service->createPeriod($periodStart);
            ViewRenderer::flash('Payroll period created.');
            $this->redirect('/hr/settings/periods');
        } catch (RuntimeException $e) {
            ViewRenderer::render('hr/settings/index', [
                'activePage'   => 'settings',
                'tab'          => 'periods',
                'periods'      => $service->listPeriods(),
                'errors'       => [$e->getMessage()],
                'period_start' => $periodStart,
            ], 'Settings');
        }
    }

    // =========================================================================
    // Holiday Calendar
    // =========================================================================

    /** GET /hr/settings/holidays */
    public function holidays(array $params = []): void
    {
        ViewRenderer::render('hr/settings/index', [
            'activePage' => 'settings',
            'tab'        => 'holidays',
            'holidays'   => $this->schedRepo()->findAllHolidays(),
            'errors'     => [],
        ], 'Settings');
    }

    /** POST /hr/settings/holidays */
    public function storeHoliday(array $params = []): void
    {
        $data   = $this->extractHoliday();
        $errors = $this->validateHoliday($data);

        if ($errors === []) {
            try {
                $this->schedRepo()->createHoliday([
                    'holiday_date' => $data['holiday_date'],
                    'description'  => $data['description'],
                    'holiday_type' => $data['holiday_type'],
                ]);
                ViewRenderer::flash('Holiday added.');
                $this->redirect('/hr/settings/holidays');
                return;
            } catch (RuntimeException $e) {
                $errors['holiday_date'] = $e->getMessage();
            }
        }

        ViewRenderer::render('hr/settings/index', [
            'activePage' => 'settings',
            'tab'        => 'holidays',
            'holidays'   => $this->schedRepo()->findAllHolidays(),
            'errors'     => $errors,
            'input'      => $data,
        ], 'Settings');
    }

    /** GET /hr/settings/holidays/{id}/edit */
    public function editHoliday(array $params = []): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $holiday = $this->schedRepo()->findHolidayById($id);

        if ($holiday === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ViewRenderer::render('hr/settings/holiday_edit', [
            'activePage' => 'settings',
            'holiday'    => $holiday,
            'errors'     => [],
        ], 'Edit Holiday — Settings');
    }

    /** POST /hr/settings/holidays/{id}  (with _method=PUT) */
    public function updateHoliday(array $params = []): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $repo    = $this->schedRepo();
        $holiday = $repo->findHolidayById($id);

        if ($holiday === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $data   = $this->extractHoliday();
        $errors = $this->validateHoliday($data);

        if ($errors === []) {
            try {
                $repo->updateHoliday($id, [
                    'holiday_date' => $data['holiday_date'],
                    'description'  => $data['description'],
                    'holiday_type' => $data['holiday_type'],
                    'status'       => $data['status'] !== '' ? $data['status'] : 'Active',
                ]);
                ViewRenderer::flash('Holiday updated.');
                $this->redirect('/hr/settings/holidays');
                return;
            } catch (RuntimeException $e) {
                $errors['holiday_date'] = $e->getMessage();
            }
        }

        ViewRenderer::render('hr/settings/holiday_edit', [
            'activePage' => 'settings',
            'holiday'    => array_merge($holiday, ['holiday_id' => $id], $data),
            'errors'     => $errors,
        ], 'Edit Holiday — Settings');
    }

    /** POST /hr/settings/holidays/{id}/delete */
    public function deleteHoliday(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);

        if ($this->schedRepo()->findHolidayById($id) === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        $this->schedRepo()->deactivateHoliday($id);
        ViewRenderer::flash('Holiday deactivated.');
        $this->redirect('/hr/settings/holidays');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * @return array{title:string,department:string|null,sort_order:int,errors:array<string,string>}
     */
    private function extractPosition(): array
    {
        $title  = trim((string) ($_POST['position_title'] ?? ''));
        $dept   = trim((string) ($_POST['department']     ?? ''));
        $sort   = (int) ($_POST['sort_order'] ?? 0);
        $errors = [];

        if ($title === '') {
            $errors['position_title'] = 'Position title is required.';
        } elseif (mb_strlen($title) > 100) {
            $errors['position_title'] = 'Position title must be 100 characters or fewer.';
        }

        return [
            'title'      => $title,
            'department' => $dept !== '' ? $dept : null,
            'sort_order' => max(0, $sort),
            'errors'     => $errors,
        ];
    }

    /** @param array<string,string> $errors */
    private function renderPositions(array $errors, ?int $editId): void
    {
        $repo      = $this->posRepo();
        $positions = $repo->findAll();
        $total     = count($positions);
        ViewRenderer::render('hr/settings/index', [
            'activePage' => 'settings',
            'tab'        => 'positions',
            'positions'  => $positions,
            'total'      => $total,
            'active'     => count(array_filter($positions, fn($p) => $p['status'] === 'Active')),
            'inactive'   => count(array_filter($positions, fn($p) => $p['status'] !== 'Active')),
            'errors'     => $errors,
            'editRow'    => $editId,
            'input'      => $_POST,
        ], 'Settings');
    }

    /** @return array<string,string> */
    private function extractHoliday(): array
    {
        return [
            'holiday_date' => trim((string) ($_POST['holiday_date'] ?? '')),
            'description'  => trim((string) ($_POST['description']  ?? '')),
            'holiday_type' => trim((string) ($_POST['holiday_type'] ?? '')),
            'status'       => trim((string) ($_POST['status']       ?? 'Active')),
        ];
    }

    /**
     * @param  array<string,string> $data
     * @return array<string,string>
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

    private function posRepo(): PositionRepository
    {
        return new PositionRepository(new Connection(require APP_ROOT . '/config/database.php'));
    }

    private function schedRepo(): ScheduleRepository
    {
        return new ScheduleRepository(new Connection(require APP_ROOT . '/config/database.php'));
    }

    private function payrollService(): PayrollService
    {
        $conn = new Connection(require APP_ROOT . '/config/database.php');
        return new PayrollService($conn);
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
