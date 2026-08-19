/**
 * Formularios del caso: canal «Otro», hechos y tipos de violencia.
 */
document.addEventListener('DOMContentLoaded', () => {
    initReportChannelToggle();
    initFactsForm();
    initAggressorForm();
    initBackgroundForm();
    initRiskForm();
    initSupportForm();
    initActionsForm();
    initReferralsForm();
    initFollowUpsForm();
});

function initReportChannelToggle() {
    const form = document.querySelector('[data-women-case-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const channelSelect = form.querySelector('[data-women-report-channel-toggle]');
    const channelOther = form.querySelector('[data-women-report-channel-other]');

    if (!(channelSelect instanceof HTMLSelectElement) || !(channelOther instanceof HTMLElement)) {
        return;
    }

    const syncChannelOther = () => {
        const selected = channelSelect.selectedOptions[0];
        const slug = selected?.dataset.slug ?? '';
        channelOther.hidden = slug !== 'otro';
    };

    channelSelect.addEventListener('change', syncChannelOther);
    syncChannelOther();
}

function initFactsForm() {
    const form = document.querySelector('[data-women-case-facts-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const precision = form.querySelector('[data-women-date-precision]');
    const dateRow = form.querySelector('[data-women-incident-date-row]');

    const syncDatePrecision = () => {
        if (!(precision instanceof HTMLSelectElement) || !(dateRow instanceof HTMLElement)) {
            return;
        }

        const undetermined = precision.value === 'undetermined';
        dateRow.hidden = undetermined;
    };

    if (precision instanceof HTMLSelectElement) {
        precision.addEventListener('change', syncDatePrecision);
        syncDatePrecision();
    }

    form.querySelectorAll('[data-women-violence-toggle]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const syncViolenceOther = () => {
            const typeId = input.value;
            const other = form.querySelector(`[data-women-violence-other="${typeId}"]`);
            if (!(other instanceof HTMLElement)) {
                return;
            }

            other.hidden = !input.checked;
        };

        input.addEventListener('change', syncViolenceOther);
        syncViolenceOther();
    });
}

function initAggressorForm() {
    const form = document.querySelector('[data-women-case-aggressor-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const relationshipSelect = form.querySelector('[data-women-relationship-toggle]');
    const relationshipOther = form.querySelector('[data-women-relationship-other]');

    if (relationshipSelect instanceof HTMLSelectElement && relationshipOther instanceof HTMLElement) {
        const syncRelationshipOther = () => {
            const selected = relationshipSelect.selectedOptions[0];
            relationshipOther.hidden = (selected?.dataset.slug ?? '') !== 'otro';
        };

        relationshipSelect.addEventListener('change', syncRelationshipOther);
        syncRelationshipOther();
    }

    const birthWrap = form.querySelector('[data-women-aggressor-birth]');
    const ageWrap = form.querySelector('[data-women-aggressor-age]');
    const birthInput = form.querySelector('[data-women-aggressor-birth-input]');
    const ageInput = form.querySelector('[data-women-aggressor-age-input]');

    const syncAgeFields = () => {
        if (!(birthWrap instanceof HTMLElement) || !(ageWrap instanceof HTMLElement)) {
            return;
        }

        const hasBirth = birthInput instanceof HTMLInputElement && birthInput.value !== '';
        const hasAge = ageInput instanceof HTMLInputElement && ageInput.value.trim() !== '';

        if (hasBirth) {
            ageWrap.hidden = true;
            birthWrap.hidden = false;
            return;
        }

        if (hasAge) {
            birthWrap.hidden = true;
            ageWrap.hidden = false;
            return;
        }

        birthWrap.hidden = false;
        ageWrap.hidden = false;
    };

    if (birthInput instanceof HTMLInputElement) {
        birthInput.addEventListener('change', syncAgeFields);
        birthInput.addEventListener('input', syncAgeFields);
    }

    if (ageInput instanceof HTMLInputElement) {
        ageInput.addEventListener('input', syncAgeFields);
    }

    syncAgeFields();
}

function initBackgroundForm() {
    const form = document.querySelector('[data-women-case-background-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const firstOccurrence = form.querySelector('[data-women-first-occurrence-toggle]');
    const recurrencePanel = form.querySelector('[data-women-recurrence-panel]');
    const previousToggle = form.querySelector('[data-women-previous-reports-toggle]');
    const previousPanel = form.querySelector('[data-women-previous-reports-panel]');
    const formalToggle = form.querySelector('[data-women-formal-report-toggle]');
    const formalPanel = form.querySelector('[data-women-formal-report-panel]');
    const formalInstitution = form.querySelector('[data-women-formal-institution-toggle]');
    const formalOther = form.querySelector('[data-women-formal-institution-other]');
    const list = form.querySelector('[data-women-previous-reports-list]');
    const template = document.querySelector('[data-women-previous-report-template]');
    const addButton = form.querySelector('[data-women-add-previous-report]');

    if (firstOccurrence instanceof HTMLSelectElement && recurrencePanel instanceof HTMLElement) {
        const syncRecurrence = () => {
            recurrencePanel.hidden = firstOccurrence.value !== 'no';
        };
        firstOccurrence.addEventListener('change', syncRecurrence);
        syncRecurrence();
    }

    if (previousToggle instanceof HTMLSelectElement && previousPanel instanceof HTMLElement) {
        const syncPrevious = () => {
            previousPanel.hidden = previousToggle.value !== 'yes';
        };
        previousToggle.addEventListener('change', syncPrevious);
        syncPrevious();
    }

    if (formalToggle instanceof HTMLSelectElement && formalPanel instanceof HTMLElement) {
        const syncFormal = () => {
            formalPanel.hidden = formalToggle.value !== 'yes';
        };
        formalToggle.addEventListener('change', syncFormal);
        syncFormal();
    }

    if (formalInstitution instanceof HTMLSelectElement && formalOther instanceof HTMLElement) {
        const syncFormalOther = () => {
            const selected = formalInstitution.selectedOptions[0];
            formalOther.hidden = (selected?.dataset.slug ?? '') !== 'otra';
        };
        formalInstitution.addEventListener('change', syncFormalOther);
        syncFormalOther();
    }

    const reindexPreviousReports = () => {
        if (!(list instanceof HTMLElement)) {
            return;
        }

        list.querySelectorAll('[data-women-previous-report-row]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((input) => {
                if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
                    return;
                }

                input.name = input.name.replace(
                    /previous_reports\[[^\]]+\]/,
                    `previous_reports[${index}]`
                );
            });
        });
    };

    const bindRemoveButtons = () => {
        if (!(list instanceof HTMLElement)) {
            return;
        }

        list.querySelectorAll('[data-women-remove-previous-report]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-women-previous-report-row]');
                if (!(row instanceof HTMLElement) || !(list instanceof HTMLElement)) {
                    return;
                }

                if (list.querySelectorAll('[data-women-previous-report-row]').length <= 1) {
                    row.querySelectorAll('input, textarea').forEach((field) => {
                        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
                            field.value = '';
                        }
                    });

                    return;
                }

                row.remove();
                reindexPreviousReports();
            });
        });
    };

    if (addButton instanceof HTMLButtonElement && list instanceof HTMLElement && template instanceof HTMLTemplateElement) {
        addButton.addEventListener('click', () => {
            const index = list.querySelectorAll('[data-women-previous-report-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            list.insertAdjacentHTML('beforeend', html);
            bindRemoveButtons();
        });
    }

    bindRemoveButtons();
}

function initRiskForm() {
    const form = document.querySelector('[data-women-case-risk-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.querySelectorAll('[data-women-risk-toggle]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const syncRiskOther = () => {
            const typeId = input.value;
            const other = form.querySelector(`[data-women-risk-other="${typeId}"]`);
            if (!(other instanceof HTMLElement)) {
                return;
            }

            other.hidden = !input.checked;
        };

        input.addEventListener('change', syncRiskOther);
        syncRiskOther();
    });
}

function initSupportForm() {
    const form = document.querySelector('[data-women-case-support-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const protectiveToggle = form.querySelector('[data-women-protective-measures-toggle]');
    const protectivePanel = form.querySelector('[data-women-protective-measures-panel]');
    const minorsToggle = form.querySelector('[data-women-linked-minors-toggle]');
    const minorsPanel = form.querySelector('[data-women-linked-minors-panel]');
    const dependentsToggle = form.querySelector('[data-women-dependents-toggle]');
    const dependentsPanel = form.querySelector('[data-women-dependents-panel]');

    if (protectiveToggle instanceof HTMLSelectElement && protectivePanel instanceof HTMLElement) {
        const syncProtective = () => {
            protectivePanel.hidden = protectiveToggle.value !== 'yes';
        };
        protectiveToggle.addEventListener('change', syncProtective);
        syncProtective();
    }

    if (minorsToggle instanceof HTMLSelectElement && minorsPanel instanceof HTMLElement) {
        const syncMinors = () => {
            minorsPanel.hidden = minorsToggle.value !== 'yes';
        };
        minorsToggle.addEventListener('change', syncMinors);
        syncMinors();
    }

    if (dependentsToggle instanceof HTMLSelectElement && dependentsPanel instanceof HTMLElement) {
        const syncDependents = () => {
            dependentsPanel.hidden = dependentsToggle.value !== 'yes';
        };
        dependentsToggle.addEventListener('change', syncDependents);
        syncDependents();
    }

    form.querySelectorAll('[data-women-need-toggle]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const syncNeedOther = () => {
            const typeId = input.value;
            const other = form.querySelector(`[data-women-need-other="${typeId}"]`);
            if (!(other instanceof HTMLElement)) {
                return;
            }

            other.hidden = !input.checked;
        };

        input.addEventListener('change', syncNeedOther);
        syncNeedOther();
    });

    initRepeatRows(
        form,
        '[data-women-protective-measures-list]',
        '[data-women-protective-measure-row]',
        '[data-women-add-protective-measure]',
        '[data-women-remove-protective-measure]',
        document.querySelector('[data-women-protective-measure-template]'),
        'protective_measures'
    );

    initRepeatRows(
        form,
        '[data-women-linked-minors-list]',
        '[data-women-linked-minor-row]',
        '[data-women-add-linked-minor]',
        '[data-women-remove-linked-minor]',
        document.querySelector('[data-women-linked-minor-template]'),
        'linked_minors'
    );
}

function initRepeatRows(form, listSelector, rowSelector, addSelector, removeSelector, template, fieldPrefix) {
    const list = form.querySelector(listSelector);
    if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
        return;
    }

    const reindexRows = () => {
        list.querySelectorAll(rowSelector).forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((input) => {
                if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement || input instanceof HTMLSelectElement)) {
                    return;
                }

                input.name = input.name.replace(
                    new RegExp(`${fieldPrefix}\\[[^\\]]+\\]`),
                    `${fieldPrefix}[${index}]`
                );
            });
        });
    };

    const bindRemoveButtons = () => {
        list.querySelectorAll(removeSelector).forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest(rowSelector);
                if (!(row instanceof HTMLElement)) {
                    return;
                }

                if (list.querySelectorAll(rowSelector).length <= 1) {
                    row.querySelectorAll('input, textarea, select').forEach((field) => {
                        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                            field.value = '';
                        }
                    });

                    return;
                }

                row.remove();
                reindexRows();
            });
        });
    };

    const addButton = form.querySelector(addSelector);
    if (addButton instanceof HTMLButtonElement) {
        addButton.addEventListener('click', () => {
            const index = list.querySelectorAll(rowSelector).length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            list.insertAdjacentHTML('beforeend', html);
            bindRemoveButtons();
        });
    }

    bindRemoveButtons();
}

function initActionsForm() {
    const form = document.querySelector('[data-women-case-actions-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const bindActionTypeToggles = () => {
        form.querySelectorAll('[data-women-action-type-toggle]').forEach((select) => {
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            const syncActionOther = () => {
                const row = select.closest('[data-women-action-row]');
                if (!(row instanceof HTMLElement)) {
                    return;
                }

                const description = row.querySelector('[data-women-action-description]');
                const selected = select.selectedOptions[0];
                const isOther = (selected?.dataset.slug ?? '') === 'otra';

                if (description instanceof HTMLTextAreaElement) {
                    description.placeholder = isOther
                        ? 'Especifique la acción realizada'
                        : 'Detalle de la acción';
                }
            };

            select.addEventListener('change', syncActionOther);
            syncActionOther();
        });
    };

    initRepeatRows(
        form,
        '[data-women-actions-list]',
        '[data-women-action-row]',
        '[data-women-add-action]',
        '[data-women-remove-action]',
        document.querySelector('[data-women-action-template]'),
        'actions'
    );

    const addButton = form.querySelector('[data-women-add-action]');
    if (addButton instanceof HTMLButtonElement) {
        addButton.addEventListener('click', bindActionTypeToggles);
    }

    bindActionTypeToggles();
}

function initReferralsForm() {
    const form = document.querySelector('[data-women-case-referrals-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    initRepeatRows(
        form,
        '[data-women-referrals-list]',
        '[data-women-referral-row]',
        '[data-women-add-referral]',
        '[data-women-remove-referral]',
        document.querySelector('[data-women-referral-template]'),
        'referrals'
    );
}

function initFollowUpsForm() {
    const form = document.querySelector('[data-women-case-followups-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const bindFollowUpToggles = () => {
        form.querySelectorAll('[data-women-followup-row]').forEach((row) => {
            if (!(row instanceof HTMLElement)) {
                return;
            }

            const contactSelect = row.querySelector('[data-women-followup-contact-toggle]');
            const contactOther = row.querySelector('[data-women-followup-contact-other]');
            const resultSelect = row.querySelector('[data-women-followup-result-toggle]');
            const resultOther = row.querySelector('[data-women-followup-result-other]');
            const requiresSelect = row.querySelector('[data-women-followup-requires-toggle]');
            const nextDate = row.querySelector('[data-women-followup-next-date]');

            if (contactSelect instanceof HTMLSelectElement && contactOther instanceof HTMLElement) {
                const syncContact = () => {
                    const slug = contactSelect.selectedOptions[0]?.dataset.slug ?? '';
                    contactOther.hidden = slug !== 'otro';
                };
                contactSelect.addEventListener('change', syncContact);
                syncContact();
            }

            if (resultSelect instanceof HTMLSelectElement && resultOther instanceof HTMLElement) {
                const syncResult = () => {
                    const slug = resultSelect.selectedOptions[0]?.dataset.slug ?? '';
                    resultOther.hidden = slug !== 'otro';
                };
                resultSelect.addEventListener('change', syncResult);
                syncResult();
            }

            if (requiresSelect instanceof HTMLSelectElement && nextDate instanceof HTMLElement) {
                const syncNext = () => {
                    nextDate.hidden = requiresSelect.value !== 'yes';
                };
                requiresSelect.addEventListener('change', syncNext);
                syncNext();
            }
        });
    };

    initRepeatRows(
        form,
        '[data-women-followups-list]',
        '[data-women-followup-row]',
        '[data-women-add-followup]',
        '[data-women-remove-followup]',
        document.querySelector('[data-women-followup-template]'),
        'followups'
    );

    const addButton = form.querySelector('[data-women-add-followup]');
    if (addButton instanceof HTMLButtonElement) {
        addButton.addEventListener('click', bindFollowUpToggles);
    }

    bindFollowUpToggles();
}
