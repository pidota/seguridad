<?php $alert = flash_alert(); ?>
<?php if ($alert): ?>
    <script type="application/json" id="flash-data"><?= json_encode($alert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
