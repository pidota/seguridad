<?php
$items = $items ?? [];
?>
<div class="senda-assist-result">
    <h4 class="h6 mb-2">Resultado ASSIST</h4>
    <div class="table-responsive">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Sustancia</th>
                    <th>Puntuación</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['label'] ?? '')) ?></td>
                        <td><?= e((string) (($row['score'] ?? '') !== '' ? $row['score'] : '—')) ?></td>
                        <td><?= e((string) ($row['risk_label'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
