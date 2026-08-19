<section class="page-toolbar">
    <div>
        <p class="welcome-kicker mb-1">Perfil</p>
        <h2 class="page-card__title mb-0">Mi Firma Simple</h2>
        <p class="text-secondary mb-0 mt-1">Cargue una imagen PNG con fondo transparente para reutilizarla al firmar reuniones.</p>
    </div>
</section>

<div class="page-card page-card--md mb-3">
    <?php if (($activeSignature ?? null) !== null): ?>
        <h3 class="h6">Firma activa</h3>
        <div class="meetings-signature-preview mb-3">
            <img src="<?= e((string) ($imageUrl ?? '')) ?>" alt="Mi firma simple">
        </div>
        <p class="text-secondary small mb-0">Al subir una nueva imagen, la firma anterior dejará de usarse en futuras solicitudes.</p>
    <?php else: ?>
        <p class="text-secondary mb-0">Aún no ha registrado una firma simple. Debe hacerlo antes de firmar reuniones.</p>
    <?php endif; ?>
</div>

<div class="page-card page-card--md">
    <h3 class="h6 mb-3"><?= ($activeSignature ?? null) !== null ? 'Actualizar firma' : 'Registrar firma' ?></h3>
    <form method="post" action="<?= e((string) ($storeUrl ?? url('/meetings/profile/signature'))) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="signature">Imagen PNG (máx. 1 MB)</label>
            <input class="form-control" type="file" id="signature" name="signature" accept="image/png" required>
        </div>
        <button type="submit" class="btn btn-navy">Guardar firma</button>
    </form>
</div>
