<?php if (!empty($case['has_facts'])): ?>
<div class="page-card">
    <h3 class="page-card__title">Hechos y violencia</h3>
    <dl class="women-case-summary">
        <div>
            <dt>Fecha del hecho</dt>
            <dd>
                <?= e((string) ($case['incident_date_precision_label'] ?? '—')) ?>
                <?php if (!empty($case['incident_date'])): ?>
                    · <?= e(date('d-m-Y', strtotime((string) $case['incident_date']))) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Hora / referencia</dt>
            <dd>
                <?php if (!empty($case['incident_time'])): ?>
                    <?= e(substr((string) $case['incident_time'], 0, 5)) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
                <?php if (!empty($case['incident_time_notes'])): ?>
                    · <?= e((string) $case['incident_time_notes']) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Lugar</dt>
            <dd><?= e((string) ($case['incident_place'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Sector</dt>
            <dd><?= e((string) ($case['incident_sector_name'] ?? '—')) ?></dd>
        </div>
        <div class="women-case-summary__wide">
            <dt>Dirección o referencia</dt>
            <dd><?= e((string) ($case['incident_address'] ?? '—')) ?></dd>
        </div>
        <div class="women-case-summary__wide">
            <dt>Tipos de violencia</dt>
            <dd>
                <?php if (($case['violence_types'] ?? []) === []): ?>
                    —
                <?php else: ?>
                    <ul class="women-tag-list mb-0">
                        <?php foreach ($case['violence_types'] as $type): ?>
                            <li>
                                <?= e((string) ($type['violence_type_name'] ?? '')) ?>
                                <?php if (!empty($type['other_text'])): ?>
                                    (<?= e((string) $type['other_text']) ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </dd>
        </div>
    </dl>
    <div class="women-case-description">
        <h4 class="h6">Descripción</h4>
        <p class="mb-0"><?= nl2br(e((string) ($case['description'] ?? ''))) ?></p>
    </div>
</div>
<?php else: ?>
<div class="page-card">
    <p class="text-secondary mb-0">Aún no se registran los antecedentes del hecho ni los tipos de violencia.</p>
    <?php if (!empty($canEdit)): ?>
        <a class="btn btn-navy btn-sm mt-3" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/facts')) ?>">Completar hechos</a>
    <?php endif; ?>
</div>
<?php endif; ?>
