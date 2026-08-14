<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use App\Models\Cctv\LogEntry;

/**
 * Calcula el tiempo de respuesta de Carabineros a partir de datos ya registrados.
 *
 * Reglas (sin modificar históricos):
 * - Fin del intervalo: hora de llegada = DATE(occurred_at) + police_arrival_time
 *   (solo si police_arrived = Sí y police_arrival_time está informada).
 * - Inicio del intervalo:
 *   1) Primer aviso a Carabineros (MIN contacted_at con contact_type = carabineros).
 *   2) Si no hay aviso registrado, occurred_at (hora del suceso/incidente).
 * - Si la llegada queda antes que el inicio en el mismo día, se asume llegada al día siguiente.
 * - Sin ambos extremos válidos no se calcula ni se imputa valor.
 */
final class PoliceResponseTimeCalculator
{
    public const SOURCE_CARABINEROS_CONTACT = 'carabineros_contact';
    public const SOURCE_INCIDENT_OCCURRED = 'incident_occurred_at';

    /**
     * @param array{
     *     entry_id?: int|string,
     *     occurred_at?: string,
     *     police_arrival_time?: string|null,
     *     carabineros_notified_at?: string|null
     * } $row
     * @return array{
     *     entry_id: int,
     *     notification_source: string,
     *     notification_at: string,
     *     arrival_at: string,
     *     response_seconds: int
     * }|null
     */
    public function calculate(array $row): ?array
    {
        $entryId = (int) ($row['entry_id'] ?? 0);
        $occurredAt = trim((string) ($row['occurred_at'] ?? ''));
        $arrivalTime = trim((string) ($row['police_arrival_time'] ?? ''));

        if ($entryId < 1 || $occurredAt === '' || $arrivalTime === '') {
            return null;
        }

        $occurredTimestamp = strtotime($occurredAt);
        if ($occurredTimestamp === false) {
            return null;
        }

        $arrivalAt = $this->buildArrivalDateTime($occurredAt, $arrivalTime);
        if ($arrivalAt === null) {
            return null;
        }

        [$notificationAt, $source] = $this->resolveNotificationStart($row, $occurredAt);
        if ($notificationAt === null) {
            return null;
        }

        $notificationTimestamp = strtotime($notificationAt);
        $arrivalTimestamp = strtotime($arrivalAt);
        if ($notificationTimestamp === false || $arrivalTimestamp === false) {
            return null;
        }

        if ($arrivalTimestamp < $notificationTimestamp) {
            $arrivalTimestamp = strtotime($arrivalAt . ' +1 day') ?: false;
            if ($arrivalTimestamp === false) {
                return null;
            }
            $arrivalAt = date('Y-m-d H:i:s', $arrivalTimestamp);
        }

        $responseSeconds = $arrivalTimestamp - $notificationTimestamp;
        if ($responseSeconds < 0) {
            return null;
        }

        return [
            'entry_id' => $entryId,
            'notification_source' => $source,
            'notification_at' => $notificationAt,
            'arrival_at' => $arrivalAt,
            'response_seconds' => $responseSeconds,
        ];
    }

    /**
     * @param list<array{
     *     entry_id: int,
     *     notification_source: string,
     *     notification_at: string,
     *     arrival_at: string,
     *     response_seconds: int
     * }> $rows
     * @return array{
     *     eligible_count: int,
     *     average_seconds: int|null,
     *     min_seconds: int|null,
     *     max_seconds: int|null,
     *     average_label: string,
     *     min_label: string,
     *     max_label: string,
     *     notification_sources: array<string, int>,
     *     criteria: array<string, string>
     * }
     */
    public function summarize(array $rows): array
    {
        if ($rows === []) {
            return $this->emptySummary();
        }

        $seconds = array_map(
            static fn (array $row): int => (int) ($row['response_seconds'] ?? 0),
            $rows
        );

        $sources = [
            self::SOURCE_CARABINEROS_CONTACT => 0,
            self::SOURCE_INCIDENT_OCCURRED => 0,
        ];

        foreach ($rows as $row) {
            $source = (string) ($row['notification_source'] ?? '');
            if (isset($sources[$source])) {
                $sources[$source]++;
            }
        }

        $min = min($seconds);
        $max = max($seconds);
        $average = (int) round(array_sum($seconds) / count($seconds));

        return [
            'eligible_count' => count($rows),
            'average_seconds' => $average,
            'min_seconds' => $min,
            'max_seconds' => $max,
            'average_label' => $this->formatDuration($average),
            'min_label' => $this->formatDuration($min),
            'max_label' => $this->formatDuration($max),
            'notification_sources' => $sources,
            'criteria' => $this->criteriaLabels(),
        ];
    }

    /**
     * @return array{
     *     eligible_count: int,
     *     average_seconds: int|null,
     *     min_seconds: int|null,
     *     max_seconds: int|null,
     *     average_label: string,
     *     min_label: string,
     *     max_label: string,
     *     notification_sources: array<string, int>,
     *     criteria: array<string, string>
     * }
     */
    public function emptySummary(): array
    {
        return [
            'eligible_count' => 0,
            'average_seconds' => null,
            'min_seconds' => null,
            'max_seconds' => null,
            'average_label' => '—',
            'min_label' => '—',
            'max_label' => '—',
            'notification_sources' => [
                self::SOURCE_CARABINEROS_CONTACT => 0,
                self::SOURCE_INCIDENT_OCCURRED => 0,
            ],
            'criteria' => $this->criteriaLabels(),
        ];
    }

    public function formatDuration(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds . ' s';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $remainingSeconds > 0
                ? $minutes . ' min ' . $remainingSeconds . ' s'
                : $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours . ' h';
        }

        return $hours . ' h ' . $remainingMinutes . ' min';
    }

    /**
     * @return array<string, string>
     */
    public function criteriaLabels(): array
    {
        return [
            'arrival' => 'Llegada: hora registrada cuando Carabineros = Sí.',
            'notification_primary' => 'Aviso: primer contacto tipo Carabineros (contacted_at).',
            'notification_fallback' => 'Sin aviso explícito: hora del suceso (occurred_at).',
            'police_flag' => 'Solo incidentes con police_arrived = Sí y hora de llegada informada.',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{0: string|null, 1: string}
     */
    private function resolveNotificationStart(array $row, string $occurredAt): array
    {
        $notifiedAt = trim((string) ($row['carabineros_notified_at'] ?? ''));
        if ($notifiedAt !== '' && strtotime($notifiedAt) !== false) {
            return [$this->normalizeDateTime($notifiedAt), self::SOURCE_CARABINEROS_CONTACT];
        }

        if ($occurredAt !== '' && strtotime($occurredAt) !== false) {
            return [$this->normalizeDateTime($occurredAt), self::SOURCE_INCIDENT_OCCURRED];
        }

        return [null, self::SOURCE_INCIDENT_OCCURRED];
    }

    private function buildArrivalDateTime(string $occurredAt, string $arrivalTime): ?string
    {
        $occurredTimestamp = strtotime($occurredAt);
        if ($occurredTimestamp === false) {
            return null;
        }

        $date = date('Y-m-d', $occurredTimestamp);
        $normalizedTime = $this->normalizeTime($arrivalTime);
        if ($normalizedTime === null) {
            return null;
        }

        $combined = strtotime($date . ' ' . $normalizedTime);

        return $combined !== false ? date('Y-m-d H:i:s', $combined) : null;
    }

    private function normalizeDateTime(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : $value;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
