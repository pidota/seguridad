<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Repositories\Meetings\UserSignatureRepository;
use Core\Auth;
use Core\Exceptions\HttpException;

final class UserSignatureService
{
    private const MAX_BYTES = 1_048_576;

    public function __construct(
        private readonly UserSignatureRepository $signatures = new UserSignatureRepository()
    ) {
    }

    public function findActive(int $userId): ?array
    {
        return $this->signatures->findActiveByUserId($userId);
    }

    public function assertActive(int $userId): array
    {
        $signature = $this->findActive($userId);
        if ($signature === null) {
            throw new HttpException(422, 'Debe registrar su firma simple en Mi Firma antes de firmar reuniones.');
        }

        return $signature;
    }

    /**
     * @param array<string, mixed> $upload
     */
    public function store(int $userId, array $upload): void
    {
        if ((int) (Auth::id() ?? 0) !== $userId) {
            throw new HttpException(403, 'No puede modificar la firma de otro usuario.');
        }

        $stored = $this->storeFile($upload, $userId);
        $this->signatures->deactivateForUser($userId);
        $this->signatures->create([
            'user_id' => $userId,
            'image_path' => $stored['path'],
        ]);
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'signatures/')) {
            throw new HttpException(404, 'Firma no encontrada.');
        }

        $absolute = $this->storageDirectory() . DIRECTORY_SEPARATOR . basename($relativePath);
        if (!is_file($absolute)) {
            throw new HttpException(404, 'Firma no encontrada.');
        }

        $realBase = realpath($this->storageDirectory());
        $realFile = realpath($absolute);
        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            throw new HttpException(404, 'Firma no encontrada.');
        }

        return $realFile;
    }

    /**
     * @param array<string, mixed> $upload
     * @return array{path: string}
     */
    private function storeFile(array $upload, int $userId): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'No se pudo cargar la imagen de firma.');
        }

        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new HttpException(422, 'La firma supera el tamaño permitido (1 MB).');
        }

        $originalName = basename((string) ($upload['name'] ?? 'firma.png'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'png') {
            throw new HttpException(422, 'La firma debe ser un archivo PNG.');
        }

        $mime = $this->detectMime((string) ($upload['tmp_name'] ?? ''));
        if ($mime !== 'image/png') {
            throw new HttpException(422, 'La firma debe ser una imagen PNG válida.');
        }

        $directory = $this->storageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new HttpException(500, 'No fue posible preparar el almacenamiento de firmas.');
        }

        $filename = 'user_' . $userId . '_' . bin2hex(random_bytes(8)) . '.png';
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        $stored = is_uploaded_file($tmpPath) ? move_uploaded_file($tmpPath, $target) : copy($tmpPath, $target);
        if (!$stored) {
            throw new HttpException(500, 'No fue posible guardar la firma.');
        }

        return ['path' => 'signatures/' . $filename];
    }

    public function copySnapshot(string $sourceRelativePath, int $meetingId, int $userId): string
    {
        $source = $this->resolveAbsolutePath($sourceRelativePath);
        $directory = $this->meetingSignaturesDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new HttpException(500, 'No fue posible preparar el almacenamiento de firmas de reunión.');
        }

        $filename = 'meeting_' . $meetingId . '_user_' . $userId . '_' . bin2hex(random_bytes(4)) . '.png';
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!copy($source, $target)) {
            throw new HttpException(500, 'No fue posible generar el respaldo de la firma.');
        }

        return 'meeting-signatures/' . $filename;
    }

    public function resolveMeetingSignaturePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..') || !str_starts_with($relativePath, 'meeting-signatures/')) {
            throw new HttpException(404, 'Firma no encontrada.');
        }

        $absolute = $this->meetingSignaturesDirectory() . DIRECTORY_SEPARATOR . basename($relativePath);
        if (!is_file($absolute)) {
            throw new HttpException(404, 'Firma no encontrada.');
        }

        return $absolute;
    }

    private function storageDirectory(): string
    {
        return BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'signatures';
    }

    private function meetingSignaturesDirectory(): string
    {
        return BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'meeting-signatures';
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
}
