<?php

$caseId = (int) ($case['id'] ?? 0);
$documents = is_array($documents ?? null) ? $documents : [];
$canUpload = !empty($canUploadDocuments);
$canEdit = !empty($canEdit);

?>
<div class="page-card">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-card__title mb-1">Documentos</h3>
            <p class="text-secondary mb-0">Archivos adjuntos al caso. Se almacenan de forma segura y se descargan mediante el sistema.</p>
        </div>
    </div>

    <?php if ($canUpload): ?>
        <form class="women-documents-upload mb-4" method="post" action="<?= e(url('/women/cases/' . $caseId . '/documents')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="document">Adjuntar documento</label>
                    <input class="form-control" type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    <div class="form-text">PDF, JPG, PNG, DOC o DOCX. Máximo 5 MB.</div>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-navy w-100" type="submit">Subir archivo</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($documents === []): ?>
        <p class="text-secondary mb-0">No hay documentos adjuntos en este caso.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tamaño</th>
                        <th>Subido por</th>
                        <th>Fecha</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document): ?>
                        <?php
                        $documentId = (int) ($document['id'] ?? 0);
                        $uploadedAt = trim((string) ($document['uploaded_at'] ?? ''));
                        ?>
                        <tr>
                            <td><?= e((string) ($document['original_filename'] ?? 'Documento')) ?></td>
                            <td><?= e((string) ($document['file_size_label'] ?? '—')) ?></td>
                            <td><?= e((string) ($document['uploaded_by_name'] ?? '—')) ?></td>
                            <td><?= e($uploadedAt !== '' ? date('d-m-Y H:i', strtotime($uploadedAt)) : '—') ?></td>
                            <td class="text-end">
                                <?php if (hasPermission('women.documents.view')): ?>
                                    <a class="btn btn-sm btn-outline-navy" href="<?= e(url('/women/cases/' . $caseId . '/documents/' . $documentId)) ?>">Descargar</a>
                                <?php endif; ?>
                                <?php if ($canUpload): ?>
                                    <form class="d-inline" method="post" action="<?= e(url('/women/cases/' . $caseId . '/documents/' . $documentId . '/delete')) ?>" onsubmit="return confirm('¿Eliminar este documento adjunto?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
