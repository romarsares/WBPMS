<?php

declare(strict_types=1);

namespace Wbpms\Http\Controllers;

use RuntimeException;
use Wbpms\Http\View\ViewRenderer;
use Wbpms\Infrastructure\Database\Connection;
use Wbpms\Infrastructure\Persistence\PositionRepository;

/**
 * PositionController — HR Settings: Job Position management.
 *
 * Routes (all require HRHead role):
 *   GET  /hr/settings/positions          → index()        — list all positions
 *   POST /hr/settings/positions          → store()        — create new position
 *   POST /hr/settings/positions/{id}     → update()       — edit existing position
 *   POST /hr/settings/positions/{id}/toggle → toggle()   — activate / deactivate
 */
final class PositionController
{
    // -----------------------------------------------------------------------
    // GET /hr/settings/positions
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function index(array $params = []): void
    {
        $repo      = $this->repo();
        $positions = $repo->findAll();

        $total    = count($positions);
        $active   = count(array_filter($positions, fn($p) => $p['status'] === 'Active'));
        $inactive = $total - $active;

        ViewRenderer::render('hr/settings/positions', [
            'positions' => $positions,
            'total'     => $total,
            'active'    => $active,
            'inactive'  => $inactive,
            'errors'    => [],
            'editRow'   => null,
        ], 'Job Positions');
    }

    // -----------------------------------------------------------------------
    // POST /hr/settings/positions
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function store(array $params = []): void
    {
        ['title' => $title, 'department' => $dept, 'sort_order' => $sort, 'errors' => $errors]
            = $this->extractAndValidate();

        if ($errors !== []) {
            $this->renderWithErrors($errors, null);
            return;
        }

        try {
            $this->repo()->create($title, $dept, $sort);
            ViewRenderer::flash("Position \"{$title}\" created.");
        } catch (RuntimeException $e) {
            ViewRenderer::flashError($e->getMessage());
        }

        $this->redirect('/hr/settings/positions');
    }

    // -----------------------------------------------------------------------
    // POST /hr/settings/positions/{id}
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function update(array $params = []): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $repo = $this->repo();

        if ($repo->findById($id) === null) {
            http_response_code(404);
            ViewRenderer::render('errors/404', [], '404 Not Found');
            return;
        }

        ['title' => $title, 'department' => $dept, 'sort_order' => $sort, 'errors' => $errors]
            = $this->extractAndValidate();

        if ($errors !== []) {
            $this->renderWithErrors($errors, $id);
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

    // -----------------------------------------------------------------------
    // POST /hr/settings/positions/{id}/toggle
    // -----------------------------------------------------------------------

    /** @param array<string,string> $params */
    public function toggle(array $params = []): void
    {
        $id   = (int) ($params['id'] ?? 0);
        $repo = $this->repo();
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

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * @return array{title:string,department:string|null,sort_order:int,errors:array<string,string>}
     */
    private function extractAndValidate(): array
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
    private function renderWithErrors(array $errors, ?int $editId): void
    {
        $repo      = $this->repo();
        $positions = $repo->findAll();
        ViewRenderer::render('hr/settings/positions', [
            'positions' => $positions,
            'total'     => count($positions),
            'active'    => count(array_filter($positions, fn($p) => $p['status'] === 'Active')),
            'inactive'  => count(array_filter($positions, fn($p) => $p['status'] !== 'Active')),
            'errors'    => $errors,
            'editRow'   => $editId,
            'input'     => $_POST,
        ], 'Job Positions');
    }

    private function repo(): PositionRepository
    {
        return new PositionRepository(
            new Connection(require APP_ROOT . '/config/database.php')
        );
    }

    private function redirect(string $path): void
    {
        $base = rtrim((string) ($_ENV['APP_BASE_URL'] ?? ''), '/');
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
