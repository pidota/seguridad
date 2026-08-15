<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogType;
use App\Repositories\Cctv\OfficeVisitRepository;
use App\Support\ChileanRutValidator;
use Core\Database;
use Core\Exceptions\HttpException;

final class OfficeVisitService
{
    public function __construct(
        private readonly OfficeVisitRepository $visits = new OfficeVisitRepository(),
        private readonly LogEntryService $logEntries = new LogEntryService(),
        private readonly CctvAuditService $cctvAudit = new CctvAuditService(),
        private readonly RecordingRequestStatusCatalog $statuses = new RecordingRequestStatusCatalog()
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createGeneralVisit(array $data, int $shiftId, int $operatorId): int
    {
        $visitReason = trim((string) ($data['visit_reason'] ?? ''));
        if ($visitReason !== '' && !VisitReasonCatalog::isValid($visitReason)) {
            throw new HttpException(422, 'Seleccione un motivo de visita válido.');
        }

        $visitReasonOther = null;
        if (VisitReasonCatalog::isOther($visitReason)) {
            $visitReasonOther = trim((string) ($data['visit_reason_other'] ?? ''));
            if ($visitReasonOther === '') {
                throw new HttpException(422, 'Especifique el motivo de la visita.');
            }
        }

        return Database::transaction(function () use ($data, $shiftId, $operatorId, $visitReason, $visitReasonOther): int {
            $visitId = $this->visits->create([
                'cctv_shift_id' => $shiftId,
                'visitor_type' => VisitorTypeCatalog::GENERAL,
                'visit_reason' => $visitReason !== '' ? $visitReason : null,
                'visit_reason_other' => $visitReasonOther,
                'visit_date' => $data['visit_date'],
                'arrival_time' => $this->normalizeTime($data['arrival_time']),
                'departure_time' => $this->nullableTime($data['departure_time'] ?? null),
                'requester_name' => trim((string) $data['requester_name']),
                'requester_rut' => $this->nullableRut($data['requester_rut'] ?? null),
                'requester_phone' => $this->nullableString($data['requester_phone'] ?? null),
                'requester_email' => $this->nullableString($data['requester_email'] ?? null),
                'organization' => $this->nullableString($data['organization'] ?? null),
                'authorized_by' => $this->nullableInt($data['authorized_by'] ?? null),
                'reason' => trim((string) $data['reason']),
                'internal_notes' => $this->nullableString($data['internal_notes'] ?? null),
                'recording_requested' => 0,
                'created_by' => $operatorId,
            ]);

            $summary = sprintf(
                'Persona concurre a oficina CCTV: %s.',
                $this->excerpt(trim((string) $data['reason']))
            );
            $this->logEntries->createOfficeSummary(
                $shiftId,
                $operatorId,
                LogType::SLUG_OFFICE_VISIT,
                $summary,
                (string) $data['visit_date'],
                $this->normalizeTime($data['arrival_time']),
                'cctv_office_visit',
                $visitId
            );

            $created = $this->visits->findById($visitId);
            $this->cctvAudit->officeVisitCreated($visitId, $this->snapshot($created));

            return $visitId;
        });
    }

    public function detail(int $visitId): array
    {
        $record = $this->visits->findById($visitId);
        if ($record === null) {
            throw new HttpException(404, 'La visita no existe.');
        }

        $record['visitor_type_label'] = VisitorTypeCatalog::label((string) $record['visitor_type']);
        $record['visit_reason_label'] = VisitReasonCatalog::label($record['visit_reason'] ?? null);
        if (VisitReasonCatalog::isOther($record['visit_reason'] ?? null) && !empty($record['visit_reason_other'])) {
            $record['visit_reason_label'] .= ': ' . $record['visit_reason_other'];
        }

        return $record;
    }

    public function registerDeparture(int $visitId, ?string $departureTime = null): void
    {
        $record = $this->visits->findById($visitId);
        if ($record === null) {
            throw new HttpException(404, 'La visita no existe.');
        }

        if (!empty($record['departure_time'])) {
            throw new HttpException(422, 'La salida ya fue registrada.');
        }

        if ((int) ($record['recording_requested'] ?? 0) === 1) {
            throw new HttpException(422, 'Use el detalle de solicitud para visitas de grabación.');
        }

        $time = $departureTime !== null && $departureTime !== ''
            ? $this->normalizeTime($departureTime)
            : date('H:i:s');

        $this->visits->registerDeparture($visitId, $time);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 20): array
    {
        $page = max(1, $page);
        $result = $this->visits->paginate($filters, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));
        $data = array_map(function (array $row): array {
            $row['visitor_type_label'] = VisitorTypeCatalog::label((string) $row['visitor_type']);
            if (!empty($row['recording_status'])) {
                $row['recording_status_label'] = $this->statuses->label((string) $row['recording_status']);
                $row['recording_status_tone'] = $this->statuses->tone((string) $row['recording_status']);
            }

            return $row;
        }, $result['data']);

        return [
            'data' => $data,
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
        ];
    }

    /**
     * @param array<string, mixed>|null $record
     * @return array<string, mixed>
     */
    private function snapshot(?array $record): array
    {
        if ($record === null) {
            return [];
        }

        return [
            'id' => $record['id'],
            'visitor_type' => $record['visitor_type'],
            'visit_date' => $record['visit_date'],
            'requester_name' => $record['requester_name'],
            'recording_requested' => (int) ($record['recording_requested'] ?? 0),
        ];
    }

    private function excerpt(string $text): string
    {
        if (mb_strlen($text) <= 120) {
            return $text;
        }

        return mb_substr($text, 0, 117) . '...';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function nullableRut(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $formatted = ChileanRutValidator::format($value);
        if ($formatted === null) {
            throw new HttpException(422, 'El RUT ingresado no es válido.');
        }

        return $formatted;
    }

    private function normalizeTime(mixed $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            throw new HttpException(422, 'La hora indicada no es válida.');
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function nullableTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return $this->normalizeTime($value);
    }
}
