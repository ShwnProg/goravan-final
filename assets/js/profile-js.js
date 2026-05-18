/* settings.js */
(function () {
    'use strict';

    // Feedback helper
    function showFeedback(el, type, message) {
    if (!el) return;
    el.className = 'settings-feedback show ' + type;
    el.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    clearTimeout(el._timer);
    el._timer = setTimeout(function () {
        el.classList.remove('show');
    }, 4000);
}

// Profile form
function initProfileForm() {
    var form = document.getElementById('form-profile');
    if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn      = form.querySelector('[data-submit]');
            var feedback = document.getElementById('profile-feedback');

            if (btn) btn.classList.add('loading');

            var data = {
                action:         'update_profile',
                first_name:     (form.querySelector('[name="first_name"]') || {}).value || '',
                last_name:      (form.querySelector('[name="last_name"]') || {}).value || '',
                email:          (form.querySelector('[name="email"]') || {}).value || '',
                contact_number: (form.querySelector('[name="contact_number"]') || {}).value || '',
                csrf_token:     (form.querySelector('[name="csrf_token"]') || {}).value || ''
            };

            fetch('../../controllers/SettingsController.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(data)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                showFeedback(feedback, res.success ? 'success' : 'error', res.message);
            })
            .catch(function () {
                showFeedback(feedback, 'error', 'Something went wrong. Please try again.');
            })
            .finally(function () {
                if (btn) btn.classList.remove('loading');
            });
        });
    }

    function initPasswordForm() {
        var form = document.getElementById('form-password');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn      = form.querySelector('[data-submit]');
            var feedback = document.getElementById('password-feedback');

            var newPass     = (form.querySelector('[name="new_password"]') || {}).value || '';
            var confirmPass = (form.querySelector('[name="confirm_password"]') || {}).value || '';

            if (newPass !== confirmPass) {
                showFeedback(feedback, 'error', 'New passwords do not match.');
                return;
            }

            if (newPass.length < 8) {
                showFeedback(feedback, 'error', 'Password must be at least 8 characters.');
                return;
            }

            if (btn) btn.classList.add('loading');

            var data = {
                action:           'change_password',
                current_password: (form.querySelector('[name="current_password"]') || {}).value || '',
                new_password:     newPass,
                confirm_password: confirmPass,
                csrf_token:       (form.querySelector('[name="csrf_token"]') || {}).value || ''
            };

            fetch('../../controllers/SettingsController.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(data)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                showFeedback(feedback, res.success ? 'success' : 'error', res.message);
                if (res.success) form.reset();
            })
            .catch(function () {
                showFeedback(feedback, 'error', 'Something went wrong. Please try again.');
            })
            .finally(function () {
                if (btn) btn.classList.remove('loading');
            });
        });
    }

    function initPasswordToggles() {
        var toggles = document.querySelectorAll('.password-toggle');
        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var wrapper = toggle.closest('.password-wrapper');
                if (!wrapper) return;

                var input = wrapper.querySelector('input[type="password"], input[type="text"]');
                if (!input) return;

                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                toggle.innerHTML = '<i class="fas fa-' + (isPassword ? 'eye-slash' : 'eye') + '"></i>';
            });
        });
    }

    window.initSettingsPage = function () {
        initProfileForm();
        initPasswordForm();
        initPasswordToggles();
        // Dark mode toggle on settings page is wired in nav.js automatically
    };

})();