<div class="error-inner">
    <span class="error-code">403</span>
    <h1>Acceso denegado</h1>
    <p><?= e($message ?? 'No tiene permisos para acceder a este recurso.') ?></p>
    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-navy">Volver al panel</a>
</div>
