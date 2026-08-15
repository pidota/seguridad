<?php

declare(strict_types=1);

namespace App\Services\Cctv;

use Core\Exceptions\HttpException;

final class ComplaintDocumentService
{
    private const MAX_BYTES = 5_242_880;

    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    /**
     * @param array<string, mixed> $upload
     * @return array{path: string, original_name: string, mime: string, size: int}
     */
    public function store(int $visitId, array $upload, ?string $previousPath = null): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'No se pudo cargar el documento de respaldo.');
        }

        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new HttpException(422, 'El documento supera el tamaño permitido (5 MB).');
        }

        $originalName = basename((string) ($upload['name'] ?? 'documento'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$extension])) {
            throw new HttpException(422, 'Formato de documento no permitido.');
        }

        $mime = $this->detectMime((string) ($upload['tmp_name'] ?? ''));
        if ($mime === null || !in_array($mime, self::ALLOWED[$extension], true)) {
            throw new HttpException(422, 'El contenido del archivo no coincide con su extensión.');
        }

        $directory = $this->storageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new HttpException(500, 'No fue posible preparar el almacenamiento seguro.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file((string) $upload['tmp_name'], $target)) {
            throw new HttpException(500, 'No fue posible guardar el documento de respaldo.');
        }

        if ($previousPath !== null) {
            $this->deleteStored($previousPath);
        }

        return [
            'path' => 'cctv/complaints/' . $filename,
            'original_name' => $originalName,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'cctv/complaints/')) {
            throw new HttpException(404, 'Documento no encontrado.');
        }

        $absolute = $this->storageDirectory() . DIRECTORY_SEPARATOR . basename($relativePath);
        if (!is_file($absolute)) {
            throw new HttpException(404, 'Documento no encontrado.');
        }

        $realBase = realpath($this->storageDirectory());
        $realFile = realpath($absolute);
        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            throw new HttpException(404, 'Documento no encontrado.');
        }

        return $realFile;
    }

    private function storageDirectory(): string
    {
        return BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cctv' . DIRECTORY_SEPARATOR . 'complaints';
    }

    private function detectMime(string $tmpPath): ?string
    {
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        return is_string($mime) ? $mime : null;
    }

    private function deleteStored(string $relativePath): void
    {
        try {
            $absolute = $this->resolveAbsolutePath($relativePath);
            @unlink($absolute);
        } catch (\Throwable) {
        }
    }
}
