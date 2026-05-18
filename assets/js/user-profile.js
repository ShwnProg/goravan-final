/* user-profile.js - Profile page specific JavaScript */

(function () {


    let editForm = document.getElementById('editform');

    //AJAX SWEET ALERT

    editForm.addEventListener('submit', function (e) {
        e.preventDefault();
        let formData = new FormData(editForm);

        fetch('../../controllers/users/ProfileController.php', {
            method: 'POST',
            body: formData,
            dataType: 'json'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Profile Updated',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: data.message || 'An error occurred while updating your profile.',
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'A network error occurred. Please check your connection and try again.',
                });
                console.error('ProfileController error:', error);
            });
    });

    let changePasswordForm = document.getElementById('changePasswordForm');

    changePasswordForm.addEventListener('submit', function (e) {
        e.preventDefault();
        let formData = new FormData(changePasswordForm);


        fetch('../../controllers/users/PasswordController.php', {
            method: 'POST',
            body: formData,
            dataType: 'json'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Changed',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: data.message || 'An error occurred while updating your password.',
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'A network error occurred. Please check your connection and try again.',
                });
                console.error('ProfileController error:', error);
            });
    });
    // Menu item interactions
    var menuItems = document.querySelectorAll('.u-menu-item');
    menuItems.forEach(function (item) {
        item.addEventListener('click', function (e) {
            this.style.transform = 'scale(0.98)';
            setTimeout(function () { item.style.transform = ''; }, 150);

            if (this.classList.contains('danger')) {
                if (!confirm('Are you sure you want to sign out?')) {
                    e.preventDefault();
                }
            }
        });
    });

    // Input field focus effects
    var inputs = document.querySelectorAll('.u-form-group input');
    inputs.forEach(function (input) {
        input.addEventListener('focus', function () { this.parentElement.classList.add('focused'); });
        input.addEventListener('blur', function () { this.parentElement.classList.remove('focused'); });
    });

    // Password match indicator
    var newPasswordInput = document.getElementById('newPassword');
    var confirmPasswordInput = document.getElementById('confirmPassword');
    if (newPasswordInput && confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function () {
            this.style.borderColor = (this.value !== newPasswordInput.value)
                ? 'var(--u-danger)'
                : 'var(--u-success)';
        });
    }

    // Phone number formatting
    var phoneInput = document.getElementById('contact');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            var value = this.value.replace(/\D/g, '');
            if (value.length <= 4) {
                this.value = value;
            } else if (value.length <= 7) {
                this.value = value.slice(0, 4) + ' ' + value.slice(4);
            } else {
                this.value = value.slice(0, 4) + ' ' + value.slice(4, 7) + ' ' + value.slice(7, 11);
            }
        });
    }

    initNotificationPreferences();

    function initNotificationPreferences() {
        var prefInputs = document.querySelectorAll('[data-notif-pref]');
        if (!prefInputs.length) return;

        var storageKey = 'gv-user-notif-prefs';
        var defaults = {
            booking: true,
            reminder: true,
            payment: true,
            verification: true,
            schedule: true
        };

        applyPrefs(readPrefs());

        var saveBtn = document.getElementById('saveNotificationPrefs');
        var resetBtn = document.getElementById('resetNotificationPrefs');

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                var prefs = {};
                prefInputs.forEach(function (input) {
                    prefs[input.dataset.notifPref] = input.checked;
                });
                localStorage.setItem(storageKey, JSON.stringify(Object.assign({}, defaults, prefs)));
                window.dispatchEvent(new Event('gv-notif-prefs-updated'));
                closePreferencesModal();
                notifyPreferenceChange('Notification preferences saved.');
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                localStorage.setItem(storageKey, JSON.stringify(defaults));
                applyPrefs(defaults);
                window.dispatchEvent(new Event('gv-notif-prefs-updated'));
                notifyPreferenceChange('Notification preferences reset.');
            });
        }

        function readPrefs() {
            try {
                return Object.assign({}, defaults, JSON.parse(localStorage.getItem(storageKey) || '{}'));
            } catch (e) {
                return defaults;
            }
        }

        function applyPrefs(prefs) {
            prefInputs.forEach(function (input) {
                input.checked = prefs[input.dataset.notifPref] !== false;
            });
        }

        function notifyPreferenceChange(message) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: message,
                    timer: 1200,
                    showConfirmButton: false
                });
            }
        }

        function closePreferencesModal() {
            var modalEl = document.getElementById('notificationPreferencesModal');
            if (!modalEl || !window.bootstrap) return;
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    }
})();


/* Verification system - AJAX + Bootstrap modal */
(function () {
    var modalEl = document.getElementById('verifModal');
    if (!modalEl) return; // Not on profile page

    var verifModal = new bootstrap.Modal(modalEl);

    // DOM refs
    var actionInput = document.getElementById('verifAction');
    var csrfInput = document.getElementById('verifCsrf');
    var modalTitle = document.getElementById('verifModalTitle');
    var typeSelect = document.getElementById('verifType');
    var fileInput = document.getElementById('verifDoc');
    var fileName = document.getElementById('verifFileName');
    var alertBox = document.getElementById('verifAlert');
    var btnSubmit = document.getElementById('btnSubmitVerif');
    var btnText = document.getElementById('verifBtnText');
    var btnSpinner = document.getElementById('verifBtnSpinner');

    // Label map
    var labels = {
        submit_verification: { title: 'Submit Verification', btn: 'Submit' },
        resubmit_verification: { title: 'Resubmit Verification', btn: 'Resubmit' },
        update_verification: { title: 'Update Verification', btn: 'Update' },
    };

    // Open modal via trigger buttons.
    document.querySelectorAll('.verif-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = this.dataset.action || 'submit_verification';
            actionInput.value = action;

            // Reset form state
            typeSelect.value = '';
            fileInput.value = '';
            setFileName('');
            hideAlert();
            setLoading(false);

            // Set modal copy
            var lbl = labels[action] || labels['submit_verification'];
            modalTitle.textContent = lbl.title;
            btnText.textContent = lbl.btn;
        });
    });

    fileInput.addEventListener('change', function () {
        setFileName(this.files[0] ? this.files[0].name : '');
    });

    // Submit button.
    btnSubmit.addEventListener('click', function () {
        var action = actionInput.value;
        var type = typeSelect.value;
        var file = fileInput.files[0];

        if (!type) {
            showAlert('Please select a verification type.', 'danger');
            return;
        }
        if (!file) {
            showAlert('Please upload a supporting document.', 'danger');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            showAlert('File is too large. Maximum allowed size is 5 MB.', 'danger');
            return;
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('verification_type', type);
        formData.append('verification_document', file);
        formData.append('csrf_token', csrfInput.value);

        setLoading(true);
        hideAlert();

        fetch('../../controllers/users/VerificationController.php', {
            method: 'POST',
            body: formData,
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Server error: ' + res.status);
                return res.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (_) {
                        throw new Error('Server returned an invalid response.');
                    }
                });
            })
            .then(function (data) {
                setLoading(false);
                if (data.success) {
                    showAlert(data.message, 'success');
                    // Reload after a short delay so user sees the success message
                    setTimeout(function () {
                        verifModal.hide();
                        window.location.reload();
                    }, 1400);
                } else {
                    showAlert(data.message || 'Something went wrong. Please try again 1.', 'danger');
                }
            })
            .catch(function (err) {
                setLoading(false);
                showAlert(err.message || 'A network error occurred. Please check your connection and try again.', 'danger');
                console.error('VerificationController error:', err);
            });
    });

    // Helpers.
    function setLoading(on) {
        btnSubmit.disabled = on;
        btnSpinner.classList.toggle('d-none', !on);
        if (!on) {
            var action = actionInput.value;
            btnText.textContent = (labels[action] || labels['submit_verification']).btn;
        } else {
            btnText.textContent = 'Submitting\u2026';
        }
    }

    function showAlert(msg, type) {
        alertBox.textContent = msg;
        alertBox.className = 'u-verif-alert ' + (type === 'success' ? 'is-success' : 'is-danger');
    }

    function hideAlert() {
        alertBox.textContent = '';
        alertBox.className = 'u-verif-alert d-none';
    }

    function setFileName(name) {
        if (fileName) {
            fileName.textContent = name || 'No file selected';
        }
    }
})();
