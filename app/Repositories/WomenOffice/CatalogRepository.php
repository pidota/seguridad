<?php

declare(strict_types=1);

namespace App\Repositories\WomenOffice;

use Core\Database;

final class CatalogRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function statusId(string $slug): int
    {
        $stmt = $this->db()->prepare(
            'SELECT id FROM women_case_statuses WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $id = (int) $stmt->fetchColumn();

        if ($id < 1) {
            throw new \RuntimeException('Estado de caso no configurado: ' . $slug);
        }

        return $id;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function reportChannels(): array
    {
        return $this->options('women_report_channels', true);
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function violenceTypes(): array
    {
        return $this->options('women_violence_types', true);
    }

    public function violenceTypeSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_violence_types WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function relationshipTypes(): array
    {
        return $this->options('women_relationship_types', true);
    }

    public function relationshipTypeSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_relationship_types WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function formalReportInstitutions(): array
    {
        return $this->options('women_formal_report_institutions', true);
    }

    public function formalReportInstitutionSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_formal_report_institutions WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function riskFactors(): array
    {
        return $this->options('women_risk_factors', true);
    }

    public function riskFactorSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_risk_factors WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function needs(): array
    {
        return $this->options('women_needs', true);
    }

    public function needSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_needs WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function protectiveMeasureTypes(): array
    {
        return $this->options('women_protective_measure_types');
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function minorAgeRanges(): array
    {
        return $this->options('women_minor_age_ranges');
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function actionTypes(): array
    {
        return $this->options('women_action_types', true);
    }

    public function actionTypeSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_action_types WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function referralInstitutions(): array
    {
        return $this->options('women_referral_institutions');
    }

    public function referralInstitutionSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_referral_institutions WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function referralStatuses(): array
    {
        return $this->options('women_referral_statuses');
    }

    public function referralStatusId(string $slug): int
    {
        $stmt = $this->db()->prepare(
            'SELECT id FROM women_referral_statuses WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $id = (int) $stmt->fetchColumn();

        if ($id < 1) {
            throw new \RuntimeException('Estado de derivación no configurado: ' . $slug);
        }

        return $id;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function followUpContactTypes(): array
    {
        return $this->options('women_followup_contact_types', true);
    }

    public function followUpContactTypeSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_followup_contact_types WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    public function followUpResults(): array
    {
        return $this->options('women_followup_results', true);
    }

    public function followUpResultSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_followup_results WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function educationLevels(): array
    {
        return $this->options('women_education_levels');
    }

    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function caseStatuses(): array
    {
        return $this->options('women_case_statuses');
    }

    public function reportChannelSlug(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT slug FROM women_report_channels WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * @return list<array{id: int, slug: string, name: string, allows_other?: int}>
     */
    private function options(string $table, bool $withAllowsOther = false): array
    {
        $columns = 'id, slug, name';
        if ($withAllowsOther) {
            $columns .= ', allows_other';
        }

        $stmt = $this->db()->query(
            'SELECT ' . $columns . '
             FROM ' . $table . '
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }
}
