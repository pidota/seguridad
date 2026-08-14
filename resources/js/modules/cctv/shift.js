/**
 * Turnos CCTV — recepción, entrega, checklist y confirmación SweetAlert2.
 * Solo UX; validación crítica en PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cctv-shift-reception-form]').forEach((form) => {
        bindEquipmentNotes(form);
    });

    document.querySelectorAll('[data-cctv-shift-close-form]').forEach((form) => {
        bindCloseConfirmation(form);
        refreshClosingEndingTime(form);
    });
});

function bindEquipmentNotes(form) {
    const notePanels = form.querySelectorAll('[data-equipment-notes]');

    form.querySelectorAll('[data-equipment-status]').forEach((input) => {
        input.addEventListener('change', () => {
            syncEquipmentNotes(form, notePanels);
        });
    });

    syncEquipmentNotes(form, notePanels);
}

function syncEquipmentNotes(form, notePanels) {
    notePanels.forEach((panel) => {
        const equipmentId = panel.getAttribute('data-equipment-id');
        const selected = form.querySelector('[data-equipment-status][data-equipment-id="' + equipmentId + '"]:checked');
        const status = selected ? selected.value : '';
        const requiresNotes = status === 'con_observaciones' || status === 'no_operativo';

        panel.hidden = !requiresNotes;
    });
}

function bindCloseConfirmation(form) {
    if (typeof Swal === 'undefined') {
        return;
    }

    form.addEventListener('submit', (event) => {
        if (form.dataset.cctvConfirmed === '1') {
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        event.preventDefault();

        const summary = readClosingSummary(form);
        summary.ending_time = formatNowTime();
        refreshClosingEndingTime(form, summary.ending_time);

        Swal.fire({
            icon: 'warning',
            title: '¿Finalizar turno CCTV?',
            html: buildClosingSummaryHtml(summary)
                + '<p class="cctv-shift-close-confirm__message">Una vez finalizado, el turno quedará cerrado y las modificaciones posteriores estarán restringidas según permisos.</p>',
            showCancelButton: true,
            confirmButtonColor: '#0b1f33',
            cancelButtonColor: '#5c6774',
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Cancelar',
            focusCancel: true,
        }).then((result) => {
            if (result.isConfirmed) {
                form.dataset.cctvConfirmed = '1';
                form.submit();
            }
        });
    });
}

function readClosingSummary(form) {
    const fallback = {
        started_time: '—',
        ending_time: formatNowTime(),
        total_entries: 0,
        incidents: 0,
        general_entries: 0,
        technical_issues: 0,
        coordinations: 0,
    };

    try {
        const raw = form.getAttribute('data-closing-summary') || '{}';
        const parsed = JSON.parse(raw);

        return Object.assign({}, fallback, parsed);
    } catch (error) {
        return fallback;
    }
}

function buildClosingSummaryHtml(summary) {
    return ''
        + '<div class="cctv-shift-close-confirm">'
        + '<dl class="cctv-shift-close-confirm__grid">'
        + '<div><dt>Inicio</dt><dd>' + escapeHtml(summary.started_time) + '</dd></div>'
        + '<div><dt>Término</dt><dd>' + escapeHtml(summary.ending_time) + '</dd></div>'
        + '<div><dt>Total registros</dt><dd>' + Number(summary.total_entries || 0) + '</dd></div>'
        + '<div><dt>Incidentes</dt><dd>' + Number(summary.incidents || 0) + '</dd></div>'
        + '<div><dt>Novedades</dt><dd>' + Number(summary.general_entries || 0) + '</dd></div>'
        + '<div><dt>Novedades técnicas</dt><dd>' + Number(summary.technical_issues || 0) + '</dd></div>'
        + '<div><dt>Coordinaciones</dt><dd>' + Number(summary.coordinations || 0) + '</dd></div>'
        + '</dl>'
        + '</div>';
}

function refreshClosingEndingTime(form, value) {
    const endingTime = value || formatNowTime();

    form.querySelectorAll('[data-cctv-closing-ending-time]').forEach((node) => {
        node.textContent = endingTime;
    });
}

function formatNowTime() {
    const now = new Date();

    return String(now.getHours()).padStart(2, '0')
        + ':'
        + String(now.getMinutes()).padStart(2, '0');
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
