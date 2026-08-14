<?php
$shiftTimeline = $shiftTimeline ?? [];
?>
<?php if (!empty($openShift) && !empty($canViewLog)): ?>
    <?= \Core\View::make('camera/shifts/_shift-timeline', [
        'shiftTimeline' => $shiftTimeline,
        'logOrderOptions' => $logOrderOptions ?? [],
        'title' => 'Bitácora del Turno',
        'hint' => ((int) ($shiftTimeline['total'] ?? 0)) . ' registros en el turno activo',
        'sectionId' => 'bitacora-turno',
        'showOrderToggle' => true,
        'formAction' => url('/cctv'),
        'canViewLog' => true,
    ], null) ?>
<?php endif; ?>
