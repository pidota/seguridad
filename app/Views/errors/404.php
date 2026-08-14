<div class="error-inner">
    <span class="error-code">404</span>
    <h1>Página no encontrada</h1>
    <p><?= e($message ?? 'La ruta solicitada no existe.') ?></p>
    <a href="<?= e(url('/')) ?>" class="btn btn-navy">Ir al inicio</a>
</div>
