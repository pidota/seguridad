/**
 * Muestra ASSIST y calcula la clasificación solo en pantalla.
 * El puntaje y el riesgo persistidos los clasifica PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-senda-referral-form]').forEach((form) => {
        initScreening(form);
        initAssistScores(form);
    });
});

function initScreening(form) {
    const radios = Array.from(form.querySelectorAll('[data-senda-screening]'));
    const panel = form.querySelector('[data-senda-assist-panel]');
    const observations = form.querySelector('[data-senda-observations-step]');
    const observationsNav = form.querySelector('[data-step-goto="8"]');
    const screeningEnd = form.querySelector('[data-senda-screening-end]');

    if (radios.length === 0) {
        return;
    }

    const selectedValue = () => {
        const selected = radios.find((radio) => radio.checked);
        return selected ? selected.value : '';
    };

    const sync = () => {
        const value = selectedValue();
        const used = value === 'si';
        const skipped = value === 'no';

        radios.forEach((radio) => {
            radio.closest('.senda-choice-card')?.classList.toggle('is-current', radio.checked);
        });

        if (panel) {
            panel.hidden = !used;
            panel.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !used;
            });
        }

        if (observations) {
            observations.hidden = skipped;
        }
        if (observationsNav) {
            observationsNav.hidden = skipped;
        }
        if (screeningEnd) {
            screeningEnd.hidden = !skipped;
        }

        form.dataset.skipObservations = skipped ? '1' : '0';
        form.sendaWizard?.refresh();
    };

    radios.forEach((radio) => {
        radio.addEventListener('change', sync);
    });
    sync();
}

function initAssistScores(form) {
    const table = form.querySelector('[data-senda-assist-table]');
    if (!table) {
        return;
    }

    let rules = {};
    try {
        rules = JSON.parse(table.getAttribute('data-assist-rules') || '{}');
    } catch (error) {
        rules = {};
    }

    const labels = rules.labels && typeof rules.labels === 'object' ? rules.labels : {};
    const fallback = rules.default && typeof rules.default === 'object'
        ? rules.default
        : { minima_max: 3, breve_max: 20 };

    const riskFor = (substance, raw) => {
        if (raw === '') {
            return '—';
        }

        const score = Number(raw);
        if (!Number.isInteger(score) || score < 0) {
            return '—';
        }

        const band = rules[substance] && typeof rules[substance] === 'object'
            ? rules[substance]
            : fallback;
        const minimaMax = Number(band.minima_max);
        const breveMax = Number(band.breve_max);

        if (score <= minimaMax) {
            return labels.intervencion_minima || 'Intervención Mínima';
        }
        if (score <= breveMax) {
            return labels.intervencion_breve || 'Intervención Breve';
        }

        return labels.tratamiento || 'Tratamiento';
    };

    table.querySelectorAll('[data-assist-substance]').forEach((row) => {
        const input = row.querySelector('[data-assist-score]');
        const output = row.querySelector('[data-assist-risk]');
        if (!input || !output) {
            return;
        }

        const sync = () => {
            output.textContent = riskFor(row.getAttribute('data-assist-substance') || '', input.value.trim());
        };

        input.addEventListener('input', sync);
        sync();
    });
}
