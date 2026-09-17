<?php

declare(strict_types=1);

namespace Wbpms\Infrastructure\Persistence;

use PDO;

/**
 * PositionRepository — PDO persistence for the job_position table.
 *
 * Used by PositionController (settings) and EmployeeController (dropdown).
 * ADR-0003: all SQL lives here.
 */
final class PositionRepository extends AbstractRepository
{
    /**
     * All positions ordered for the settings management table.
     *
     * @return list<array<string,mixed>>
     */
    public function findAll(): array
    {
        return $this->pdo()->query(
            "SELECT position_id, position_title, department,
                    status, sort_order, created_at, updated_at
               FROM job_position
              ORDER BY sort_order ASC, position_title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active positions only — for employee form dropdown.
     *
     * @return list<array{position_id:int,position_title:string,department:string|null}>
     */
    public function activeList(): array
    {
        return $this->pdo()->query(
            "SELECT position_id, position_title, department
               FROM job_position
              WHERE status = 'Active'
              ORDER BY sort_order ASC, position_title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a single position by ID, or null.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $positionId): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT position_id, position_title, department, status, sort_order
               FROM job_position
              WHERE position_id = :id"
        );
        $stmt->execute([':id' => $positionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Create a new position. Returns the new position_id.
     *
     * @throws \RuntimeException on duplicate title
     */
    public function create(string $title, ?string $department, int $sortOrder): int
    {
        try {
            $stmt = $this->pdo()->prepare(
                "INSERT INTO job_position
                    (position_title, department, status, sort_order, created_at, updated_at)
                 VALUES
                    (:title, :dept, 'Active', :sort, NOW(), NOW())"
            );
            $stmt->execute([
                ':title' => $title,
                ':dept'  => $department !== '' ? $department : null,
                ':sort'  => $sortOrder,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                throw new \RuntimeException("Position \"{$title}\" already exists.");
            }
            throw $e;
        }
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Update an existing position's fields.
     *
     * @throws \RuntimeException on duplicate title
     */
    public function update(int $positionId, string $title, ?string $department, int $sortOrder): void
    {
        try {
            $this->pdo()->prepare(
                "UPDATE job_position
                    SET position_title = :title,
                        department     = :dept,
                        sort_order     = :sort,
                        updated_at     = NOW()
                  WHERE position_id = :id"
            )->execute([
                ':title' => $title,
                ':dept'  => $department !== '' ? $department : null,
                ':sort'  => $sortOrder,
                ':id'    => $positionId,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
                throw new \RuntimeException("Position \"{$title}\" already exists.");
            }
            throw $e;
        }
    }

    /**
     * Toggle status between Active ↔ Inactive.
     */
    public function toggleStatus(int $positionId): void
    {
        $this->pdo()->prepare(
            "UPDATE job_position
                SET status     = IF(status = 'Active', 'Inactive', 'Active'),
                    updated_at = NOW()
              WHERE position_id = :id"
        )->execute([':id' => $positionId]);
    }
}
