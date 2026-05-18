/* ═══════════════════════════════════════════════════
   SEARCHABLE-SELECT WIDGET
   Registered once globally. buildSearchableSelects(root) can be called
   multiple times safely — it skips already-built selects.
══════════════════════════════════════════════════════════════════════════ */
if (!window._ssWidgetReady) {
    window._ssWidgetReady = true;

    (function () {

        function closeAll() {
            document.querySelectorAll('.ss-panel.is-open').forEach(function (p) {
                p.classList.remove('is-open');
            });
            document.querySelectorAll('.ss-btn.is-open').forEach(function (b) {
                b.classList.remove('is-open');
            });
        }

        function buildSS(select) {
            if (select._ssBuilt) return; // already built — skip
            select._ssBuilt = true;

            var ph   = select.dataset.placeholder || '— Select —';
            var wrap = document.createElement('div');
            wrap.className = 'ss-wrap';
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);

            /* Button */
            var btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'ss-btn';

            var txt = document.createElement('span');
            txt.className = 'ss-btn-txt';

            var cur = select.options[select.selectedIndex];
            if (cur && cur.value) {
                txt.textContent = cur.text;
            } else {
                txt.textContent = ph;
                btn.classList.add('is-placeholder');
            }
            btn.appendChild(txt);

            var arr = document.createElement('i');
            arr.className = 'fas fa-chevron-down ss-btn-arr';
            btn.appendChild(arr);
            wrap.insertBefore(btn, select);

            /* Panel */
            var panel = document.createElement('div');
            panel.className = 'ss-panel';

            var search = document.createElement('input');
            search.type        = 'text';
            search.placeholder = 'Search...';
            search.className   = 'ss-search';
            panel.appendChild(search);

            var ul = document.createElement('ul');
            ul.className = 'ss-list';
            panel.appendChild(ul);
            wrap.appendChild(panel);

            /* Options */
            Array.from(select.options).forEach(function (opt) {
                if (opt.value === '') return; // skip placeholder option
                var li         = document.createElement('li');
                li.className   = 'ss-item';
                li.dataset.val = opt.value;
                li.textContent = opt.text;
                if (select.value && select.value === opt.value) {
                    li.classList.add('is-sel');
                }
                li.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    select.value = opt.value;
                    syncSS(select, opt.value);
                    closeAll();
                });
                ul.appendChild(li);
            });

            /* Search filter */
            search.addEventListener('input', function () {
                var q = search.value.toLowerCase();
                ul.querySelectorAll('.ss-item').forEach(function (li) {
                    li.style.display = li.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });

            /* Open / close */
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var wasOpen = panel.classList.contains('is-open');
                closeAll();
                if (!wasOpen) {
                    btn.classList.add('is-open');
                    panel.classList.add('is-open');
                    search.value = '';
                    ul.querySelectorAll('.ss-item').forEach(function (li) {
                        li.style.display = '';
                    });
                    setTimeout(function () { search.focus(); }, 50);
                }
            });
        }

        /* Global helpers */
        document.addEventListener('click', closeAll);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });

        /**
         * Build SS widgets inside a root element.
         * Safe to call multiple times — skips already-built selects.
         */
        window.buildSearchableSelects = function (root) {
            (root || document).querySelectorAll('select.ss').forEach(buildSS);
        };

        /**
         * Sync the SS button text & highlighted item to a new value.
         * Call this whenever you programmatically change select.value.
         */
        window.syncSS = function (selectEl, value) {
            if (!selectEl || !selectEl._ssBuilt) return;
            selectEl.value = value;
            var wrap = selectEl.closest('.ss-wrap');
            if (!wrap) return;
            var btn  = wrap.querySelector('.ss-btn');
            var txt  = wrap.querySelector('.ss-btn-txt');
            var ul   = wrap.querySelector('.ss-list');
            var ph   = selectEl.dataset.placeholder || '— Select —';
            var opt  = selectEl.options[selectEl.selectedIndex];
            if (opt && opt.value) {
                txt.textContent = opt.text;
                btn.classList.remove('is-placeholder');
            } else {
                txt.textContent = ph;
                btn.classList.add('is-placeholder');
            }
            if (ul) {
                ul.querySelectorAll('.ss-item').forEach(function (li) {
                    li.classList.toggle('is-sel', li.dataset.val === String(value) && value !== '');
                });
            }
        };

        /* Expose close helper */
        window.closeAllSS = closeAll;

    })();
}

/* ═══════════════════════════════════════════════════
   USER NAVIGATION
══════════════════════════════════════════════════════════════════════════ */
(function () {
    /* ── Dark mode ── */
    var body = document.getElementById('userBody');
    var themeBtn = document.getElementById('themeToggle');
    var themeIcon = document.getElementById('themeIcon');

    // Persist preference and honor the early preload class to prevent a light flash.
    var saved = localStorage.getItem('gv-theme');
    var preloadedDark = document.documentElement.classList.contains('user-dark-preload');
    applyDark(saved === 'dark' || preloadedDark);
    document.documentElement.classList.remove('user-dark-preload');

    themeBtn && themeBtn.addEventListener('click', function () {
        var isDark = body.classList.contains('dark');
        applyDark(!isDark);
        localStorage.setItem('gv-theme', isDark ? 'light' : 'dark');
    });

    function applyDark(on) {
        body.classList.toggle('dark', on);
        if (themeIcon) {
            themeIcon.className = on ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
        document.querySelectorAll('[data-light-logo][data-dark-logo]').forEach(function (logoImg) {
            logoImg.src = on ? logoImg.dataset.darkLogo : logoImg.dataset.lightLogo;
        });
    }

    /* ── Profile dropdown ── */
    var chip     = document.getElementById('profileChip');
    var dropdown = document.getElementById('profileDropdown');
    var caret    = document.getElementById('profileCaret');

    chip && chip.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = dropdown.classList.toggle('open');
        caret && caret.classList.toggle('open', open);
    });

    document.addEventListener('click', function () {
        dropdown && dropdown.classList.remove('open');
        caret && caret.classList.remove('open');
    });

    initUserNotifications();

    /* ── Filter tabs (Bookings page) ── */
    document.querySelectorAll('.u-ftab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.u-ftab').forEach(function (t) {
                t.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    /* ── Build searchable selects on page load ── */
    if (window.buildSearchableSelects) {
        window.buildSearchableSelects(document);
    }

    function initUserNotifications() {
        var toggle = document.getElementById('userNotifToggle');
        var panel = document.getElementById('userNotifPanel');
        var list = document.getElementById('userNotifList');
        var dot = document.getElementById('userNotifDot');
        var summary = document.getElementById('userNotifSummary');
        var markRead = document.getElementById('userNotifMarkRead');

        if (!toggle || !panel || !list) return;

        var storageKey = 'gv-user-notif-read';
        var prefStorageKey = 'gv-user-notif-prefs';
        var prefDefaults = {
            booking: true,
            reminder: true,
            payment: true,
            verification: true,
            schedule: true
        };
        var items = [];
        var loaded = false;
        var visibleLimit = 5;

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var open = panel.classList.toggle('open');
            dropdown && dropdown.classList.remove('open');
            caret && caret.classList.remove('open');
            if (open && !loaded) loadNotifications();
        });

        panel.addEventListener('click', function (e) {
            e.stopPropagation();
            var item = e.target.closest('.u-notif-item');
            if (item && item.dataset.id) {
                var readIds = getReadIds();
                if (readIds.indexOf(item.dataset.id) === -1) {
                    readIds.push(item.dataset.id);
                    saveReadIds(readIds);
                }
            }
        });

        document.addEventListener('click', function () {
            panel.classList.remove('open');
        });

        markRead && markRead.addEventListener('click', function () {
            saveReadIds(filterByPrefs(items).map(function (item) { return item.id; }));
            renderNotifications(items);
        });

        window.addEventListener('gv-notif-prefs-updated', function () {
            renderNotifications(items);
        });

        loadNotifications();

        function loadNotifications() {
            var url = toggle.dataset.notificationUrl;
            if (!url) return;

            loaded = true;
            list.innerHTML = loadingMarkup();

            fetch(url, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        throw new Error(payload && payload.message ? payload.message : 'Unable to load notifications.');
                    }

                    items = Array.isArray(payload.data) ? payload.data : [];
                    items.sort(function (a, b) {
                        return new Date(b.time || 0) - new Date(a.time || 0);
                    });
                    renderNotifications(items);
                })
                .catch(function () {
                    list.innerHTML = emptyMarkup('fa-regular fa-bell-slash', 'Notifications are unavailable right now.');
                    if (summary) summary.textContent = 'Try again later';
                    if (dot) dot.hidden = true;
                });
        }

        function renderNotifications(data) {
            var visibleData = filterByPrefs(data);
            var readIds = getReadIds();
            var unreadCount = visibleData.filter(function (item) {
                return readIds.indexOf(item.id) === -1;
            }).length;
            var hiddenByPrefs = Math.max(0, data.length - visibleData.length);

            if (dot) dot.hidden = unreadCount === 0;
            if (markRead) markRead.disabled = unreadCount === 0;
            if (summary) {
                summary.textContent = visibleData.length
                    ? unreadCount + ' unread of ' + visibleData.length
                    : hiddenByPrefs
                        ? 'Hidden by preferences'
                    : 'No activity yet';
            }

            if (!visibleData.length) {
                list.innerHTML = emptyMarkup('fa-regular fa-bell', hiddenByPrefs ? 'Notifications hidden by preferences.' : 'No notifications yet.');
                return;
            }

            var currentGroup = '';
            var visibleItems = visibleData.slice(0, visibleLimit);
            var hiddenCount = Math.max(0, visibleData.length - visibleItems.length);

            list.innerHTML = visibleItems.map(function (item) {
                var group = groupLabel(item.time);
                var groupHtml = '';
                if (group !== currentGroup) {
                    currentGroup = group;
                    groupHtml = '<div class="u-notif-date">' + esc(group) + '</div>';
                }
                return groupHtml + itemMarkup(item, readIds.indexOf(item.id) === -1);
            }).join('') + viewMoreMarkup(hiddenCount);

            var viewMore = list.querySelector('[data-notif-view-more]');
            viewMore && viewMore.addEventListener('click', function () {
                visibleLimit += 5;
                renderNotifications(items);
            });
        }

        function filterByPrefs(data) {
            var prefs = getPrefs();
            return data.filter(function (item) {
                var type = item.type || 'booking';
                return prefs[type] !== false;
            });
        }

        function getPrefs() {
            try {
                return Object.assign({}, prefDefaults, JSON.parse(localStorage.getItem(prefStorageKey) || '{}'));
            } catch (e) {
                return prefDefaults;
            }
        }

        function itemMarkup(item, unread) {
            var title = item.title || 'Notification';
            var message = item.message || '';
            var url = item.url || '#';
            var icon = item.icon || 'fa-regular fa-bell';
            var tone = (item.tone || item.type || 'default').replace(/[^a-z0-9 _-]/gi, '');

            return '' +
                '<a class="u-notif-item ' + (unread ? 'unread' : '') + '" href="' + escAttr(url) + '" data-id="' + escAttr(item.id || '') + '">' +
                    '<span class="u-notif-icon ' + escAttr(tone) + '"><i class="' + escAttr(icon) + '"></i></span>' +
                    '<span class="u-notif-copy">' +
                        '<span class="u-notif-title">' + esc(title) + '</span>' +
                        '<span class="u-notif-msg">' + esc(message) + '</span>' +
                        '<span class="u-notif-time">' + esc(relativeTime(item.time)) + ' &middot; ' + esc(item.time_label || formatFullDate(item.time)) + '</span>' +
                    '</span>' +
                '</a>';
        }

        function getReadIds() {
            try {
                var parsed = JSON.parse(localStorage.getItem(storageKey) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function saveReadIds(ids) {
            var unique = [];
            ids.forEach(function (id) {
                if (id && unique.indexOf(id) === -1) unique.push(id);
            });
            localStorage.setItem(storageKey, JSON.stringify(unique.slice(-100)));
        }

        function groupLabel(value) {
            var date = new Date(value);
            if (isNaN(date.getTime())) return 'Older';

            var today = new Date();
            var startToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            var startDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            var diffDays = Math.round((startToday - startDate) / 86400000);

            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return date.toLocaleDateString(undefined, { weekday: 'long' });
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function relativeTime(value) {
            var date = new Date(value);
            if (isNaN(date.getTime())) return 'Just now';

            var seconds = Math.max(1, Math.floor((Date.now() - date.getTime()) / 1000));
            if (seconds < 60) return 'Just now';
            var minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + 'm ago';
            var hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + 'h ago';
            var days = Math.floor(hours / 24);
            if (days < 7) return days + 'd ago';
            return formatFullDate(value);
        }

        function formatFullDate(value) {
            var date = new Date(value);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) +
                ' ' + date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }

        function loadingMarkup() {
            return emptyMarkup('fa-solid fa-spinner fa-spin', 'Loading notifications...');
        }

        function emptyMarkup(icon, text) {
            return '<div class="u-notif-empty"><i class="' + escAttr(icon) + '"></i><p>' + esc(text) + '</p></div>';
        }

        function viewMoreMarkup(count) {
            if (count <= 0) return '';
            return '<button type="button" class="u-notif-more" data-notif-view-more>View more (' + count + ')</button>';
        }

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function escAttr(value) {
            return esc(value).replace(/`/g, '&#096;');
        }
    }
})();
