<?php if (!empty($case['has_aggressor'])): ?>
<div class="page-card">
    <h3 class="page-card__title">Persona denunciada</h3>
    <dl class="women-case-summary">
        <div>
            <dt>Relación</dt>
            <dd>
                <?= e((string) ($case['relationship_type_name'] ?? '—')) ?>
                <?php if (!empty($case['relationship_other'])): ?>
                    — <?= e((string) $case['relationship_other']) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Relación actual</dt>
            <dd><?= e((string) ($case['current_relationship_label'] ?? '—')) ?></dd>
        </div>
        <?php $aggressor = $case['aggressor'] ?? null; ?>
        <?php if (is_array($aggressor) && ($aggressor['full_name'] ?? '') !== ''): ?>
            <div>
                <dt>Nombre</dt>
                <dd><?= e((string) $aggressor['full_name']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (is_array($aggressor) && !empty($aggressor['rut'])): ?>
            <div>
                <dt>RUT</dt>
                <dd><?= e((string) $aggressor['rut']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (is_array($aggressor) && ($aggressor['age'] !== null || !empty($aggressor['approximate_age']))): ?>
            <div>
                <dt>Edad</dt>
                <dd>
                    <?php if ($aggressor['age'] !== null): ?>
                        <?= e((string) $aggressor['age']) ?> años
                    <?php else: ?>
                        <?= e((string) ($aggressor['approximate_age'] ?? '—')) ?>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endif; ?>
        <?php if (is_array($aggressor) && !empty($aggressor['phone'])): ?>
            <div>
                <dt>Teléfono</dt>
                <dd><?= e((string) $aggressor['phone']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (is_array($aggressor) && !empty($aggressor['occupation'])): ?>
            <div>
                <dt>Ocupación</dt>
                <dd><?= e((string) $aggressor['occupation']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (is_array($aggressor) && !empty($aggressor['address'])): ?>
            <div class="women-case-summary__wide">
                <dt>Domicilio</dt>
                <dd><?= e((string) $aggressor['address']) ?></dd>
            </div>
        <?php endif; ?>
    </dl>
    <?php if (is_array($aggressor) && !empty($aggressor['notes'])): ?>
        <div class="women-case-description">
            <h4 class="h6">Observaciones</h4>
            <p class="mb-0"><?= nl2br(e((string) $aggressor['notes'])) ?></p>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="page-card">
    <p class="text-secondary mb-0">Aún no se registran antecedentes de la persona denunciada ni la relación.</p>
    <?php if (!empty($canEdit)): ?>
        <a class="btn btn-navy btn-sm mt-3" href="<?= e(url('/women/cases/' . ($case['id'] ?? '') . '/aggressor')) ?>">Completar persona denunciada</a>
    <?php endif; ?>
</div>
<?php endif; ?>
