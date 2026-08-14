<?php

declare(strict_types=1);

namespace App\Services\Senda;

final class AssistClassificationService
{
    public const MINIMA = 'intervencion_minima';
    public const BREVE = 'intervencion_breve';
    public const TRATAMIENTO = 'tratamiento';

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return [
            ['value' => self::MINIMA, 'label' => 'Intervención Mínima'],
            ['value' => self::BREVE, 'label' => 'Intervención Breve'],
            ['value' => self::TRATAMIENTO, 'label' => 'Tratamiento'],
        ];
    }

    public function classify(string $substance, ?int $score): ?string
    {
        if ($score === null || $score < 0) {
            return null;
        }

        [$minimaMax, $breveMax] = $this->thresholds($substance);

        if ($score <= $minimaMax) {
            return self::MINIMA;
        }

        if ($score <= $breveMax) {
            return self::BREVE;
        }

        return self::TRATAMIENTO;
    }

    public function label(?string $classification): string
    {
        foreach ($this->options() as $option) {
            if ($option['value'] === $classification) {
                return $option['label'];
            }
        }

        return '—';
    }

    /**
     * Umbrales para la vista previa en el navegador. No se usan para persistir.
     *
     * @return array{
     *     alcohol: array{minima_max: int, breve_max: int},
     *     tabaco: array{minima_max: int, breve_max: int},
     *     default: array{minima_max: int, breve_max: int},
     *     labels: array<string, string>
     * }
     */
    public function clientRules(): array
    {
        $labels = [];
        foreach ($this->options() as $option) {
            $labels[$option['value']] = $option['label'];
        }

        return [
            'alcohol' => ['minima_max' => 10, 'breve_max' => 20],
            'tabaco' => ['minima_max' => 3, 'breve_max' => 20],
            'default' => ['minima_max' => 3, 'breve_max' => 20],
            'labels' => $labels,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function thresholds(string $substance): array
    {
        if ($substance === 'alcohol') {
            return [10, 20];
        }

        return [3, 20];
    }
}
