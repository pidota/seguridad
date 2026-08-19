<?php

declare(strict_types=1);

namespace App\Services\WomenOffice;

use App\Repositories\WomenOffice\CaseDocumentRepository;
use Core\Auth;
use Core\Exceptions\HttpException;

final class WomenCaseDocumentService
{
    private const MAX_BYTES = 5_242_880;

    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    public function __construct(
        private readonly CaseDocumentRepository $documents = new CaseDocumentRepository(),
        private readonly WomenCaseService $cases = new WomenCaseService()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCase(int $caseId): array
    {
        $case = $this->cases->findDetailed($caseId);
        $this->cases->assertCanView($case);

        return array_map([$this, 'present'], $this->documents->forCase($caseId));
    }

    /**
     * @param array<string, mixed> $upload
     */
    public function upload(int $caseId, array $upload): int
    {
        $case = $this->cases->findDetailed($caseId);
        $this->cases->assertCanEdit($case);

        if (!hasPermission('women.documents.upload')) {
            throw new HttpException(403, 'No tiene permiso para adjuntar documentos.');
        }

        $stored = $this->storeFile($upload);
        $userId = Auth::id();
        if ($userId === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        return $this->documents->create([
            'case_id' => $caseId,
            'original_filename' => $stored['original_name'],
            'stored_filename' => basename($stored['path']),
            'storage_path' => $stored['path'],
            'mime_type' => $stored['mime'],
            'file_size' => $stored['size'],
            'uploaded_by' => $userId,
        ]);
    }

    public function download(int $caseId, int $documentId): array
    {
        $case = $this->cases->findDetailed($caseId);
        $this->cases->assertCanView($case);

        if (!hasPermission('women.documents.view')) {
            throw new HttpException(403, 'No tiene permiso para consultar documentos.');
        }

        $document = $this->documents->findById($documentId);
        if ($document === null || (int) ($document['case_id'] ?? 0) !== $caseId) {
            throw new HttpException(404, 'Documento no encontrado.');
        }

        return [
            'document' => $this->present($document),
            'absolute_path' => $this->resolveAbsolutePath((string) $document['storage_path']),
        ];
    }

    public function delete(int $caseId, int $documentId): void
    {
        $case = $this->cases->findDetailed($caseId);
        $this->cases->assertCanEdit($case);

        if (!hasPermission('women.documents.upload')) {
            throw new HttpException(403, 'No tiene permiso para eliminar documentos.');
        }

        $document = $this->documents->findById($documentId);
        if ($document === null || (int) ($document['case_id'] ?? 0) !== $caseId) {
            throw new HttpException(404, 'Documento no encontrado.');
        }

        $this->documents->softDelete($documentId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $size = (int) ($row['file_size'] ?? 0);
        $row['file_size_label'] = $this->formatBytes($size);

        return $row;
    }

    /**
     * @param array<string, mixed> $upload
     * @return array{path: string, original_name: string, mime: string, size: int}
     */
    private function storeFile(array $upload): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'No se pudo cargar el documento.');
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
        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        $stored = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $target)
            : copy($tmpPath, $target);
        if (!$stored) {
            throw new HttpException(500, 'No fue posible guardar el documento.');
        }

        return [
            'path' => 'women/cases/' . $filename,
            'original_name' => $originalName,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'women/cases/')) {
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
        return BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'women' . DIRECTORY_SEPARATOR . 'cases';
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

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1_048_576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1_048_576, 1) . ' MB';
    }
}
