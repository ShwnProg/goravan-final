(function () {
    'use strict';

    var PASSWORD_SELECTOR = 'input[name="new_password"], input[name="password"]';
    var CONFIRM_SELECTOR = 'input[name="confirm_password"]';

    function getPasswordStrength(value) {
        var password = value || '';
        var hasUpper = /[A-Z]/.test(password);
        var hasLower = /[a-z]/.test(password);
        var hasNumber = /\d/.test(password);
        var hasSpecial = /[^A-Za-z0-9]/.test(password);
        var variety = [hasUpper, hasLower, hasNumber, hasSpecial].filter(Boolean).length;

        if (password.length < 8 || variety < 2) {
            return {
                level: 'weak',
                label: 'Weak password',
                valid: false
            };
        }

        if (hasUpper && hasLower && hasNumber && hasSpecial) {
            return {
                level: 'strong',
                label: 'Strong password',
                valid: true
            };
        }

        return {
            level: 'medium',
            label: 'Medium password',
            valid: true
        };
    }

    function findPasswordInput(form) {
        var inputs = form.querySelectorAll(PASSWORD_SELECTOR);
        for (var i = 0; i < inputs.length; i += 1) {
            if (inputs[i].name !== 'current_password') {
                return inputs[i];
            }
        }
        return null;
    }

    function getInsertTarget(input) {
        var wrapper = input.closest('.password-wrapper');
        return wrapper || input;
    }

    function createStrengthMeter(input) {
        var existing = input.parentNode.querySelector('.gv-password-meter');
        if (existing) return existing;

        var meter = document.createElement('div');
        meter.className = 'gv-password-meter';
        meter.setAttribute('aria-live', 'polite');
        meter.innerHTML = '<span class="gv-password-bar"><span></span></span><small></small>';

        var target = getInsertTarget(input);
        target.insertAdjacentElement('afterend', meter);
        return meter;
    }

    function createMatchFeedback(input) {
        var existing = input.parentNode.querySelector('.gv-password-match');
        if (existing) return existing;

        var feedback = document.createElement('div');
        feedback.className = 'gv-password-match';
        feedback.setAttribute('aria-live', 'polite');

        var target = getInsertTarget(input);
        target.insertAdjacentElement('afterend', feedback);
        return feedback;
    }

    function clearState(input) {
        input.classList.remove(
            'gv-password-weak',
            'gv-password-medium',
            'gv-password-strong',
            'gv-password-match',
            'gv-password-mismatch'
        );
    }

    function updateStrength(input, meter, forceMessage) {
        var value = input.value || '';
        var result = getPasswordStrength(value);
        var text = meter.querySelector('small');

        meter.classList.remove('is-empty', 'is-weak', 'is-medium', 'is-strong');
        clearState(input);

        if (!value && !forceMessage) {
            meter.classList.add('is-empty');
            text.textContent = '';
            return result;
        }

        meter.classList.add('is-' + result.level);
        input.classList.add('gv-password-' + result.level);
        text.textContent = result.label;
        return result;
    }

    function updateMatch(passwordInput, confirmInput, feedback, forceMessage) {
        var password = passwordInput.value || '';
        var confirm = confirmInput.value || '';
        var hasBoth = password !== '' && confirm !== '';
        var matches = hasBoth && password === confirm;

        confirmInput.classList.remove('gv-password-match', 'gv-password-mismatch');
        feedback.classList.remove('is-empty', 'is-match', 'is-mismatch');

        if (!confirm && !forceMessage) {
            feedback.classList.add('is-empty');
            feedback.textContent = '';
            return true;
        }

        if (matches) {
            confirmInput.classList.add('gv-password-match');
            feedback.classList.add('is-match');
            feedback.textContent = 'Passwords match';
            return true;
        }

        confirmInput.classList.add('gv-password-mismatch');
        feedback.classList.add('is-mismatch');
        feedback.textContent = 'Passwords do not match';
        return false;
    }

    function initPasswordForm(form) {
        if (form.dataset.gvPasswordReady === '1') return;

        var passwordInput = findPasswordInput(form);
        var confirmInput = form.querySelector(CONFIRM_SELECTOR);
        if (!passwordInput || !confirmInput) return;

        form.dataset.gvPasswordReady = '1';

        var meter = createStrengthMeter(passwordInput);
        var matchFeedback = createMatchFeedback(confirmInput);

        function refresh(forceMessage) {
            var strength = updateStrength(passwordInput, meter, forceMessage);
            var matches = updateMatch(passwordInput, confirmInput, matchFeedback, forceMessage);
            return strength.valid && matches && passwordInput.value.length >= 8;
        }

        passwordInput.addEventListener('input', refresh);
        confirmInput.addEventListener('input', refresh);
        passwordInput.addEventListener('blur', refresh);
        confirmInput.addEventListener('blur', refresh);

        form.addEventListener('submit', function (event) {
            if (refresh(true)) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            if (!passwordInput.value || !getPasswordStrength(passwordInput.value).valid) {
                passwordInput.focus();
            } else {
                confirmInput.focus();
            }
        }, true);

        refresh();
    }

    function initPasswordStrength(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('form');
        forms.forEach(initPasswordForm);
    }

    window.GoraVanPasswordStrength = {
        init: initPasswordStrength,
        check: getPasswordStrength
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initPasswordStrength(document);
        });
    } else {
        initPasswordStrength(document);
    }
})();
