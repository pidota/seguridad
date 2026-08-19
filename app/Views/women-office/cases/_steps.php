<?php
$currentStep = (int) ($currentStep ?? 1);
$steps = [
    1 => 'Datos del registro',
    2 => 'Hechos y violencia',
    3 => 'Persona denunciada',
    4 => 'Antecedentes',
    5 => 'Riesgo y prioridad',
    6 => 'Medidas y necesidades',
    7 => 'Acciones realizadas',
    8 => 'Derivaciones',
    9 => 'Seguimientos',
];
?>
<nav class="women-case-steps" aria-label="Pasos del registro">
    <ol class="women-case-steps__list">
        <?php foreach ($steps as $number => $label): ?>
            <li class="women-case-steps__item <?= $number === $currentStep ? 'is-current' : ($number < $currentStep ? 'is-done' : '') ?>">
                <span class="women-case-steps__number"><?= $number ?></span>
                <span class="women-case-steps__label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
