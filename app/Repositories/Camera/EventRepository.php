<?php

declare(strict_types=1);

namespace App\Repositories\Camera;

use App\Services\Cctv\CatalogService;
use Core\Database;

final class EventRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = $this->selectSql() . ' WHERE e.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND e.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filterSql($filters);
        $from = $this->fromSql() . ' WHERE e.deleted_at IS NULL' . $where;

        $count = $this->db()->prepare('SELECT COUNT(*) ' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectSql() . ' WHERE e.deleted_at IS NULL' . $where . '
                ORDER BY e.event_date DESC, e.event_time DESC, e.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    public function countToday(?string $date = null): int
    {
        $date = $date ?? date('Y-m-d');
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM camera_events WHERE deleted_at IS NULL AND event_date = :date'
        );
        $stmt->execute(['date' => $date]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function operatorOptions(): array
    {
        $sql = 'SELECT DISTINCT u.id, u.name
                FROM users u
                INNER JOIN camera_events e ON e.created_by = u.id AND e.deleted_at IS NULL
                ORDER BY u.name ASC';
        $stmt = $this->db()->query($sql);

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO camera_events (
                    event_date, event_time, shift, classification, classification_other,
                    location, description, actions_taken, created_by, updated_by
                ) VALUES (
                    :event_date, :event_time, :shift, :classification, :classification_other,
                    :location, :description, :actions_taken, :created_by, :updated_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE camera_events
                SET event_date = :event_date,
                    event_time = :event_time,
                    shift = :shift,
                    classification = :classification,
                    classification_other = :classification_other,
                    location = :location,
                    description = :description,
                    actions_taken = :actions_taken,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    private function selectSql(): string
    {
        return 'SELECT e.*,
                       creator.name AS created_by_name,
                       updater.name AS updated_by_name
                ' . $this->fromSql();
    }

    private function fromSql(): string
    {
        return 'FROM camera_events e
                LEFT JOIN users creator ON creator.id = e.created_by
                LEFT JOIN users updater ON updater.id = e.updated_by';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterSql(array $filters): array
    {
        $where = '';
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (
                e.location LIKE :q
                OR e.description LIKE :q2
                OR e.actions_taken LIKE :q3
            )';
            $like = '%' . $q . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $shift = trim((string) ($filters['shift'] ?? ''));
        if (\App\Services\Camera\EventCatalog::isValidShift($shift)) {
            $where .= ' AND e.shift = :shift';
            $params['shift'] = $shift;
        }

        $classification = trim((string) ($filters['classification'] ?? ''));
        if ((new CatalogService())->isValidIncidentTypeSlug($classification)) {
            $where .= ' AND e.classification = :classification';
            $params['classification'] = $classification;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where .= ' AND e.event_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where .= ' AND e.event_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $createdBy = $filters['created_by'] ?? null;
        if ($createdBy !== null && $createdBy !== '') {
            $where .= ' AND e.created_by = :created_by';
            $params['created_by'] = (int) $createdBy;
        }

        return [$where, $params];
    }
}
