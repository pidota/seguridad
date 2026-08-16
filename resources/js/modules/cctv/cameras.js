(function () {
    function initCameraMapPicker() {
        const container = document.querySelector('[data-camera-map-picker]');
        if (!container) {
            return;
        }

        if (typeof L === 'undefined') {
            container.innerHTML = '<div class="alert alert-warning mb-0">No fue posible cargar el mapa. Verifique su conexión a internet y recargue la página.</div>';
            return;
        }

        const latInput = document.querySelector('[data-camera-lat]');
        const lngInput = document.querySelector('[data-camera-lng]');
        const clearButton = document.querySelector('[data-camera-map-clear]');
        let config = {};

        try {
            config = JSON.parse(container.getAttribute('data-map-config') || '{}');
        } catch (error) {
            config = {};
        }

        const defaultLat = Number(config.defaultLat ?? -33.4489);
        const defaultLng = Number(config.defaultLng ?? -70.6693);
        const defaultZoom = Number(config.defaultZoom ?? 13);
        const initialLat = parseFloat(container.getAttribute('data-initial-lat') || '');
        const initialLng = parseFloat(container.getAttribute('data-initial-lng') || '');
        const hasInitial = Number.isFinite(initialLat) && Number.isFinite(initialLng);

        const map = L.map(container, {
            scrollWheelZoom: true,
        }).setView(hasInitial ? [initialLat, initialLng] : [defaultLat, defaultLng], hasInitial ? 16 : defaultZoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        let marker = null;

        function setCoordinates(lat, lng) {
            if (latInput) {
                latInput.value = lat.toFixed(7);
            }
            if (lngInput) {
                lngInput.value = lng.toFixed(7);
            }
        }

        function clearCoordinates() {
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
            if (latInput) {
                latInput.value = '';
            }
            if (lngInput) {
                lngInput.value = '';
            }
        }

        function placeMarker(lat, lng) {
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', function () {
                    const position = marker.getLatLng();
                    setCoordinates(position.lat, position.lng);
                });
            }

            setCoordinates(lat, lng);
        }

        map.on('click', function (event) {
            placeMarker(event.latlng.lat, event.latlng.lng);
        });

        if (hasInitial) {
            placeMarker(initialLat, initialLng);
        }

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                clearCoordinates();
            });
        }

        window.requestAnimationFrame(function () {
            map.invalidateSize();
        });
        setTimeout(function () {
            map.invalidateSize();
        }, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCameraMapPicker);
    } else {
        initCameraMapPicker();
    }
})();
