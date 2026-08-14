<div class="error-inner">
    <span class="error-code">500</span>
    <h1>Error interno</h1>
    <p><?= e($message ?? 'Ha ocurrido un problema en el servidor. El incidente quedó registrado.') ?></p>
    <a href="<?= e(url('/')) ?>" class="btn btn-navy">Ir al inicio</a>
</div>
