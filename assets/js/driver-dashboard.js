document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.driver-status-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled) return;

            var label = button.textContent.trim();
            Swal.fire({
                title: 'Update trip status?',
                text: label,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, update',
                confirmButtonColor: '#f97316'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                setLoading(button, true);
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            throw new Error(data.message || 'Unable to update trip status.');
                        }
                        if (window.AdminUI) {
                            AdminUI.notify('success', data.message || 'Trip status updated.');
                        }
                        setTimeout(function () { window.location.reload(); }, 700);
                    })
                    .catch(function (error) {
                        if (window.AdminUI) {
                            AdminUI.notify('error', error.message || 'Unable to update trip status.');
                        } else {
                            Swal.fire('Unable to update', error.message || 'Please try again.', 'error');
                        }
                    })
                    .finally(function () {
                        setLoading(button, false);
                    });
            });
        });
    });

    function setLoading(button, loading) {
        var icon = button.querySelector('i');
        button.disabled = loading;
        if (!icon) return;
        if (loading) {
            icon.dataset.originalClass = icon.className;
            icon.className = 'fas fa-spinner fa-spin';
        } else if (icon.dataset.originalClass) {
            icon.className = icon.dataset.originalClass;
            delete icon.dataset.originalClass;
        }
    }
});
