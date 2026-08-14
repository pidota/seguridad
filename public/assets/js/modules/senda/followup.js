/**
 * Muestra campos «Otro» y la fecha del próximo seguimiento.
 * La obligatoriedad la resuelve PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-senda-followup-form]').forEach((form) => {
        initOtherPanels(form);
        initNextDate(form);
    });
});

function setPanelVisible(panel, show, clearValues) {
    if (!panel) {
        return;
    }

    panel.hidden = !show;
    panel.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = !show;
        if (!show && clearValues) {
            field.value = '';
            field.classList.remove('is-invalid');
        }
    });
}

function initOtherPanels(form) {
    form.querySelectorAll('[data-senda-other-toggle]').forEach((toggle) => {
        const key = toggle.getAttribute('data-senda-other-toggle');
        const panel = form.querySelector('[data-senda-other-panel="' + key + '"]');
        if (!panel) {
            return;
        }

        const sync = (clearValues) => {
            setPanelVisible(panel, toggle.value === 'otro', clearValues);
        };

        toggle.addEventListener('change', () => sync(true));
        sync(false);
    });
}

function initNextDate(form) {
    const nextToggle = form.querySelector('[data-senda-next-toggle]');
    const nextPanel = form.querySelector('[data-senda-next-panel]');
    if (!nextToggle || !nextPanel) {
        return;
    }

    const sync = (clearValues) => {
        setPanelVisible(nextPanel, nextToggle.value === 'si', clearValues);
    };

    nextToggle.addEventListener('change', () => sync(true));
    sync(false);
}
