/**
 * Incidentes CCTV — campos condicionales, contactos dinámicos y Carabineros.
 * Solo UX; validación crítica en PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cctv-log-incident-form]').forEach((form) => {
        bindIncidentOtherPanel(form);
        bindPoliceArrivalPanel(form);
        bindCoordinationContactsPanel(form);
        bindContactRows(form);
        initDefaultEventTime(form);
    });
});

function initDefaultEventTime(form) {
    const timeInput = form.querySelector('[data-cctv-event-time]');

    if (timeInput && !timeInput.value) {
        timeInput.value = formatNowTime();
    }
}

function bindIncidentOtherPanel(form) {
    const toggle = form.querySelector('[data-incident-type-toggle]');
    const panel = form.querySelector('[data-incident-other-panel]');

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

function bindPoliceArrivalPanel(form) {
    const toggle = form.querySelector('[data-police-arrival-toggle]');
    const panel = form.querySelector('[data-police-arrival-panel]');
    const timeInput = form.querySelector('[data-police-arrival-time]');
    const feedback = form.querySelector('[data-police-arrival-feedback]');

    if (!toggle || !panel || !timeInput) {
        return;
    }

    const sync = () => {
        const showTime = toggle.value === '1';

        panel.hidden = !showTime;
        timeInput.required = showTime;
        timeInput.disabled = !showTime;

        if (!showTime) {
            timeInput.value = '';
            timeInput.classList.remove('is-invalid');

            if (feedback) {
                feedback.hidden = true;
                feedback.textContent = '';
            }
        }
    };

    toggle.addEventListener('change', sync);
    sync();

    form.addEventListener('submit', (event) => {
        const policeArrived = toggle.value;
        const policeTime = timeInput.value.trim();

        toggle.classList.remove('is-invalid');
        timeInput.classList.remove('is-invalid');

        if (feedback) {
            feedback.hidden = true;
            feedback.textContent = '';
        }

        if (policeArrived === '') {
            event.preventDefault();
            toggle.classList.add('is-invalid');
            toggle.focus();
            return;
        }

        if (policeArrived === '1' && policeTime === '') {
            event.preventDefault();
            timeInput.classList.add('is-invalid');

            if (feedback) {
                feedback.hidden = false;
                feedback.textContent = 'Indique la hora de llegada de Carabineros.';
            }

            timeInput.focus();
            return;
        }

        if (policeArrived !== '1' && policeTime !== '') {
            event.preventDefault();
            timeInput.classList.add('is-invalid');

            if (feedback) {
                feedback.hidden = false;
                feedback.textContent = 'No debe indicar hora si Carabineros no llegó o no aplica.';
            }

            timeInput.focus();
        }
    });
}

function bindCoordinationContactsPanel(form) {
    const toggle = form.querySelector('[data-coordination-toggle]');
    const panel = form.querySelector('[data-coordination-contacts-panel]');

    if (!toggle || !panel) {
        return;
    }

    const sync = () => {
        panel.hidden = toggle.value !== '1';
    };

    toggle.addEventListener('change', sync);
    sync();
}

function bindContactRows(form) {
    const list = form.querySelector('[data-contacts-list]');
    const template = form.querySelector('[data-contact-row-template]');
    const addButton = form.querySelector('[data-add-contact]');

    if (!list || !template) {
        return;
    }

    addButton?.addEventListener('click', () => {
        appendContactRow(list, template);
    });

    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-contact]');

        if (!removeButton) {
            return;
        }

        const rows = list.querySelectorAll('[data-contact-row]');
        const row = removeButton.closest('[data-contact-row]');

        if (rows.length <= 1) {
            clearContactRow(row);
            return;
        }

        row?.remove();
        reindexContactRows(list);
    });

    list.addEventListener('change', (event) => {
        const typeSelect = event.target.closest('[data-contact-type]');

        if (!typeSelect) {
            return;
        }

        syncContactNamePanel(typeSelect.closest('[data-contact-row]'));
    });

    list.querySelectorAll('[data-contact-row]').forEach((row) => {
        syncContactNamePanel(row);
    });
}

function appendContactRow(list, template) {
    const index = list.querySelectorAll('[data-contact-row]').length;
    const html = template.innerHTML.replaceAll('__INDEX__', String(index));
    const wrapper = document.createElement('div');

    wrapper.innerHTML = html.trim();

    const row = wrapper.firstElementChild;

    if (!row) {
        return;
    }

    list.appendChild(row);
    syncContactNamePanel(row);
}

function reindexContactRows(list) {
    list.querySelectorAll('[data-contact-row]').forEach((row, index) => {
        row.querySelectorAll('[name^="contacts["]').forEach((field) => {
            field.name = field.name.replace(/contacts\[\d+]/, 'contacts[' + index + ']');
        });
    });
}

function clearContactRow(row) {
    if (!row) {
        return;
    }

    row.querySelectorAll('input, select, textarea').forEach((field) => {
        if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
            return;
        }

        field.value = '';
    });

    syncContactNamePanel(row);
}

function syncContactNamePanel(row) {
    if (!row) {
        return;
    }

    const typeSelect = row.querySelector('[data-contact-type]');
    const namePanel = row.querySelector('[data-contact-name-panel]');

    if (!typeSelect || !namePanel) {
        return;
    }

    namePanel.hidden = typeSelect.value !== 'otro';
}

function formatNowTime() {
    const now = new Date();

    return String(now.getHours()).padStart(2, '0')
        + ':'
        + String(now.getMinutes()).padStart(2, '0');
}
