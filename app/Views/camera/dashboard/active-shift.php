<?php

$activeShiftDashboard = $activeShiftDashboard ?? null;

$stats = $activeShiftDashboard['stats'] ?? [];

$recentItems = $activeShiftDashboard['recent_items'] ?? [];

?>

<?php if (!empty($openShift)): ?>

    <section class="cctv-active-shift mb-3" id="turno-activo">

        <div class="cctv-active-shift__header">

            <div>

                <p class="welcome-kicker mb-1">Monitoreo operativo</p>

                <h3 class="cctv-active-shift__title mb-0">TURNO ACTIVO</h3>

            </div>

            <span class="camera-device-badge camera-device-badge--success">En curso</span>

        </div>



        <dl class="cctv-active-shift__stats">

            <div>

                <dt>Operador</dt>

                <dd><?= e((string) ($openShift['operator_label'] ?? '—')) ?></dd>

            </div>

            <div>

                <dt>Hora de inicio</dt>

                <dd><?= e((string) ($openShift['started_time_formatted'] ?? '—')) ?></dd>

            </div>

            <div>

                <dt>Duración</dt>

                <dd
                    data-cctv-live-duration
                    data-started-at="<?= e((string) ($openShift['started_at'] ?? '')) ?>"
                ><?= e((string) ($openShift['duration_label'] ?? '—')) ?></dd>

            </div>

            <div>

                <dt>Entradas</dt>

                <dd><?= (int) ($stats['total_entries'] ?? 0) ?></dd>

            </div>

            <div>

                <dt>Incidentes</dt>

                <dd><?= (int) ($stats['incidents'] ?? 0) ?></dd>

            </div>

            <div>

                <dt>Novedades técnicas</dt>

                <dd><?= (int) ($stats['technical_issues'] ?? 0) ?></dd>

            </div>

            <div>

                <dt>Coordinaciones</dt>

                <dd><?= (int) ($stats['coordinations'] ?? 0) ?></dd>

            </div>

            <div>

                <dt>Comunicaciones a Carabineros</dt>

                <dd><?= (int) ($stats['police_communications'] ?? 0) ?></dd>

            </div>

            <div>

                <dt>Cámaras con problemas</dt>

                <dd><?= (int) ($stats['cameras_with_issues'] ?? 0) ?></dd>

            </div>

        </dl>



        <div class="cctv-active-shift__actions">

            <?php if (!empty($canCreateLog)): ?>

                <a href="<?= e(url('/cctv/log/create')) ?>" class="btn btn-navy">+ Nueva Novedad</a>

                <a href="<?= e(url('/cctv/log/incident/create')) ?>" class="btn btn-outline-navy">+ Incidente</a>

            <?php endif; ?>

            <?php if (!empty($canViewLog)): ?>

                <a href="<?= e(url('/cctv#bitacora-turno')) ?>" class="btn btn-outline-navy">Ver Bitácora</a>

            <?php endif; ?>

            <?php if (!empty($canCloseShift)): ?>

                <a href="<?= e(url('/cctv/shifts/close')) ?>" class="btn btn-outline-danger">Finalizar Turno</a>

            <?php endif; ?>

        </div>



        <?php if ($recentItems !== []): ?>

            <div class="cctv-active-shift__recent">

                <div class="cctv-active-shift__recent-header">

                    <h4 class="cctv-active-shift__subtitle mb-0">Últimas entradas</h4>

                    <span class="cctv-active-shift__recent-count"><?= count($recentItems) ?> recientes</span>

                </div>

                <div class="cctv-active-shift__recent-list">

                    <?php foreach ($recentItems as $item): ?>

                        <article class="cctv-active-shift__recent-item">

                            <div class="cctv-active-shift__recent-head">

                                <time class="cctv-shift-log__time"><?= e((string) ($item['time_label'] ?? '—')) ?></time>

                                <span class="camera-device-badge camera-device-badge--<?= e((string) ($item['type_tone'] ?? 'other')) ?>">

                                    <?= e((string) ($item['type_label'] ?? 'REGISTRO')) ?>

                                </span>

                            </div>

                            <p class="cctv-active-shift__recent-summary mb-0"><?= e((string) ($item['summary'] ?? '—')) ?></p>

                            <?php if (!empty($item['can_view']) && !empty($item['id'])): ?>

                                <a class="cctv-active-shift__recent-link" href="<?= e(url('/cctv/log/' . (int) $item['id'])) ?>">Ver detalle</a>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php else: ?>

            <p class="cctv-active-shift__empty mb-0">Aún no hay entradas registradas en este turno.</p>

        <?php endif; ?>



        <?php if (!empty($openingChecks)): ?>

            <details class="cctv-active-shift__details">

                <summary>Recepción del puesto</summary>

                <ul class="cctv-shift-check-list mt-3 mb-0">

                    <?php foreach ($openingChecks as $check): ?>

                        <li class="cctv-shift-check-list__item">

                            <span class="cctv-shift-check-list__name"><?= e((string) ($check['equipment_name'] ?? 'Equipo')) ?></span>

                            <span class="camera-device-badge camera-device-badge--<?= e((string) ($check['status_tone'] ?? 'other')) ?>">

                                <?= e((string) ($check['status_label'] ?? '—')) ?>

                            </span>

                        </li>

                    <?php endforeach; ?>

                </ul>

            </details>

        <?php endif; ?>

    </section>

<?php endif; ?>

