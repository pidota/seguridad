/**
 * Dashboard CCTV — duración en vivo, navegación y anclas operativas.
 * Solo experiencia de usuario; la lógica crítica vive en PHP.
 */
document.addEventListener('DOMContentLoaded', () => {
    initNavCurrentPage();
    initLiveShiftDuration();
    initDashboardAnchors();
});

function initNavCurrentPage() {
    document.querySelectorAll('.senda-nav__link.is-active').forEach((link) => {
        link.setAttribute('aria-current', 'page');
    });
}

function initLiveShiftDuration() {
    document.querySelectorAll('[data-cctv-live-duration]').forEach((node) => {
        const startedAt = node.getAttribute('data-started-at');

        if (!startedAt) {
            return;
        }

        const startedMs = Date.parse(startedAt.replace(' ', 'T'));

        if (Number.isNaN(startedMs)) {
            return;
        }

        const tick = () => {
            node.textContent = formatDurationLabel(Math.max(0, Math.floor((Date.now() - startedMs) / 1000)));
        };

        tick();
        window.setInterval(tick, 60000);
    });
}

function initDashboardAnchors() {
    if (window.location.hash !== '#bitacora-turno') {
        return;
    }

    const section = document.getElementById('bitacora-turno');

    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function formatDurationLabel(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);

    if (hours > 0) {
        return hours + ' h ' + String(minutes).padStart(2, '0') + ' min';
    }

    return minutes + ' min';
}
