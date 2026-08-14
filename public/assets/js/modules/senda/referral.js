/**
 * Wizard y paneles de la ficha. No sustituye las validaciones PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-senda-referral-form]').forEach((form) => {
        const wizard = initWizard(form);
        initApplicantKind(form);
        initCesfam(form);
        initPreviousTreatments(form);
        initFinalizeConfirm(form);
        form.sendaWizard = wizard;
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

function initApplicantKind(form) {
    const radios = Array.from(form.querySelectorAll('[data-senda-applicant-kind]'));
    if (radios.length === 0) {
        return;
    }

    const extra = form.querySelector('[data-senda-applicant-extra]');
    const personPanel = form.querySelector('[data-senda-applicant-person]');
    const relationship = form.querySelector('#applicant_relationship');
    const name = form.querySelector('#applicant_name');
    const phone = form.querySelector('#applicant_phone');
    const email = form.querySelector('#applicant_email');

    const sync = () => {
        const selected = radios.find((radio) => radio.checked);
        const kind = selected ? selected.value : '';
        const isPerson = kind === 'persona_implicada';
        const needsExtra = kind === 'familiar' || kind === 'institucional';

        radios.forEach((radio) => {
            radio.closest('.senda-choice-card')?.classList.toggle('is-current', radio.checked);
        });

        if (personPanel) {
            personPanel.hidden = !isPerson;
        }

        setPanelVisible(extra, needsExtra, false);

        if (needsExtra && relationship && ['', 'familiar', 'institucional'].includes(relationship.value)) {
            relationship.value = kind;
        }

        if (kind === 'institucional' && name && name.value.trim() === '') {
            name.value = form.dataset.applicantReferralName || '';
            if (phone && phone.value.trim() === '') {
                phone.value = form.dataset.applicantReferralPhone || '';
            }
            if (email && email.value.trim() === '') {
                email.value = form.dataset.applicantReferralEmail || '';
            }
        }
    };

    radios.forEach((radio) => {
        radio.addEventListener('change', sync);
    });
    sync();
}

function initCesfam(form) {
    const enrolled = form.querySelector('[data-senda-cesfam-toggle]');
    const panel = form.querySelector('[data-senda-cesfam]');

    if (!enrolled || !panel) {
        return;
    }

    const sync = (clearName) => {
        setPanelVisible(panel, enrolled.value === 'si', clearName);
    };

    enrolled.addEventListener('change', () => sync(true));
    sync(false);
}

function initPreviousTreatments(form) {
    const toggle = form.querySelector('[data-senda-treatments-toggle]');
    const panel = form.querySelector('[data-senda-treatments-detail]');

    if (!toggle || !panel) {
        return;
    }

    const sync = (clearValues) => {
        setPanelVisible(panel, toggle.value === 'si', clearValues);
    };

    toggle.addEventListener('change', () => sync(true));
    sync(false);
}

function initFinalizeConfirm(form) {
    const buttons = Array.from(form.querySelectorAll('[data-senda-finalize]'));
    if (buttons.length === 0 || typeof Swal === 'undefined') {
        return;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', (event) => {
            if (form.dataset.sendaFinalizeConfirmed === '1') {
                return;
            }

            event.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Finalizar ficha',
                text: 'La ficha quedará finalizada. El servidor volverá a validar todos los campos.',
                showCancelButton: true,
                confirmButtonColor: '#0b1f33',
                cancelButtonColor: '#5c6774',
                confirmButtonText: 'Finalizar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.sendaFinalizeConfirmed = '1';
                    button.click();
                }
            });
        });
    });
}

function initWizard(form) {
    const steps = Array.from(form.querySelectorAll('[data-step]'));
    const navItems = Array.from(form.querySelectorAll('[data-step-goto]'));
    const prev = form.querySelector('[data-step-prev]');
    const next = form.querySelector('[data-step-next]');

    if (steps.length === 0) {
        return null;
    }

    form.classList.add('is-ready');

    let current = 1;
    const invalid = form.querySelector('.is-invalid');
    if (invalid) {
        const section = invalid.closest('[data-step]');
        if (section) {
            current = Number(section.getAttribute('data-step')) || 1;
        }
    }

    const visibleNumbers = () => steps
        .filter((step) => !step.hidden)
        .map((step) => Number(step.getAttribute('data-step')));

    const show = (value) => {
        const numbers = visibleNumbers();
        if (numbers.length === 0) {
            return;
        }

        const min = numbers[0];
        const max = numbers[numbers.length - 1];
        if (!numbers.includes(value)) {
            value = numbers.reduce((best, number) => (number <= value ? number : best), min);
        }

        current = Math.min(max, Math.max(min, value));
        steps.forEach((step) => {
            step.classList.toggle('is-active', Number(step.getAttribute('data-step')) === current);
        });
        navItems.forEach((item) => {
            item.classList.toggle('is-active', Number(item.getAttribute('data-step-goto')) === current);
        });
        if (prev) {
            prev.disabled = current === min;
        }
        if (next) {
            next.disabled = current === max;
        }
    };

    const sibling = (direction) => {
        const numbers = visibleNumbers();
        const index = numbers.indexOf(current);
        return numbers[index + direction] ?? current;
    };

    navItems.forEach((item) => {
        item.addEventListener('click', () => {
            if (item.hidden) {
                return;
            }
            show(Number(item.getAttribute('data-step-goto')));
        });
    });
    prev?.addEventListener('click', () => show(sibling(-1)));
    next?.addEventListener('click', () => show(sibling(1)));
    show(current);

    return {
        refresh() {
            show(current);
        },
        show,
    };
}
