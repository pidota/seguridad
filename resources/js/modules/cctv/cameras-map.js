(function () {
    const container = document.querySelector('[data-cameras-map]');
    if (!container || typeof L === 'undefined') {
        return;
    }

    let config = {};
    let cameras = [];

    try {
        config = JSON.parse(container.getAttribute('data-map-config') || '{}');
        cameras = JSON.parse(container.getAttribute('data-cameras') || '[]');
    } catch (error) {
        return;
    }

    if (cameras.length === 0) {
        return;
    }

    const defaultLat = Number(config.defaultLat ?? -33.4489);
    const defaultLng = Number(config.defaultLng ?? -70.6693);
    const defaultZoom = Number(config.defaultZoom ?? 13);

    const map = L.map(container).setView([defaultLat, defaultLng], defaultZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const bounds = [];

    cameras.forEach(function (camera) {
        const lat = Number(camera.lat);
        const lng = Number(camera.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        bounds.push([lat, lng]);

        const popupHtml = `
            <div class="cctv-map-popup">
                <strong>${escapeHtml(camera.code)}</strong><br>
                ${escapeHtml(camera.name)}<br>
                <small>${escapeHtml(camera.status)}</small>
                ${camera.location ? `<br><small>${escapeHtml(camera.location)}</small>` : ''}
                ${camera.sector && camera.sector !== '—' ? `<br><small>Sector: ${escapeHtml(camera.sector)}</small>` : ''}
                ${camera.editUrl ? `<br><a href="${escapeHtml(camera.editUrl)}">Editar cámara</a>` : ''}
            </div>
        `;

        L.marker([lat, lng]).addTo(map).bindPopup(popupHtml);
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 16);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40] });
    }

    setTimeout(function () {
        map.invalidateSize();
    }, 150);

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
