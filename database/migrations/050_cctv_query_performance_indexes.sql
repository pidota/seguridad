-- Índices compuestos para dashboard y listados CCTV.
-- Los índices simples (shift_id, log_type_id, occurred_at, status, deleted_at, etc.)
-- ya existen en migraciones 035, 036 y 045.

ALTER TABLE cctv_shifts
    ADD INDEX idx_cctv_shifts_status_deleted_started (status, deleted_at, started_at);

ALTER TABLE cctv_log_entries
    ADD INDEX idx_cctv_log_entries_active_occurred (deleted_at, occurred_at, id);

ALTER TABLE cctv_log_contacts
    ADD INDEX idx_cctv_log_contacts_entry_type (cctv_log_entry_id, contact_type);
