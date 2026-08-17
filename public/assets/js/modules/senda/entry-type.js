/**
 * Comportamiento visual del menú Atención.
 * La persistencia y las reglas las valida PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.senda-nav__link.is-active').forEach((link) => {
        link.setAttribute('aria-current', 'page');
    });

    document.querySelectorAll('.senda-choice-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (typeof Swal === 'undefined' || form.dataset.sendaConfirmed === '1') {
                return;
            }

            const label = form.querySelector('.senda-choice-card__label');
            const title = label ? label.textContent.trim() : 'Atención';
            const isFollowUp = form.dataset.menuAction === 'followup';

            event.preventDefault();
            Swal.fire({
                icon: 'question',
                title: isFollowUp ? 'Ir a seguimiento' : 'Confirmar tipo de ingreso',
                text: isFollowUp
                    ? 'Se abrirá el módulo de seguimiento SENDA.'
                    : 'Se usará «' + title + '» en las atenciones de esta sesión.',
                showCancelButton: true,
                confirmButtonColor: '#0b1f33',
                cancelButtonColor: '#5c6774',
                confirmButtonText: 'Continuar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.sendaConfirmed = '1';
                    form.submit();
                }
            });
        });
    });
});
