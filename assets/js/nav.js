// nav.js - Admin shell UI


document.addEventListener('DOMContentLoaded', function () {

    var isDark = localStorage.getItem('admin_theme') === 'dark';

    // Immediately sync page-content on every page load
    var pageContent = document.getElementById('page-content');
    if (pageContent && isDark) {
        pageContent.classList.add('admin-dark-mode');
    }

// Element refs
var sidebar   = document.getElementById('sidebar');
var overlay   = document.getElementById('sidebar-overlay');
var burgerBtn = document.getElementById('burger-btn');

    var notifToggle   = document.getElementById('notif-toggle');
    var notifDropdown = document.getElementById('notif-dropdown');
    var notifDot      = document.getElementById('notif-dot');

    var profileToggle   = document.getElementById('profile-toggle');
    var profileDropdown = document.getElementById('profile-dropdown');
    var profileCaret    = document.getElementById('profile-caret');

    var darkToggleBtn = document.getElementById('topbar-dark-toggle');


// Sidebar functions
function openSidebar() {
    sidebar   && sidebar.classList.add('open');
    overlay   && overlay.classList.add('active');
    burgerBtn && burgerBtn.classList.add('open');
}

    function closeSidebar() {
        sidebar   && sidebar.classList.remove('open');
        overlay   && overlay.classList.remove('active');
        burgerBtn && burgerBtn.classList.remove('open');
    }

    if (burgerBtn) {
        burgerBtn.addEventListener('click', function () {
            sidebar && sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
    }

    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('.menu-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });


    var currentPath = window.location.pathname.replace(/\/$/, '').toLowerCase();

    document.querySelectorAll('.menu-btn[href]').forEach(function (btn) {
        var linkPath = btn.getAttribute('href').replace(/\/$/, '').toLowerCase();
        if (currentPath.endsWith(linkPath)) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });


    if (notifToggle) {
        notifToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown && profileDropdown.classList.remove('open');
            profileCaret    && profileCaret.classList.remove('open');
            notifDropdown   && notifDropdown.classList.toggle('open');
        });
    }

    if (notifDropdown) {
        notifDropdown.addEventListener('click', function (e) { e.stopPropagation(); });
    }


    if (profileToggle) {
        profileToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown   && notifDropdown.classList.remove('open');
            profileDropdown && profileDropdown.classList.toggle('open');
            profileCaret    && profileCaret.classList.toggle('open');
        });
    }

    if (profileDropdown) {
        profileDropdown.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    document.addEventListener('click', function () {
        notifDropdown   && notifDropdown.classList.remove('open');
        profileDropdown && profileDropdown.classList.remove('open');
        profileCaret    && profileCaret.classList.remove('open');
    });


// Dark mode engine
    var THEME_KEY = 'admin_theme';
    var DARK_VAL  = 'dark';

    function applyTheme(dark) {
        var pc = document.getElementById('page-content');

        // 1. Body class → sidebar + topbar
        document.body.classList.toggle('admin-dark-mode-active', dark);
        document.body.classList.toggle('admin-light-mode-active', !dark);
        document.body.style.backgroundColor = dark ? '#0f172a' : '#f8fafc';
        document.documentElement.classList.toggle('admin-dark-mode-active', dark);
        document.documentElement.classList.toggle('admin-light-mode-active', !dark);
        document.documentElement.classList.toggle('dark-init', dark);
        document.documentElement.classList.toggle('light-init', !dark);
        document.documentElement.dataset.adminTheme = dark ? 'dark' : 'light';

        // 2. Page content class → all page views
        if (pc) pc.classList.toggle('admin-dark-mode', dark);

        // 3. Topbar icon swap (moon ↔ sun)
        if (darkToggleBtn) {
            var icon = darkToggleBtn.querySelector('i');
            if (icon) icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
            darkToggleBtn.title = dark ? 'Light Mode' : 'Dark Mode';
        }

        // 4. Settings page checkbox sync
        var settingsToggle = document.getElementById('dark-mode-toggle');
        if (settingsToggle) settingsToggle.checked = dark;

        // 5. Logo: white in dark mode
        var logo = document.querySelector('.sidebar .logo img');
        if (logo) logo.style.filter = dark ? 'brightness(0) invert(1)' : '';
    }

    function initDarkMode() {
        // Sync icon + checkbox + logo (page-content class already applied above)
        applyTheme(isDark);

        // Topbar moon/sun button
        if (darkToggleBtn) {
            darkToggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var nowDark = !document.body.classList.contains('admin-dark-mode-active');
                localStorage.setItem(THEME_KEY, nowDark ? DARK_VAL : 'light');
                applyTheme(nowDark);
            });
        }

        // Settings page checkbox
        var settingsToggle = document.getElementById('dark-mode-toggle');
        if (settingsToggle) {
            settingsToggle.addEventListener('change', function () {
                var dark = this.checked;
                localStorage.setItem(THEME_KEY, dark ? DARK_VAL : 'light');
                applyTheme(dark);
            });
        }
    }

    initDarkMode();

    // Global escape hatch
    window.adminApplyTheme = applyTheme;

});
