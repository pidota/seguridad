/**
 * Bitácora CCTV — novedades, técnicas y formulario legacy de eventos.
 * Solo UX; validación crítica en PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cctv-log-form]').forEach((form) => {
        initDefaultEventTime(form);
    });

    document.querySelectorAll('[data-cctv-log-technical-form]').forEach((form) => {
        bindTargetPanels(form);
        bindTechnicalOtherPanel(form);
        bindTechnicalClientHints(form);
    });

    document.querySelectorAll('[data-camera-event-form]').forEach((form) => {
        bindLegacyOtherPanel(form);
    });
});

function initDefaultEventTime(form) {
    const timeInput = form.querySelector('[data-cctv-event-time]');

    if (timeInput && !timeInput.value) {
        timeInput.value = formatNowTime();
    }
}

function bindLegacyOtherPanel(form) {
    const toggle = form.querySelector('[data-camera-other-toggle]');
    const panel = form.querySelector('[data-camera-other-panel]');

    if (!toggle || !panel) {
        return;
    }

    const sync = () => {
        panel.hidden = toggle.value !== 'otro';
    };

    toggle.addEventListener('change', sync);
    sync();
}

function bindTargetPanels(form) {
    const toggles = form.querySelectorAll('[data-target-type-toggle]');
    const cameraPanel = form.querySelector('[data-target-camera-panel]');
    const equipmentPanel = form.querySelector('[data-target-equipment-panel]');
    const cameraStatusPanel = form.querySelector('[data-camera-status-panel]');
    const cameraSelect = form.querySelector('[data-camera-select]');
    const equipmentSelect = form.querySelector('#equipment_id');
    const cameraStatusSelect = form.querySelector('[data-camera-status-select]');

    if (toggles.length === 0) {
        return;
    }

    const sync = () => {
        const selected = form.querySelector('[data-target-type-toggle]:checked');
        const targetType = selected ? selected.value : 'camera';
        const isCamera = targetType === 'camera';

        if (cameraPanel) {
            cameraPanel.hidden = !isCamera;
        }

        if (equipmentPanel) {
            equipmentPanel.hidden = isCamera;
        }

        if (cameraStatusPanel) {
            cameraStatusPanel.hidden = !isCamera;
        }

        if (cameraSelect) {
            cameraSelect.disabled = !isCamera;

            if (!isCamera) {
                cameraSelect.value = '';
            }
        }

        if (equipmentSelect) {
            equipmentSelect.disabled = isCamera;

            if (isCamera) {
                equipmentSelect.value = '';
            }
        }

        if (cameraStatusSelect) {
            cameraStatusSelect.disabled = !isCamera;

            if (!isCamera) {
                cameraStatusSelect.value = '';
            }
        }
    };

    toggles.forEach((toggle) => toggle.addEventListener('change', sync));
    sync();
}

function bindTechnicalOtherPanel(form) {
    const toggle = form.querySelector('[data-technical-issue-toggle]');
    const panel = form.querySelector('[data-technical-other-panel]');

    if (!toggle || !panel) {
        return;
    }

    const sync = () => {
        const selected = toggle.options[toggle.selectedIndex];
        const show = selected && selected.getAttribute('data-allows-other') === '1';

        panel.hidden = !show;
    };

    toggle.addEventListener('change', sync);
    sync();
}

function bindTechnicalClientHints(form) {
    form.addEventListener('submit', (event) => {
        const targetType = form.querySelector('[data-target-type-toggle]:checked')?.value ?? '';
        const cameraSelect = form.querySelector('[data-camera-select]');
        const equipmentSelect = form.querySelector('#equipment_id');
        const cameraStatusSelect = form.querySelector('[data-camera-status-select]');
        const issueSelect = form.querySelector('[data-technical-issue-toggle]');
        const otherInput = form.querySelector('#technical_issue_other');
        let blocked = false;

        if (targetType === 'camera' && cameraSelect && cameraSelect.value === '') {
            cameraSelect.classList.add('is-invalid');
            blocked = true;
        } else if (cameraSelect) {
            cameraSelect.classList.remove('is-invalid');
        }

        if (targetType === 'equipment' && equipmentSelect && equipmentSelect.value === '') {
            equipmentSelect.classList.add('is-invalid');
            blocked = true;
        } else if (equipmentSelect) {
            equipmentSelect.classList.remove('is-invalid');
        }

        if (cameraStatusSelect && cameraStatusSelect.value !== '' && (targetType !== 'camera' || !cameraSelect?.value)) {
            cameraStatusSelect.classList.add('is-invalid');
            blocked = true;
        } else if (cameraStatusSelect) {
            cameraStatusSelect.classList.remove('is-invalid');
        }

        const selectedIssue = issueSelect?.options[issueSelect.selectedIndex];

        if (selectedIssue && selectedIssue.getAttribute('data-allows-other') === '1' && otherInput && otherInput.value.trim() === '') {
            otherInput.classList.add('is-invalid');
            blocked = true;
        } else if (otherInput) {
            otherInput.classList.remove('is-invalid');
        }

        if (blocked) {
            event.preventDefault();
        }
    });
}

function formatNowTime() {
    const now = new Date();

    return String(now.getHours()).padStart(2, '0')
        + ':'
        + String(now.getMinutes()).padStart(2, '0');
}
