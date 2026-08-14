/**
 * Formato visual de RUT. La validez la comprueba PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-rut-input]').forEach((input) => {
        input.addEventListener('blur', () => {
            const formatted = formatChileanRut(input.value);
            if (formatted) {
                input.value = formatted;
            }
        });
    });
});

function formatChileanRut(value) {
    const clean = String(value).replace(/[^0-9kK]/g, '').toUpperCase();
    if (clean.length < 8 || clean.length > 9) {
        return null;
    }

    const body = clean.slice(0, -1);
    const verifier = clean.slice(-1);
    const chunks = [];

    for (let i = body.length; i > 0; i -= 3) {
        chunks.unshift(body.slice(Math.max(0, i - 3), i));
    }

    return chunks.join('.') + '-' + verifier;
}
