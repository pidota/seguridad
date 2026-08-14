<?php
$history = $history ?? [];
?>
<div class="page-card mt-3">
    <h3 class="page-card__title">Historial de Atenciones</h3>
    <?php if ($history === []): ?>
        <p class="text-secondary mb-0">Esta persona aún no tiene atenciones registradas.</p>
    <?php else: ?>
        <div class="senda-tree" aria-label="Historial de atenciones">
            <div class="senda-tree__person"><?= e((string) $record['full_name']) ?></div>
            <ul class="senda-tree__list">
                <?php foreach ($history as $index => $attention): ?>
                    <?php
                        $attentionLast = $index === array_key_last($history);
                        $children = $attention['children'] ?? [];
                        $stem = $attentionLast ? '    ' : '│   ';
                    ?>
                    <li class="senda-tree__attention">
                        <div class="senda-tree__row">
                            <span class="senda-tree__branch" aria-hidden="true"><?= $attentionLast ? '└──' : '├──' ?></span>
                            <?php if (!empty($attention['url'])): ?>
                                <a href="<?= e((string) $attention['url']) ?>">Atención <?= (int) $attention['ordinal'] ?></a>
                            <?php else: ?>
                                <span>Atención <?= (int) $attention['ordinal'] ?></span>
                            <?php endif; ?>
                            <span class="senda-tree__meta">
                                <code><?= e((string) $attention['attention_number']) ?></code>
                                · <?= e((string) $attention['arrived_at']) ?>
                                · <span class="senda-badge senda-badge--<?= e((string) $attention['entry_tone']) ?>"><?= e((string) $attention['entry_label']) ?></span>
                                · <?= e((string) $attention['officer']) ?>
                            </span>
                        </div>

                        <?php if ($children !== []): ?>
                            <ul class="senda-tree__children">
                                <?php foreach ($children as $childIndex => $child): ?>
                                    <?php $childLast = $childIndex === array_key_last($children); ?>
                                    <li class="senda-tree__row">
                                        <span class="senda-tree__branch" aria-hidden="true"><?= e($stem) ?><?= $childLast ? '└──' : '├──' ?></span>
                                        <?php if (!empty($child['url'])): ?>
                                            <a href="<?= e((string) $child['url']) ?>"><?= e((string) $child['label']) ?></a>
                                        <?php else: ?>
                                            <span><?= e((string) $child['label']) ?></span>
                                        <?php endif; ?>
                                        <?php if (($child['meta'] ?? '') !== ''): ?>
                                            <span class="senda-tree__meta">
                                                · <?= e((string) $child['meta']) ?>
                                                <?php if (!empty($child['is_overdue'])): ?>
                                                    <span class="status-pill is-off">Atrasado</span>
                                                <?php elseif (!empty($child['is_due_today'])): ?>
                                                    <span class="status-pill is-on">Hoy</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <dl class="senda-tree__answers">
                            <?php foreach ($attention['answers'] as $item): ?>
                                <div>
                                    <dt><?= e((string) $item['question']) ?></dt>
                                    <dd><?= e((string) $item['answer']) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
