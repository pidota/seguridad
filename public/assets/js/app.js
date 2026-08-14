document.addEventListener('DOMContentLoaded', () => {
    const flashNode = document.getElementById('flash-data');

    if (flashNode) {
        try {
            const flash = JSON.parse(flashNode.textContent || '{}');
            const allowed = ['success', 'error', 'warning', 'info'];
            const icon = allowed.includes(flash.type) ? flash.type : 'info';

            Swal.fire({
                icon,
                title: flash.title || '',
                text: flash.message || '',
                confirmButtonColor: '#0b1f33',
            });
        } catch (error) {
            console.error('No se pudo mostrar el mensaje flash.');
        }
    }

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('submit', (event) => {
            event.preventDefault();
            const form = event.currentTarget;

            Swal.fire({
                icon: 'warning',
                title: form.dataset.confirmTitle || 'Confirmar acción',
                text: form.dataset.confirm || '¿Desea continuar?',
                showCancelButton: true,
                confirmButtonColor: '#0b1f33',
                cancelButtonColor: '#5c6774',
                confirmButtonText: form.dataset.confirmConfirmText || 'Sí, continuar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    HTMLFormElement.prototype.submit.call(form);
                }
            });
        });
    });

    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const toggle = group.querySelector('[data-nav-toggle]');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', () => {
            const open = group.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    const sidebar = document.getElementById('app-sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    const backdrop = document.getElementById('sidebar-backdrop');

    const closeSidebar = () => {
        document.body.classList.remove('sidebar-open');
        if (backdrop) {
            backdrop.hidden = true;
        }
    };

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            const open = document.body.classList.toggle('sidebar-open');
            if (backdrop) {
                backdrop.hidden = !open;
            }
        });
    }

    backdrop?.addEventListener('click', closeSidebar);
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });
});
