/**
 * Protección contra pérdida de información en formularios CCTV.
 * Compara el estado actual con una línea base inicial; no autoguarda.
 * Las reglas críticas se validan en PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cctv-unsaved-guard]').forEach((form) => {
        bindUnsavedGuard(form);
    });
});

function bindUnsavedGuard(form) {
    if (form.dataset.cctvUnsavedGuardBound === '1') {
        return;
    }

    form.dataset.cctvUnsavedGuardBound = '1';

    let baseline = '';
    let allowLeave = false;

    const captureBaseline = () => {
        baseline = serializeForm(form);
    };

    captureBaseline();

    const isDirty = () => {
        if (allowLeave || form.dataset.cctvConfirmed === '1') {
            return false;
        }

        return serializeForm(form) !== baseline;
    };

    form.addEventListener('submit', () => {
        allowLeave = true;
    });

    window.addEventListener('beforeunload', (event) => {
        if (!isDirty()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    document.addEventListener('click', (event) => {
        if (!isDirty()) {
            return;
        }

        const link = event.target.closest('a[href]');

        if (!link) {
            return;
        }

        const href = (link.getAttribute('href') || '').trim();

        if (href === '' || href.startsWith('#') || link.target === '_blank') {
            return;
        }

        event.preventDefault();
        confirmLeave(() => {
            allowLeave = true;
            window.location.href = href;
        });
    }, true);
}

function serializeForm(form) {
    const data = new FormData(form);
    const parts = [];

    for (const [key, value] of data.entries()) {
        if (key === '_token' || key === '_method') {
            continue;
        }

        parts.push(key + '=' + String(value));
    }

    parts.sort();

    return parts.join('&');
}

function confirmLeave(onConfirm) {
    if (typeof Swal === 'undefined') {
        if (window.confirm('Hay cambios sin guardar. Si sale ahora, perderá la información ingresada.')) {
            onConfirm();
        }

        return;
    }

    Swal.fire({
        icon: 'warning',
        title: 'Cambios sin guardar',
        text: 'Si sale ahora, perderá la información ingresada en el formulario.',
        showCancelButton: true,
        confirmButtonColor: '#0b1f33',
        cancelButtonColor: '#5c6774',
        confirmButtonText: 'Salir sin guardar',
        cancelButtonText: 'Seguir editando',
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm();
        }
    });
}
