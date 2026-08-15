(function () {
    const form = document.getElementById('cctv-visit-form');
    if (form) {
        const typeInputs = form.querySelectorAll('[data-visit-type]');
        const recordingPanels = form.querySelectorAll('[data-recording-only]');
        const generalPanels = form.querySelectorAll('[data-general-only]');
        const complaintRadios = form.querySelectorAll('[data-complaint-toggle]');
        const complaintFields = form.querySelector('[data-complaint-fields]');
        const complaintWarning = form.querySelector('[data-complaint-warning]');
        const visitReason = form.querySelector('[data-visit-reason]');
        const visitReasonOther = form.querySelector('[data-visit-reason-other]');

        function selectedType() {
            const checked = form.querySelector('[data-visit-type]:checked');
            return checked ? checked.value : 'general_visit';
        }

        function toggleType() {
            const isRecording = selectedType() === 'recording_request';
            recordingPanels.forEach((panel) => {
                panel.hidden = !isRecording;
            });
            generalPanels.forEach((panel) => {
                panel.hidden = isRecording;
            });
            toggleComplaint();
            toggleVisitReasonOther();
        }

        function toggleComplaint() {
            if (selectedType() !== 'recording_request') {
                if (complaintFields) complaintFields.hidden = true;
                if (complaintWarning) complaintWarning.hidden = true;
                return;
            }

            const yes = form.querySelector('[data-complaint-toggle="yes"]');
            const hasComplaint = yes && yes.checked;
            if (complaintFields) complaintFields.hidden = !hasComplaint;
            if (complaintWarning) complaintWarning.hidden = hasComplaint;
        }

        function toggleVisitReasonOther() {
            if (!visitReason || !visitReasonOther) {
                return;
            }
            visitReasonOther.hidden = visitReason.value !== 'other';
        }

        typeInputs.forEach((input) => input.addEventListener('change', toggleType));
        complaintRadios.forEach((input) => input.addEventListener('change', toggleComplaint));
        if (visitReason) {
            visitReason.addEventListener('change', toggleVisitReasonOther);
        }
        toggleType();
    }

    const statusForm = document.getElementById('cctv-status-form');
    if (statusForm) {
        const statusSelect = statusForm.querySelector('[data-status-select]');
        const extras = statusForm.querySelectorAll('[data-status-extra]');

        function toggleStatusExtras() {
            const value = statusSelect ? statusSelect.value : '';
            extras.forEach((panel) => {
                panel.hidden = panel.getAttribute('data-status-extra') !== value;
            });
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', toggleStatusExtras);
            toggleStatusExtras();
        }
    }

    const deliveryForm = document.getElementById('cctv-delivery-form');
    if (deliveryForm && window.Swal) {
        deliveryForm.addEventListener('submit', function (event) {
            event.preventDefault();
            let summary = {};
            try {
                summary = JSON.parse(deliveryForm.getAttribute('data-delivery-summary') || '{}');
            } catch (error) {
                summary = {};
            }

            const receiverName = deliveryForm.querySelector('#receiver_name')?.value || '—';
            const html = `
                <div class="text-start small">
                    <p><strong>Solicitud:</strong> ${summary.request_number || '—'}</p>
                    <p><strong>Solicitante:</strong> ${summary.requester_name || '—'}</p>
                    <p><strong>Denuncia:</strong> ${summary.complaint_verified || '—'}</p>
                    <p><strong>Grabación:</strong> ${summary.recording_located || '—'}</p>
                    <p><strong>Autorización:</strong> ${summary.authorized || '—'}</p>
                    <p><strong>Persona que recibe:</strong> ${receiverName}</p>
                </div>
            `;

            Swal.fire({
                title: 'Confirmar entrega de grabación',
                html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Confirmar entrega',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    deliveryForm.submit();
                }
            });
        });
    }
})();
