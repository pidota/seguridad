<?php

declare(strict_types=1);

namespace App\Services\Senda;

use App\Repositories\Senda\AssistedReferralRepository;
use App\Repositories\Senda\AssistResultRepository;
use App\Repositories\Senda\AttentionRepository;
use App\Repositories\Senda\PersonRepository;
use App\Services\AuditService;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;

final class ReferralService
{
    public function __construct(
        private readonly AssistedReferralRepository $referrals = new AssistedReferralRepository(),
        private readonly AssistResultRepository $assistResults = new AssistResultRepository(),
        private readonly AssistClassificationService $assistClassification = new AssistClassificationService(),
        private readonly AttentionRepository $attentions = new AttentionRepository(),
        private readonly PersonRepository $people = new PersonRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function all(): array
    {
        return array_map([$this, 'present'], $this->referrals->all());
    }

    public function find(int $id): array
    {
        $record = $this->referrals->findById($id);

        if ($record === null) {
            throw new HttpException(404, 'La ficha de referencia no existe.');
        }

        return $this->present($record);
    }

    public function findByAttention(int $attentionId): ?array
    {
        $record = $this->referrals->findByAttentionId($attentionId);

        return $record === null ? null : $this->present($record);
    }

    public function create(array $data): int
    {
        $payload = $this->payload($data);
        $assistResults = $this->assistResultsPayload($data, (int) $payload['screening_used']);
        $existing = $this->referrals->findByAttentionId((int) $payload['senda_attention_id']);

        if ($existing !== null) {
            throw new HttpException(422, 'Esta atención ya tiene una ficha de referencia. Utilice el registro existente.');
        }

        try {
            return Database::transaction(function () use ($payload, $assistResults): int {
                $id = $this->referrals->create($payload);
                $this->assistResults->replaceForReferral($id, $assistResults);
                $created = $this->referrals->findById($id);
                $presented = $created ? $this->present($created) : $payload;
                $this->auditReferralWrite($id, null, $presented);

                return $id;
            });
        } catch (\PDOException $e) {
            if ((string) ($e->errorInfo[0] ?? '') === '23000') {
                throw new HttpException(422, 'Esta atención ya tiene una ficha de referencia. Utilice el registro existente.');
            }

            throw $e;
        }
    }

    public function update(int $id, array $data): void
    {
        $current = $this->find($id);

        if (ReferralStatus::isCompleted($current) && !hasPermission('senda.referrals.edit_completed')) {
            throw new HttpException(403, 'No puede modificar una ficha ya finalizada.');
        }

        $data['senda_attention_id'] = $current['senda_attention_id'];
        $data['senda_person_id'] = $current['senda_person_id'];
        $payload = $this->payload($data, $current);
        $assistResults = $this->assistResultsPayload($data, (int) $payload['screening_used']);
        unset($payload['senda_attention_id'], $payload['senda_person_id'], $payload['created_by']);

        Database::transaction(function () use ($id, $payload, $assistResults): void {
            $this->referrals->update($id, $payload);
            $this->assistResults->replaceForReferral($id, $assistResults);
        });
        $updated = $this->referrals->findById($id);
        $presented = $updated ? $this->present($updated) : $payload;
        $this->auditReferralWrite($id, $current, $presented);
    }

    /**
     * @param array<string, mixed> $attention
     * @param array<string, mixed> $person
     * @return array<string, mixed>
     */
    public function defaults(array $attention, array $person): array
    {
        $user = Auth::user();
        $isReferral = (string) ($attention['entry_type'] ?? '') === EntryType::DERIVACION;
        $officer = trim((string) ($attention['created_by_name'] ?? ''));

        if ($officer === '') {
            $officer = trim((string) ($user['name'] ?? ''));
        }

        return [
            'senda_attention_id' => (int) $attention['id'],
            'senda_person_id' => (int) $person['id'],
            'request_date' => trim((string) ($attention['attention_date'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            'demand_origin' => DemandOrigin::fromAttention($attention),
            'receiving_officer' => $officer,
            'demand_area' => '',
            'request_type' => '',
            'requesting_device' => $isReferral ? trim((string) ($attention['referral_institution_name'] ?? '')) : '',
            'requesting_commune' => '',
            'destination_center' => '',
            'destination_commune' => '',
            'applicant_kind' => '',
            'applicant_name' => '',
            'applicant_role' => '',
            'applicant_institution' => '',
            'applicant_phone' => '',
            'applicant_email' => '',
            'applicant_relationship' => '',
            'gender' => '',
            'health_insurance' => '',
            'nationality' => '',
            'indigenous_people' => '',
            'enrolled_health_center' => '',
            'cesfam_name' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'substances' => '',
            'age_of_onset' => '',
            'consumption_frequency' => '',
            'consumption_route' => '',
            'mental_health_history' => '',
            'physical_health_history' => '',
            'family_situation' => '',
            'legal_situation' => '',
            'support_network' => '',
            'has_previous_treatments' => '',
            'previous_treatments_count' => '',
            'previous_treatment_modality' => '',
            'previous_treatment_stay' => '',
            'previous_treatment_completed' => '',
            'previous_treatment_center' => '',
            'previous_treatment_commune' => '',
            'previous_treatments_detail' => '',
            'suicide_risk' => '',
            'violence_risk' => '',
            'street_situation' => '',
            'pregnancy' => '',
            'children_in_care' => '',
            'overall_risk' => '',
            'risk_notes' => '',
            'screening_used' => '',
            'assist_applicable' => 0,
            'assist' => AssistedReferralCatalog::emptyAssist(),
            'observations' => '',
            'status' => ReferralStatus::DRAFT,
            'is_completed' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $row['person_full_name'] = PersonService::fullName($row);
        $row['person_age'] = PersonService::age(isset($row['birth_date']) ? (string) $row['birth_date'] : null);
        $row['assist'] = $this->presentAssist((int) ($row['id'] ?? 0), $row['assist_data'] ?? null);
        $row['status'] = ReferralStatus::fromRow($row);
        $row['status_label'] = ReferralStatus::label($row['status']);
        $row['is_completed'] = ReferralStatus::isCompleted($row['status']);
        $row['is_draft'] = ReferralStatus::isDraft($row['status']);
        $row['has_previous_treatments'] = (int) ($row['has_previous_treatments'] ?? 0) === 1 ? 'si' : 'no';
        $screeningUsed = $row['screening_used'] ?? null;
        $row['screening_used'] = ($screeningUsed === null || $screeningUsed === '')
            ? ''
            : ((int) $screeningUsed === 1 ? 'si' : 'no');
        $row['assist_applicable'] = (int) ($row['assist_applicable'] ?? 0);
        $row['request_type_label'] = AssistedReferralCatalog::optionLabel(
            AssistedReferralCatalog::requestTypes(),
            $row['request_type'] ?? null
        );
        $row['demand_origin_label'] = DemandOrigin::label((string) ($row['demand_origin'] ?? ''));
        $row['overall_risk_label'] = AssistedReferralCatalog::optionLabel(
            AssistedReferralCatalog::riskLevels(),
            $row['overall_risk'] ?? null
        );

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $current
     * @return array<string, mixed>
     */
    private function payload(array $data, ?array $current = null): array
    {
        $attentionId = (int) ($data['senda_attention_id'] ?? 0);
        $attention = $this->attentions->findById($attentionId);

        if ($attention === null) {
            throw new HttpException(422, 'La ficha debe pertenecer a una atención existente.');
        }

        $personId = (int) ($attention['senda_person_id'] ?? 0);

        if ($personId < 1 || $this->people->findById($personId) === null) {
            throw new HttpException(422, 'La atención no tiene una persona asociada.');
        }

        $origin = DemandOrigin::resolve($attention, $data['demand_origin'] ?? null);

        if ($origin === '') {
            throw new HttpException(422, 'Debe indicar el origen de la demanda.');
        }

        $enrolledHealthCenter = $this->nullable($data['enrolled_health_center'] ?? null);
        $cesfamName = null;

        if ($enrolledHealthCenter === 'si') {
            $cesfamName = $this->nullable($data['cesfam_name'] ?? null);

            if ($cesfamName === null) {
                throw new HttpException(422, 'Indique el nombre del CESFAM.');
            }
        }

        $screening = $this->screeningPayload($data);
        $status = ReferralStatus::resolve($data, $current);

        return [
            'senda_attention_id' => $attentionId,
            'senda_person_id' => $personId,
            'request_date' => trim((string) ($data['request_date'] ?? '')),
            'demand_origin' => $origin,
            'receiving_officer' => trim((string) ($data['receiving_officer'] ?? '')),
            'demand_area' => $this->nullable($data['demand_area'] ?? null),
            'request_type' => $this->nullable($data['request_type'] ?? null),
            'requesting_device' => $this->nullable($data['requesting_device'] ?? null),
            'requesting_commune' => $this->nullable($data['requesting_commune'] ?? null),
            'destination_center' => $this->nullable($data['destination_center'] ?? null),
            'destination_commune' => $this->nullable($data['destination_commune'] ?? null),
            ...$this->applicantPayload($data, $attention, $personId),
            'gender' => $this->nullable($data['gender'] ?? null),
            'health_insurance' => $this->nullable($data['health_insurance'] ?? null),
            'nationality' => $this->nullable($data['nationality'] ?? null),
            'indigenous_people' => $this->nullable($data['indigenous_people'] ?? null),
            'enrolled_health_center' => $enrolledHealthCenter,
            'cesfam_name' => $cesfamName,
            'emergency_contact_name' => $this->nullable($data['emergency_contact_name'] ?? null),
            'emergency_contact_phone' => $this->nullable($data['emergency_contact_phone'] ?? null),
            'substances' => $this->nullable($data['substances'] ?? null),
            'age_of_onset' => $this->nullable($data['age_of_onset'] ?? null),
            'consumption_frequency' => $this->nullable($data['consumption_frequency'] ?? null),
            'consumption_route' => $this->nullable($data['consumption_route'] ?? null),
            'mental_health_history' => $this->nullable($data['mental_health_history'] ?? null),
            'physical_health_history' => $this->nullable($data['physical_health_history'] ?? null),
            'family_situation' => $this->nullable($data['family_situation'] ?? null),
            'legal_situation' => $this->nullable($data['legal_situation'] ?? null),
            'support_network' => $this->nullable($data['support_network'] ?? null),
            ...$this->previousTreatmentsPayload($data),
            'suicide_risk' => $this->nullable($data['suicide_risk'] ?? null),
            'violence_risk' => $this->nullable($data['violence_risk'] ?? null),
            'street_situation' => $this->nullableBool($data['street_situation'] ?? null),
            'pregnancy' => $this->nullableBool($data['pregnancy'] ?? null),
            'children_in_care' => $this->nullableBool($data['children_in_care'] ?? null),
            'overall_risk' => $this->nullable($data['overall_risk'] ?? null),
            'risk_notes' => $this->nullable($data['risk_notes'] ?? null),
            ...$screening,
            'observations' => $this->nullable($data['observations'] ?? null),
            'status' => $status,
            'is_completed' => ReferralStatus::isCompleted($status) ? 1 : 0,
            'created_by' => Auth::id(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $attention
     * @return array<string, mixed>
     */
    private function applicantPayload(array $data, array $attention, int $personId): array
    {
        $kind = trim((string) ($data['applicant_kind'] ?? ''));
        $allowed = array_column(AssistedReferralCatalog::applicantKinds(), 'value');

        if (!in_array($kind, $allowed, true)) {
            throw new HttpException(422, 'Indique quién realiza la solicitud.');
        }

        if (AssistedReferralCatalog::isAttendedPersonApplicant($kind)) {
            $person = $this->people->findById($personId);

            if ($person === null) {
                throw new HttpException(422, 'La atención no tiene una persona asociada.');
            }

            $name = PersonService::fullName($person);

            if ($name === '') {
                throw new HttpException(422, 'La persona atendida no tiene nombre para reutilizar en la solicitud.');
            }

            return [
                'applicant_kind' => $kind,
                'applicant_name' => $name,
                'applicant_role' => null,
                'applicant_institution' => null,
                'applicant_phone' => $this->nullable($person['phone'] ?? null),
                'applicant_email' => $this->nullable($person['email'] ?? null),
                'applicant_relationship' => null,
            ];
        }

        $relationship = trim((string) ($data['applicant_relationship'] ?? ''));

        if ($relationship === '' && in_array($kind, ['familiar', 'institucional'], true)) {
            $relationship = $kind;
        }

        $name = trim((string) ($data['applicant_name'] ?? ''));
        $phone = $this->nullable($data['applicant_phone'] ?? null);
        $email = $this->nullable($data['applicant_email'] ?? null);

        if ($kind === 'institucional') {
            if ($name === '') {
                $name = trim((string) ($attention['referral_person'] ?? ''));
            }
            if ($phone === null) {
                $phone = $this->nullable($attention['referral_phone'] ?? null);
            }
            if ($email === null) {
                $email = $this->nullable($attention['referral_email'] ?? null);
            }
        }

        return [
            'applicant_kind' => $kind,
            'applicant_name' => $name,
            'applicant_role' => $this->nullable($data['applicant_role'] ?? null),
            'applicant_institution' => $kind === 'institucional'
                ? $this->nullable($data['applicant_institution'] ?? $attention['referral_institution_name'] ?? null)
                : null,
            'applicant_phone' => $phone,
            'applicant_email' => $email,
            'applicant_relationship' => $this->nullable($relationship),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function previousTreatmentsPayload(array $data): array
    {
        $hasPrevious = $this->boolInt($data['has_previous_treatments'] ?? 0);

        if ($hasPrevious !== 1) {
            return [
                'has_previous_treatments' => 0,
                'previous_treatments_count' => null,
                'previous_treatment_modality' => null,
                'previous_treatment_stay' => null,
                'previous_treatment_completed' => null,
                'previous_treatment_center' => null,
                'previous_treatment_commune' => null,
                'previous_treatments_detail' => null,
            ];
        }

        $count = trim((string) ($data['previous_treatments_count'] ?? ''));
        $countValue = ctype_digit($count) ? (int) $count : null;

        if ($countValue === null || $countValue < 1) {
            throw new HttpException(422, 'Indique la cantidad de tratamientos previos.');
        }

        $modality = $this->nullable($data['previous_treatment_modality'] ?? null);
        $stay = $this->nullable($data['previous_treatment_stay'] ?? null);
        $completed = $this->nullable($data['previous_treatment_completed'] ?? null);
        $center = $this->nullable($data['previous_treatment_center'] ?? null);
        $commune = $this->nullable($data['previous_treatment_commune'] ?? null);

        $modalities = array_column(AssistedReferralCatalog::treatmentModalities(), 'value');
        $stays = array_column(AssistedReferralCatalog::treatmentStayPeriods(), 'value');

        if ($modality === null || !in_array($modality, $modalities, true)) {
            throw new HttpException(422, 'Indique la modalidad del tratamiento previo.');
        }

        if ($stay === null || !in_array($stay, $stays, true)) {
            throw new HttpException(422, 'Indique el tiempo de permanencia del tratamiento previo.');
        }

        if (!in_array((string) $completed, ['si', 'no'], true)) {
            throw new HttpException(422, 'Indique si el tratamiento previo terminó.');
        }

        if ($center === null) {
            throw new HttpException(422, 'Indique el nombre del centro de tratamiento previo.');
        }

        if ($commune === null) {
            throw new HttpException(422, 'Indique la comuna del tratamiento previo.');
        }

        return [
            'has_previous_treatments' => 1,
            'previous_treatments_count' => $countValue,
            'previous_treatment_modality' => $modality,
            'previous_treatment_stay' => $stay,
            'previous_treatment_completed' => $completed,
            'previous_treatment_center' => $center,
            'previous_treatment_commune' => $commune,
            'previous_treatments_detail' => $this->nullable($data['previous_treatments_detail'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{screening_used: int, assist_applicable: int, assist_data: ?string}
     */
    private function screeningPayload(array $data): array
    {
        $screening = trim((string) ($data['screening_used'] ?? ''));

        if (!in_array($screening, ['si', 'no'], true)) {
            throw new HttpException(422, 'Indique si se usó instrumento de tamizaje.');
        }

        $used = $screening === 'si' ? 1 : 0;

        return [
            'screening_used' => $used,
            'assist_applicable' => $used,
            'assist_data' => null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{substance: string, score: ?int, risk_level: ?string}>
     */
    private function assistResultsPayload(array $data, int $screeningUsed): array
    {
        if ($screeningUsed !== 1) {
            return [];
        }

        $input = $data['assist'] ?? [];
        if (!is_array($input)) {
            $input = [];
        }

        $rows = [];

        foreach (AssistedReferralCatalog::assistSubstances() as $substance) {
            $key = $substance['key'];
            $row = is_array($input[$key] ?? null) ? $input[$key] : [];
            $scoreRaw = trim((string) ($row['score'] ?? ''));
            $score = null;

            if ($scoreRaw !== '') {
                if (!ctype_digit($scoreRaw) || (int) $scoreRaw > 39) {
                    throw new HttpException(422, 'El puntaje ASSIST de ' . $substance['label'] . ' no es válido.');
                }

                $score = (int) $scoreRaw;
            }

            $rows[] = [
                'substance' => $key,
                'score' => $score,
                'risk_level' => $this->assistClassification->classify($key, $score),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{score: string, risk_level: string}>
     */
    private function presentAssist(int $referralId, mixed $legacy): array
    {
        if ($referralId > 0) {
            $saved = $this->assistResults->forReferral($referralId);

            if ($saved !== []) {
                return $this->assistFromRows($saved);
            }
        }

        return $this->decodeAssist($legacy);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array{score: string, risk_level: string}>
     */
    private function assistFromRows(array $rows): array
    {
        $base = AssistedReferralCatalog::emptyAssist();

        foreach ($rows as $row) {
            $key = (string) ($row['substance'] ?? '');
            if (!isset($base[$key])) {
                continue;
            }

            $score = $row['score'] ?? '';
            $scoreValue = $score === null || $score === '' ? null : (int) $score;
            $stored = trim((string) ($row['risk_level'] ?? ''));
            $base[$key] = [
                'score' => $scoreValue === null ? '' : (string) $scoreValue,
                'risk_level' => $stored !== ''
                    ? $stored
                    : (string) ($this->assistClassification->classify($key, $scoreValue) ?? ''),
            ];
        }

        return $base;
    }

    /**
     * @return array<string, array{score: string, risk_level: string}>
     */
    private function decodeAssist(mixed $value): array
    {
        $base = AssistedReferralCatalog::emptyAssist();
        $aliases = [
            'tobacco' => 'tabaco',
            'cannabis' => 'marihuana',
            'cocaine' => 'cocaina',
            'amphetamines' => 'anfetaminas',
            'inhalants' => 'inhalantes',
            'sedatives' => 'sedantes',
            'hallucinogens' => 'alucinogenos',
            'opioids' => 'opiaceos',
            'other' => 'otras_drogas',
        ];

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return $base;
        }

        foreach ($value as $key => $row) {
            $mapped = $aliases[(string) $key] ?? (string) $key;
            if (!isset($base[$mapped]) || !is_array($row)) {
                continue;
            }

            $scoreRaw = trim((string) ($row['score'] ?? ''));
            $score = ctype_digit($scoreRaw) ? (int) $scoreRaw : null;
            $base[$mapped] = [
                'score' => $score === null ? '' : (string) $score,
                'risk_level' => (string) ($this->assistClassification->classify($mapped, $score) ?? ''),
            ];
        }

        return $base;
    }

    /**
     * @param array<string, mixed>|null $current
     * @param array<string, mixed> $updated
     */
    private function auditReferralWrite(int $id, ?array $current, array $updated): void
    {
        $new = $this->auditSnapshot($updated);

        if ($current === null) {
            $this->audit->created(AuditService::MODULE_SENDA, AuditService::RESOURCE_REFERRAL, $id, $new);

            if (ReferralStatus::isCompleted($updated)) {
                $this->audit->log(
                    AuditService::ACTION_FINALIZED,
                    AuditService::MODULE_SENDA,
                    AuditService::RESOURCE_REFERRAL,
                    $id,
                    null,
                    $new
                );
            } else {
                $this->audit->log(
                    AuditService::ACTION_DRAFT_SAVED,
                    AuditService::MODULE_SENDA,
                    AuditService::RESOURCE_REFERRAL,
                    $id,
                    null,
                    $new
                );
            }

            return;
        }

        $old = $this->auditSnapshot($current);

        if (AuditService::same($old, $new) && ReferralStatus::fromRow($current) === ReferralStatus::fromRow($updated)) {
            return;
        }

        $wasCompleted = ReferralStatus::isCompleted($current);
        $isCompleted = ReferralStatus::isCompleted($updated);

        if ($wasCompleted) {
            $this->audit->log(
                AuditService::ACTION_UPDATED_COMPLETED,
                AuditService::MODULE_SENDA,
                AuditService::RESOURCE_REFERRAL,
                $id,
                $old,
                $new
            );
            return;
        }

        if ($isCompleted) {
            $this->audit->log(
                AuditService::ACTION_FINALIZED,
                AuditService::MODULE_SENDA,
                AuditService::RESOURCE_REFERRAL,
                $id,
                $old,
                $new
            );
            return;
        }

        $this->audit->log(
            AuditService::ACTION_DRAFT_SAVED,
            AuditService::MODULE_SENDA,
            AuditService::RESOURCE_REFERRAL,
            $id,
            $old,
            $new
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        $snapshot = AuditService::pick($row, [
            'id',
            'senda_attention_id',
            'senda_person_id',
            'person_full_name',
            'request_date',
            'demand_origin',
            'receiving_officer',
            'demand_area',
            'request_type',
            'requesting_device',
            'requesting_commune',
            'destination_center',
            'destination_commune',
            'applicant_kind',
            'applicant_name',
            'applicant_role',
            'applicant_institution',
            'applicant_phone',
            'applicant_email',
            'applicant_relationship',
            'gender',
            'health_insurance',
            'nationality',
            'indigenous_people',
            'enrolled_health_center',
            'cesfam_name',
            'emergency_contact_name',
            'emergency_contact_phone',
            'substances',
            'age_of_onset',
            'consumption_frequency',
            'consumption_route',
            'mental_health_history',
            'physical_health_history',
            'family_situation',
            'legal_situation',
            'support_network',
            'has_previous_treatments',
            'previous_treatments_count',
            'previous_treatment_modality',
            'previous_treatment_stay',
            'previous_treatment_completed',
            'previous_treatment_center',
            'previous_treatment_commune',
            'previous_treatments_detail',
            'suicide_risk',
            'violence_risk',
            'street_situation',
            'pregnancy',
            'children_in_care',
            'overall_risk',
            'risk_notes',
            'screening_used',
            'assist_applicable',
            'observations',
            'status',
        ]);
        $snapshot['assist'] = $this->auditAssist($row['assist'] ?? null);

        return $snapshot;
    }

    /**
     * @return array<string, array{score: mixed, risk_level: mixed}>
     */
    private function auditAssist(mixed $assist): array
    {
        if (!is_array($assist)) {
            return [];
        }

        $out = [];
        foreach ($assist as $substance => $row) {
            if (!is_array($row)) {
                continue;
            }

            $out[(string) $substance] = [
                'score' => $row['score'] ?? null,
                'risk_level' => $row['risk_level'] ?? null,
            ];
        }

        return $out;
    }

    private function boolInt(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'on', 'si'], true) ? 1 : 0;
    }

    private function nullableBool(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->boolInt($value);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
