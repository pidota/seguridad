/**
 * Muestra u oculta los antecedentes de derivación.
 * PHP decide si esos campos son obligatorios según el tipo de ingreso.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-senda-attention-form]').forEach((form) => {
        const panel = form.querySelector('[data-senda-referral-panel]');
        if (!panel) {
            return;
        }

        const typeField = form.querySelector('[name="entry_type"]');

        const entryType = () => {
            if (typeField && typeField.value) {
                return typeField.value;
            }

            return form.getAttribute('data-entry-type') || '';
        };

        const sync = () => {
            const show = entryType() === 'derivacion';
            panel.hidden = !show;
            panel.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !show;
            });
        };

        typeField?.addEventListener('change', sync);
        sync();
    });
});
