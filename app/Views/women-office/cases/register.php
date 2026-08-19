<?php
$v = static function (string $key, mixed $fallback = '') use ($defaults): string {
    $value = old($key, $defaults[$key] ?? $fallback);

    return $value === null ? '' : (string) $value;
};
$selectedChannel = (string) old('report_channel_id', '');
$showChannelOther = false;
foreach ($reportChannels ?? [] as $channel) {
    if ((string) $channel['id'] === $selectedChannel && ($channel['slug'] ?? '') === 'otro') {
        $showChannelOther = true;
        break;
    }
}
?>
<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Nueva denuncia</p>
        <h2 class="page-card__title mb-0">1. Datos del registro</h2>
        <p class="text-secondary mb-0">Complete los antecedentes iniciales del caso.</p>
    </div>
</section>

<?= women_nav($womenNav ?? []) ?>

<?= \Core\View::make('women-office/people/card', [
    'person' => $person ?? [],
    'showUse' => false,
], null) ?>

<div class="page-card page-card--md mt-3">
    <form method="post" action="<?= e(url('/women/cases')) ?>" novalidate autocomplete="off" data-women-case-form>
        <?= csrf_field() ?>
        <input type="hidden" name="affected_person_id" value="<?= e((string) ($person['id'] ?? '')) ?>">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">N.º de caso</label>
                <input class="form-control" value="Se asignará automáticamente al guardar" readonly>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="reported_date">Fecha de registro</label>
                <input type="date" class="form-control <?= has_error('reported_date') ? 'is-invalid' : '' ?>" id="reported_date" name="reported_date" value="<?= e($v('reported_date')) ?>" required>
                <?php if (has_error('reported_date')): ?><div class="invalid-feedback"><?= e((string) error('reported_date')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label" for="reported_time">Hora de registro</label>
                <input type="time" class="form-control <?= has_error('reported_time') ? 'is-invalid' : '' ?>" id="reported_time" name="reported_time" value="<?= e($v('reported_time')) ?>" required>
                <?php if (has_error('reported_time')): ?><div class="invalid-feedback"><?= e((string) error('reported_time')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="receiving_officer">Funcionario que registra</label>
            <input class="form-control" id="receiving_officer" value="<?= e($v('receiving_officer')) ?>" readonly>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="report_channel_id">Canal de ingreso</label>
                <select class="form-select <?= has_error('report_channel_id') ? 'is-invalid' : '' ?>" id="report_channel_id" name="report_channel_id" data-women-report-channel-toggle required>
                    <option value="">Seleccione</option>
                    <?php foreach ($reportChannels ?? [] as $channel): ?>
                        <option
                            value="<?= (int) $channel['id'] ?>"
                            data-slug="<?= e((string) $channel['slug']) ?>"
                            <?= $selectedChannel === (string) $channel['id'] ? 'selected' : '' ?>
                        ><?= e((string) $channel['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (has_error('report_channel_id')): ?><div class="invalid-feedback"><?= e((string) error('report_channel_id')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6 mb-3" data-women-report-channel-other <?= $showChannelOther ? '' : 'hidden' ?>>
                <label class="form-label" for="report_channel_other">Especifique</label>
                <input class="form-control <?= has_error('report_channel_other') ? 'is-invalid' : '' ?>" id="report_channel_other" name="report_channel_other" value="<?= e((string) old('report_channel_other', '')) ?>">
                <?php if (has_error('report_channel_other')): ?><div class="invalid-feedback"><?= e((string) error('report_channel_other')) ?></div><?php endif; ?>
            </div>
        </div>

        <button class="btn btn-navy" type="submit">Registrar caso</button>
        <a class="btn btn-outline-navy" href="<?= e(url('/women/cases/create/person')) ?>">Cambiar persona</a>
    </form>
</div>
