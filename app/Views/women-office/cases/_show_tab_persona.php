<?php

$person = is_array($person ?? null) ? $person : [];
$dash = static fn (mixed $value): string => trim((string) $value) !== '' ? (string) $value : '—';
$caseId = (int) ($case['id'] ?? 0);
$personId = (int) ($person['id'] ?? 0);
$canEditPerson = !empty($canEditPerson);

?>
<div class="page-card">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <h3 class="page-card__title mb-0">Persona afectada</h3>
        <?php if ($canEditPerson && $personId > 0): ?>
            <a class="btn btn-sm btn-outline-navy" href="<?= e(url('/women/people/' . $personId . '/edit') . '?' . http_build_query(['return' => url('/women/cases/' . $caseId . '?tab=persona')])) ?>">Editar persona</a>
        <?php endif; ?>
    </div>
    <?php if ($person === []): ?>
        <p class="text-secondary mb-0">No fue posible cargar los datos de la persona afectada.</p>
    <?php else: ?>
        <dl class="women-case-summary">
            <div>
                <dt>Nombre</dt>
                <dd><?= e((string) ($person['full_name'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>RUT</dt>
                <dd><?= e($dash($person['rut'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Fecha de nacimiento</dt>
                <dd><?= e(!empty($person['birth_date']) ? date('d-m-Y', strtotime((string) $person['birth_date'])) : '—') ?></dd>
            </div>
            <div>
                <dt>Edad</dt>
                <dd><?= isset($person['age']) && $person['age'] !== null ? e((string) $person['age']) . ' años' : '—' ?></dd>
            </div>
            <div>
                <dt>Teléfono</dt>
                <dd><?= e($dash($person['phone'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Correo</dt>
                <dd><?= e($dash($person['email'] ?? null)) ?></dd>
            </div>
            <div class="women-case-summary__wide">
                <dt>Domicilio</dt>
                <dd><?= e($dash($person['address'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Sector</dt>
                <dd><?= e($dash($person['sector_name'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Nacionalidad</dt>
                <dd><?= e($dash($person['nationality'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Ocupación</dt>
                <dd><?= e($dash($person['occupation'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Educación</dt>
                <dd><?= e($dash($person['education_level_name'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Contacto seguro</dt>
                <dd><?= e(match ((string) ($person['safe_contact'] ?? '')) {
                    'yes' => 'Sí',
                    'no' => 'No',
                    'restricted' => 'Con restricciones',
                    default => '—',
                }) ?></dd>
            </div>
            <?php if (!empty($person['safe_contact_notes'])): ?>
                <div class="women-case-summary__wide">
                    <dt>Indicaciones de contacto</dt>
                    <dd><?= nl2br(e((string) $person['safe_contact_notes'])) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    <?php endif; ?>
</div>
