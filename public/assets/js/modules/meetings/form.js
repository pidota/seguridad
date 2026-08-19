/**
 * Formulario dinámico de reuniones: participantes, temas, acuerdos.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-meetings-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    initRepeatSections(form);
    initNextMeetingToggle(form);
    initUserSearch(form);
});

function initRepeatSections(form) {
    const participants = form.querySelector('[data-meetings-participants]');
    const topics = form.querySelector('[data-meetings-topics]');
    const agreements = form.querySelector('[data-meetings-agreements]');

    form.querySelectorAll('[data-meetings-add-participant]').forEach((button) => {
        button.addEventListener('click', () => {
            const type = button.getAttribute('data-meetings-add-participant') ?? 'internal';
            const templateId = type === 'external'
                ? 'meetings-participant-external-template'
                : 'meetings-participant-internal-template';
            appendFromTemplate(participants, templateId, reindexParticipants);
            initUserSearch(form);
        });
    });

    form.querySelector('[data-meetings-add-topic]')?.addEventListener('click', () => {
        appendFromTemplate(topics, 'meetings-topic-template', reindexTopics);
    });

    form.querySelector('[data-meetings-add-agreement]')?.addEventListener('click', () => {
        appendFromTemplate(agreements, 'meetings-agreement-template', reindexAgreements);
    });

    form.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-meetings-remove-row]')) {
            const row = target.closest('[data-meetings-participant-row], [data-meetings-topic-row], [data-meetings-agreement-row]');
            row?.remove();
            reindexParticipants();
            reindexTopics();
            reindexAgreements();
        }
    });
}

function appendFromTemplate(container, templateId, reindexFn) {
    if (!(container instanceof HTMLElement)) {
        return;
    }

    const template = document.getElementById(templateId);
    if (!(template instanceof HTMLTemplateElement)) {
        return;
    }

    const index = container.children.length;
    const html = template.innerHTML.replaceAll('__INDEX__', String(index));
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const row = wrapper.firstElementChild;
    if (row) {
        container.appendChild(row);
    }

    reindexFn();
}

function reindexParticipants() {
    reindexRows('[data-meetings-participant-row]', 'participants');
}

function reindexTopics() {
    reindexRows('[data-meetings-topic-row]', 'topics', true);
}

function reindexAgreements() {
    reindexRows('[data-meetings-agreement-row]', 'agreements', true);
}

function reindexRows(selector, prefix, showNumber = false) {
    document.querySelectorAll(selector).forEach((row, index) => {
        row.querySelectorAll('[name]').forEach((field) => {
            if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
                return;
            }

            field.name = field.name.replace(/\[\d+\]/, `[${index}]`);
            if (field.id.startsWith('sig_')) {
                field.id = `sig_${index}`;
                const label = row.querySelector(`label[for="${field.id}"]`);
            }
        });

        const checkbox = row.querySelector('input[type="checkbox"][name*="signature_required"]');
        const checkboxLabel = row.querySelector('label[for^="sig_"]');
        if (checkbox instanceof HTMLInputElement && checkboxLabel instanceof HTMLLabelElement) {
            checkbox.id = `sig_${index}`;
            checkboxLabel.setAttribute('for', checkbox.id);
        }

        if (showNumber) {
            const number = row.querySelector('[data-meetings-item-number]');
            if (number) {
                number.textContent = String(index + 1);
            }
        }
    });
}

function initNextMeetingToggle(form) {
    const fields = form.querySelector('[data-meetings-next-fields]');
    const toggles = form.querySelectorAll('[data-meetings-next-toggle]');

    const sync = () => {
        const selected = form.querySelector('[data-meetings-next-toggle]:checked');
        const yes = selected instanceof HTMLInputElement && selected.value === 'yes';
        if (fields instanceof HTMLElement) {
            fields.hidden = !yes;
        }
    };

    toggles.forEach((toggle) => toggle.addEventListener('change', sync));
    sync();
}

function initUserSearch(form) {
    const searchUrl = window.MEETINGS_USER_SEARCH_URL;
    if (!searchUrl) {
        return;
    }

    form.querySelectorAll('[data-meetings-user-search]').forEach((input) => {
        if (!(input instanceof HTMLInputElement) || input.dataset.bound === '1') {
            return;
        }

        input.dataset.bound = '1';
        const row = input.closest('[data-meetings-participant-row]');
        const hidden = row?.querySelector('[data-meetings-user-id]');
        const results = row?.querySelector('[data-meetings-user-results]');
        let timer = null;

        input.addEventListener('input', () => {
            if (!(results instanceof HTMLElement) || !(hidden instanceof HTMLInputElement)) {
                return;
            }

            hidden.value = '';
            window.clearTimeout(timer);
            const term = input.value.trim();
            if (term.length < 2) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }

            timer = window.setTimeout(async () => {
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();
                const items = Array.isArray(payload.data) ? payload.data : [];
                results.innerHTML = items.map((item) => (
                    `<button type="button" class="list-group-item list-group-item-action" data-user-id="${item.id}" data-user-name="${escapeHtml(item.name)}">${escapeHtml(item.name)}${item.email ? ` <small class="text-secondary">${escapeHtml(item.email)}</small>` : ''}</button>`
                )).join('');
                results.hidden = items.length === 0;
            }, 250);
        });

        results?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const button = target.closest('[data-user-id]');
            if (!(button instanceof HTMLElement) || !(hidden instanceof HTMLInputElement) || !(results instanceof HTMLElement)) {
                return;
            }

            hidden.value = button.dataset.userId ?? '';
            input.value = button.dataset.userName ?? '';
            results.hidden = true;
            results.innerHTML = '';
        });
    });
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
