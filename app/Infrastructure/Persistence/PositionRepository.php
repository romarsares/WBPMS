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
            "SELECT position_id, position_title, department, default_daily_rate,
                    contractual_allowed, status, sort_order, created_at, updated_at
               FROM job_position
              ORDER BY sort_order ASC, position_title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active positions only — for employee form dropdown.
     *
     * @return list<array{position_id:int,position_title:string,department:string|null,default_daily_rate:string|null,contractual_allowed:string}>
     */
    public function activeList(): array
    {
        return $this->pdo()->query(
            "SELECT position_id, position_title, department, default_daily_rate, contractual_allowed
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
            "SELECT position_id, position_title, department, default_daily_rate,
                    contractual_allowed, status, sort_order
               FROM job_position
              WHERE position_id = :id"
        );
        $stmt->execute([':id' => $positionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Find an active position by its (unique) title, including its default
     * daily rate and contractual_allowed flag. Used by EmployeeController to
     * derive the new employee salary from Position / Job title configuration
     * and enforce the Contractual restriction. Returns null when no active
     * position matches the title (including a custom "Other" position).
     *
     * @return array{position_id:int,position_title:string,default_daily_rate:string|null,contractual_allowed:string}|null
     */
    public function findActiveByTitle(string $title): ?array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT position_id, position_title, default_daily_rate, contractual_allowed
               FROM job_position
              WHERE position_title = :title
                AND status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([':title' => $title]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Create a new position. Returns the new position_id.
     *
     * @param float|null $defaultDailyRate Daily rate used for new employees
     *                                     assigned to this position; null
     *                                     leaves the position unavailable for
     *                                     new employee creation.
     * @param bool       $contractualAllowed Whether Contractual employees may
     *                                       hold this position (default true).
     *
     * @throws \RuntimeException on duplicate title
     */
    public function create(string $title, ?string $department, int $sortOrder, ?float $defaultDailyRate = null, bool $contractualAllowed = true): int
    {
        $rate = ($defaultDailyRate !== null && $defaultDailyRate > 0) ? $defaultDailyRate : null;

        try {
            $stmt = $this->pdo()->prepare(
                "INSERT INTO job_position
                    (position_title, department, default_daily_rate, contractual_allowed, status, sort_order, created_at, updated_at)
                 VALUES
                    (:title, :dept, :rate, :contractual_allowed, 'Active', :sort, NOW(), NOW())"
            );
            $stmt->execute([
                ':title'               => $title,
                ':dept'                => $department !== '' ? $department : null,
                ':rate'                => $rate,
                ':contractual_allowed' => $contractualAllowed ? 1 : 0,
                ':sort'                => $sortOrder,
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
     * @param float|null $defaultDailyRate Advisory daily rate shown on the Add
     *                                     Employee form when this position is
     *                                     selected; null clears it.
     * @param bool       $contractualAllowed Whether Contractual employees may
     *                                       hold this position.
     *
     * @throws \RuntimeException on duplicate title
     */
    public function update(int $positionId, string $title, ?string $department, int $sortOrder, ?float $defaultDailyRate = null, bool $contractualAllowed = true): void
    {
        $rate = ($defaultDailyRate !== null && $defaultDailyRate > 0) ? $defaultDailyRate : null;

        try {
            $this->pdo()->prepare(
                "UPDATE job_position
                    SET position_title       = :title,
                        department           = :dept,
                        default_daily_rate   = :rate,
                        contractual_allowed  = :contractual_allowed,
                        sort_order           = :sort,
                        updated_at           = NOW()
                  WHERE position_id = :id"
            )->execute([
                ':title'               => $title,
                ':dept'                => $department !== '' ? $department : null,
                ':rate'                => $rate,
                ':contractual_allowed' => $contractualAllowed ? 1 : 0,
                ':sort'                => $sortOrder,
                ':id'                  => $positionId,
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
