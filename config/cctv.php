<?php

declare(strict_types=1);

/**
 * Configuración operativa del módulo CCTV.
 * Solo editable por administradores vía despliegue o futura pantalla de ajustes.
 *
 * Reglas operacionales documentadas:
 * - No almacenar videos como BLOB en MySQL.
 * - Distinguir grabación original vs copia preparada para entrega; esta app no debe sobrescribir originales.
 * - file_hash_sha256 en entregas queda preparado para verificación futura (SHA-256).
 * - retention_until preparado para políticas documentales; sin borrados automáticos aún.
 */
return [
    'recording_request_pending_alert_days' => (int) env('CCTV_RECORDING_PENDING_ALERT_DAYS', 3),
    'complaint_document_max_bytes' => (int) env('CCTV_COMPLAINT_DOCUMENT_MAX_BYTES', 5_242_880),
    'complaint_document_allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
];
