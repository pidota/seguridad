<?php
$old = $record['old_values'] ? json_decode((string) $record['old_values'], true) : null;
$new = $record['new_values'] ? json_decode((string) $record['new_values'], true) : null;
?>
<div class="page-card page-card--xl">
    <p class="badge-soon mb-3">Solo lectura</p>
    <h2 class="page-card__title">Registro #<?= (int) $record['id'] ?></h2>

    <dl class="audit-dl">
        <div><dt>Fecha/hora</dt><dd><?= e(date('d-m-Y H:i:s', strtotime($record['created_at']))) ?></dd></div>
        <div><dt>Usuario</dt><dd><?= e((string) ($record['user_name'] ?? 'Sistema')) ?></dd></div>
        <div><dt>Acción</dt><dd><?= e(audit_action_label((string) $record['action'])) ?></dd></div>
        <div><dt>Módulo</dt><dd><?= e(permission_module_label($record['module'])) ?></dd></div>
        <div><dt>Recurso</dt><dd><?= e(audit_resource_label(isset($record['resource']) ? (string) $record['resource'] : null)) ?></dd></div>
        <div><dt>ID</dt><dd><?= e((string) ($record['resource_id'] ?? '—')) ?></dd></div>
        <div><dt>IP</dt><dd><?= e((string) ($record['ip_address'] ?? '—')) ?></dd></div>
        <div><dt>User Agent</dt><dd><?= e((string) ($record['user_agent'] ?? '—')) ?></dd></div>
    </dl>

    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <h3 class="h6">Valor anterior</h3>
            <pre class="audit-json"><?= $old ? e((string) json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '—' ?></pre>
        </div>
        <div class="col-md-6">
            <h3 class="h6">Valor posterior</h3>
            <pre class="audit-json"><?= $new ? e((string) json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) : '—' ?></pre>
        </div>
    </div>

    <a href="<?= e(url('/audit')) ?>" class="btn btn-outline-navy mt-3">Volver</a>
</div>
