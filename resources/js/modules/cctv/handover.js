/**
 * Revisión de traspasos CCTV — SweetAlert2 y formulario de no continuidad.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cctv-handover-accept]').forEach((form) => {
        bindAcceptConfirmation(form);
    });

    document.querySelectorAll('[data-cctv-handover-decline]').forEach((form) => {
        bindDeclineForm(form);
    });

    const alertCount = Number(document.body.dataset.cctvHandoverAlert || 0);
    if (alertCount > 0 && typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'info',
            title: 'Registros pendientes del turno anterior',
            html: '<p>Existen <strong>' + alertCount + '</strong> incidente(s) o novedad(es) en desarrollo que debe revisar para determinar si continuará su gestión.</p>',
            confirmButtonText: 'Revisar ahora',
            showCancelButton: true,
            cancelButtonText: 'Revisar después',
            confirmButtonColor: '#0b1f33',
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = resolveHandoverUrl();
            }
        });
    }
});

function resolveHandoverUrl() {
    const link = document.querySelector('[data-cctv-handover-link]');
    return link ? link.getAttribute('href') : '/cctv/handovers';
}

function bindAcceptConfirmation(form) {
    if (typeof Swal === 'undefined') {
        return;
    }

    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        Swal.fire({
            icon: 'question',
            title: '¿Continuar procedimiento?',
            text: 'Este incidente quedará bajo su gestión durante el turno actual y permanecerá en desarrollo.',
            showCancelButton: true,
            confirmButtonColor: '#0b1f33',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (result.isConfirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });
}

function bindDeclineForm(form) {
    const reasonSelect = form.querySelector('[data-decline-reason]');
    const otherBlock = form.querySelector('[data-decline-other]');
    const institutionBlock = form.querySelector('[data-decline-institution]');
    const approxBlock = form.querySelector('[data-decline-approx-time]');

    const syncFields = () => {
        const value = reasonSelect ? reasonSelect.value : '';
        if (otherBlock) {
            otherBlock.hidden = value !== 'other';
        }
        if (institutionBlock) {
            institutionBlock.hidden = value !== 'derived_no_cctv_followup';
        }
        if (approxBlock) {
            approxBlock.hidden = value !== 'finished_during_handover';
        }
    };

    if (reasonSelect) {
        reasonSelect.addEventListener('change', syncFields);
        syncFields();
    }

    if (typeof Swal === 'undefined') {
        return;
    }

    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === '1') {
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        event.preventDefault();

        const reasonLabel = reasonSelect
            ? reasonSelect.options[reasonSelect.selectedIndex].text
            : '';
        const details = form.querySelector('[name="decision_details"]');
        const justification = details ? details.value.trim() : '';

        Swal.fire({
            icon: 'warning',
            title: 'Confirmar no continuidad',
            html: '<p><strong>Motivo:</strong> ' + escapeHtml(reasonLabel) + '</p>'
                + '<p><strong>Justificación:</strong> ' + escapeHtml(justification) + '</p>'
                + '<p class="mt-2">El registro quedará finalizado indicando que revisó los antecedentes y determinó justificadamente que no correspondía continuar.</p>',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Volver',
            confirmButtonColor: '#9b2c2c',
        }).then((result) => {
            if (result.isConfirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
