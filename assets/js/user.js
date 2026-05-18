﻿(function () {
    'use strict';

    function initPasswordToggles() {
        var toggles = document.querySelectorAll('.password-toggle');
        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var wrapper = toggle.closest('.password-wrapper');
                if (!wrapper) return;

                var input = wrapper.querySelector('input[type=\'password\'], input[type=\'text\']');
                if (!input) return;

                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                toggle.innerHTML = '<i class="fas fa-' + (isPassword ? 'eye-slash' : 'eye') + '"></i>';
            });
        });
    }

    window.initAuthPage = function () {
        initPasswordToggles();
    };
})();
