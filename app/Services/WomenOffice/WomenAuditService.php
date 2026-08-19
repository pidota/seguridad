<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Services\AuditService;

/**
 * Auditoría del módulo Women con snapshots sin datos personales completos.
 */
final class WomenAuditService
{
    private const TEXT_EXCERPT = 120;

    /** @var list<string> */
    private const TEXT_FIELDS = [
        'description',
        'incident_address',
        'incident_time_notes',
        'occurrence_notes',
        'dependents_notes',
        'relationship_other',
        'report_channel_other',
        'notes',
        'reason',
        'program_area',
        'closure_notes',
        'cancellation_reason',
    ];

    /** @var list<string> */
    private const PII_FIELDS = [
        'first_names',
        'paternal_surname',
        'maternal_surname',
        'rut',
        'rut_normalized',
        'birth_date',
        'phone',
        'address',
        'occupation',
        'contact_person',
        'institution_name',
        'reference_number',
        'report_number',
        'safe_contact_notes',
        'email',
        'full_name',
        'person_full_name',
    ];

    public function __construct(private readonly AuditService $audit = new AuditService())
    {
    }

    public function viewedCase(int|string $caseId, string $caseNumber, string $section = 'detail'): void
    {
        $this->audit->log(
            AuditService::ACTION_VIEW_CASE,
            AuditService::MODULE_WOMEN,
            AuditService::RESOURCE_WOMEN_CASE,
            $caseId,
            null,
            [
                'case_id' => $caseId,
                'case_number' => $caseNumber,
                'section' => $section,
            ]
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function caseCreated(int|string $id, array $snapshot): void
    {
        $this->audit->created(
            AuditService::MODULE_WOMEN,
            AuditService::RESOURCE_WOMEN_CASE,
            $id,
            $this->sanitize($snapshot)
        );
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public function caseUpdated(int|string $id, array $old, array $new): void
    {
        $this->audit->updated(
            AuditService::MODULE_WOMEN,
            AuditService::RESOURCE_WOMEN_CASE,
            $id,
            $this->sanitize($old),
            $this->sanitize($new)
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (in_array((string) $key, self::PII_FIELDS, true)) {
                continue;
            }

            if (in_array((string) $key, self::TEXT_FIELDS, true) && is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $sanitized[$key . '_excerpt'] = mb_substr($trimmed, 0, self::TEXT_EXCERPT);
                }

                continue;
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    $items = [];
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $items[] = $this->sanitize($item);
                        }
                    }
                    $sanitized[$key] = $items;

                    continue;
                }

                $sanitized[$key] = $this->sanitize($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
