(function () {
    'use strict';

    var mascotByIcon = {
        success: 'vanny-celebrate.png',
        error: 'vanny-error.png',
        warning: 'vanny-pointing.png',
        info: 'vanny-wave.png',
        question: 'vanny-pointing.png'
    };

    var appRoot = (function () {
        var script = document.currentScript || document.querySelector('script[src$="assets/js/vanny-ui.js"]');
        if (!script) {
            return String(window.GV_BASE_URL || '').replace(/\/+$/, '');
        }

        var url = new URL(script.getAttribute('src'), window.location.href);
        return url.href.replace(/\/assets\/js\/vanny-ui\.js(?:\?.*)?$/, '');
    })();

    function baseUrl() {
        return appRoot || String(window.GV_BASE_URL || '').replace(/\/+$/, '');
    }

    function mascotUrl(icon) {
        return baseUrl() + '/images/' + (mascotByIcon[icon] || 'vanny-wave.png');
    }

    function normalizeOptions(args) {
        if (args.length === 1 && args[0] && typeof args[0] === 'object') {
            return Object.assign({}, args[0]);
        }

        return {
            title: args[0],
            text: args[1],
            icon: args[2]
        };
    }

    function withVanny(options) {
        var icon = options.icon || 'info';
        if (options.toast || !mascotByIcon[icon] || options.imageUrl || options.footer) {
            return options;
        }

        var customClass = options.customClass || {};
        if (typeof customClass === 'string') {
            customClass = { popup: customClass };
        }

        return Object.assign({}, options, {
            imageUrl: mascotUrl(icon),
            imageAlt: options.imageAlt || 'Vanny',
            imageWidth: 46,
            customClass: Object.assign({}, customClass, {
                popup: [customClass.popup, 'vanny-swal', 'vanny-swal--modal']
                    .filter(Boolean)
                    .join(' ')
            })
        });
    }

    function notify(type, message, title) {
        if (!window.Swal) {
            window.alert(message || title || 'Notice');
            return Promise.resolve();
        }

        return window.Swal.fire(withVanny({
            icon: type || 'info',
            title: title || message || 'Notice',
            text: title ? (message || '') : '',
            toast: true,
            position: 'top-right',
            timer: type === 'error' ? 4200 : 2600,
            showConfirmButton: false,
            timerProgressBar: true
        }));
    }

    window.VannyUI = {
        notify: notify,
        decorate: withVanny
    };

    if (window.Swal && !window.Swal.__vannyDecorated) {
        var originalFire = window.Swal.fire.bind(window.Swal);
        window.Swal.fire = function () {
            return originalFire(withVanny(normalizeOptions(arguments)));
        };
        window.Swal.__vannyDecorated = true;
    }
})();
