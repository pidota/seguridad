-- Traspasos declinados deben quedar asociados al turno entrante que cerró el pendiente.
UPDATE cctv_log_entries e
INNER JOIN cctv_log_handovers h ON h.log_entry_id = e.id
SET e.cctv_shift_id = h.to_shift_id,
    e.current_operator_id = h.reviewed_by,
    e.updated_at = NOW()
WHERE h.handover_status = 'not_continued'
  AND h.to_shift_id IS NOT NULL
  AND h.reviewed_by IS NOT NULL
  AND e.deleted_at IS NULL
  AND (
        e.cctv_shift_id <> h.to_shift_id
        OR e.current_operator_id <> h.reviewed_by
      );
