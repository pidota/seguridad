<div class="error-inner">
    <span class="error-code">419</span>
    <h1>Sesión expirada</h1>
    <p><?= e($message ?? 'El token de seguridad no es válido o la sesión caducó. Vuelva a intentar.') ?></p>
    <a href="<?= e(url('/login')) ?>" class="btn btn-navy">Volver a ingresar</a>
</div>
