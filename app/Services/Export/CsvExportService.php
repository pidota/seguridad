<?php

declare(strict_types=1);

namespace App\Services\Export;

final class CsvExportService
{
    /**
     * @param list<list<string|int|float|null>> $rows
     */
    public function build(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo generar el archivo CSV.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                static fn (string|int|float|null $value): string => (string) ($value ?? ''),
                $row
            ));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new \RuntimeException('No se pudo leer el archivo CSV generado.');
        }

        return "\xEF\xBB\xBF" . $csv;
    }

    public function download(string $filename, string $content): never
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'indicadores.csv';

        if (!str_ends_with(strtolower($safeName), '.csv')) {
            $safeName .= '.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Content-Length: ' . (string) strlen($content));

        echo $content;
        exit;
    }
}
