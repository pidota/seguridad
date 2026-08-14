<?php

declare(strict_types=1);

namespace App\Repositories\Senda;

use Core\Database;

final class AssistedReferralRepository
{
    private function db(): \PDO
    {
        return Database::connection();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = $this->selectSql() . ' WHERE r.id = :id';

        if (!$withDeleted) {
            $sql .= ' AND r.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByAttentionId(int $attentionId, bool $withDeleted = false): ?array
    {
        $sql = $this->selectSql() . ' WHERE r.senda_attention_id = :attention_id';

        if (!$withDeleted) {
            $sql .= ' AND r.deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql . ' ORDER BY r.id DESC LIMIT 1');
        $stmt->execute(['attention_id' => $attentionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function all(): array
    {
        $sql = $this->selectSql() . '
                WHERE r.deleted_at IS NULL
                ORDER BY r.request_date DESC, r.id DESC';
        $stmt = $this->db()->query($sql);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<int> $attentionIds
     * @return list<array<string, mixed>>
     */
    public function forAttentionIds(array $attentionIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $attentionIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $sql = $this->selectSql() . ' WHERE r.deleted_at IS NULL
                AND r.senda_attention_id IN (' . implode(', ', $placeholders) . ')
                ORDER BY r.id DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO senda_assisted_referrals (
                    senda_attention_id, senda_person_id, request_date, demand_origin,
                    receiving_officer, demand_area, request_type,
                    requesting_device, requesting_commune, destination_center, destination_commune,
                    applicant_kind, applicant_name, applicant_role, applicant_institution, applicant_phone,
                    applicant_email, applicant_relationship, gender, health_insurance,
                    nationality, indigenous_people, enrolled_health_center, cesfam_name,
                    emergency_contact_name, emergency_contact_phone,
                    substances, age_of_onset, consumption_frequency, consumption_route,
                    mental_health_history, physical_health_history, family_situation,
                    legal_situation, support_network, has_previous_treatments,
                    previous_treatments_count, previous_treatment_modality, previous_treatment_stay,
                    previous_treatment_completed, previous_treatment_center, previous_treatment_commune,
                    previous_treatments_detail,
                    suicide_risk, violence_risk, street_situation, pregnancy, children_in_care,
                    overall_risk, risk_notes, screening_used, assist_applicable, assist_data, observations,
                    is_completed, status, created_by
                ) VALUES (
                    :senda_attention_id, :senda_person_id, :request_date, :demand_origin,
                    :receiving_officer, :demand_area, :request_type,
                    :requesting_device, :requesting_commune, :destination_center, :destination_commune,
                    :applicant_kind, :applicant_name, :applicant_role, :applicant_institution, :applicant_phone,
                    :applicant_email, :applicant_relationship, :gender, :health_insurance,
                    :nationality, :indigenous_people, :enrolled_health_center, :cesfam_name,
                    :emergency_contact_name, :emergency_contact_phone,
                    :substances, :age_of_onset, :consumption_frequency, :consumption_route,
                    :mental_health_history, :physical_health_history, :family_situation,
                    :legal_situation, :support_network, :has_previous_treatments,
                    :previous_treatments_count, :previous_treatment_modality, :previous_treatment_stay,
                    :previous_treatment_completed, :previous_treatment_center, :previous_treatment_commune,
                    :previous_treatments_detail,
                    :suicide_risk, :violence_risk, :street_situation, :pregnancy, :children_in_care,
                    :overall_risk, :risk_notes, :screening_used, :assist_applicable, :assist_data, :observations,
                    :is_completed, :status, :created_by
                )';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE senda_assisted_referrals
                SET request_date = :request_date,
                    demand_origin = :demand_origin,
                    receiving_officer = :receiving_officer,
                    demand_area = :demand_area,
                    request_type = :request_type,
                    requesting_device = :requesting_device,
                    requesting_commune = :requesting_commune,
                    destination_center = :destination_center,
                    destination_commune = :destination_commune,
                    applicant_kind = :applicant_kind,
                    applicant_name = :applicant_name,
                    applicant_role = :applicant_role,
                    applicant_institution = :applicant_institution,
                    applicant_phone = :applicant_phone,
                    applicant_email = :applicant_email,
                    applicant_relationship = :applicant_relationship,
                    gender = :gender,
                    health_insurance = :health_insurance,
                    nationality = :nationality,
                    indigenous_people = :indigenous_people,
                    enrolled_health_center = :enrolled_health_center,
                    cesfam_name = :cesfam_name,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_phone = :emergency_contact_phone,
                    substances = :substances,
                    age_of_onset = :age_of_onset,
                    consumption_frequency = :consumption_frequency,
                    consumption_route = :consumption_route,
                    mental_health_history = :mental_health_history,
                    physical_health_history = :physical_health_history,
                    family_situation = :family_situation,
                    legal_situation = :legal_situation,
                    support_network = :support_network,
                    has_previous_treatments = :has_previous_treatments,
                    previous_treatments_count = :previous_treatments_count,
                    previous_treatment_modality = :previous_treatment_modality,
                    previous_treatment_stay = :previous_treatment_stay,
                    previous_treatment_completed = :previous_treatment_completed,
                    previous_treatment_center = :previous_treatment_center,
                    previous_treatment_commune = :previous_treatment_commune,
                    previous_treatments_detail = :previous_treatments_detail,
                    suicide_risk = :suicide_risk,
                    violence_risk = :violence_risk,
                    street_situation = :street_situation,
                    pregnancy = :pregnancy,
                    children_in_care = :children_in_care,
                    overall_risk = :overall_risk,
                    risk_notes = :risk_notes,
                    screening_used = :screening_used,
                    assist_applicable = :assist_applicable,
                    assist_data = :assist_data,
                    observations = :observations,
                    is_completed = :is_completed,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_merge($data, ['id' => $id]));
    }

    private function selectSql(): string
    {
        return 'SELECT r.*,
                       a.attention_number, a.attention_date, a.entry_type,
                       p.first_names, p.paternal_surname, p.maternal_surname, p.rut, p.birth_date,
                       u.name AS created_by_name
                FROM senda_assisted_referrals r
                INNER JOIN senda_attentions a ON a.id = r.senda_attention_id
                INNER JOIN senda_people p ON p.id = r.senda_person_id
                LEFT JOIN users u ON u.id = r.created_by';
    }
}
