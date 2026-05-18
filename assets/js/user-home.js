/* user-home.js — Home page specific JavaScript */

(function () {
    if (!window.buildSearchableSelects) {
        installSearchableSelects();
    }

    // Initialize searchable selects
    if (window.buildSearchableSelects) {
        window.buildSearchableSelects(document);
    }

    // Search form validation
    var searchForm = document.querySelector('.u-toolbar-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            var from = document.getElementById('from').value;
            var to = document.getElementById('to').value;
            var date = document.getElementById('date').value;

            if (!from || !to || !date) {
                e.preventDefault();
                alert('Please fill in all search fields');
                return false;
            }

            if (from === to) {
                e.preventDefault();
                alert('Origin and destination cannot be the same');
                return false;
            }
        });
    }

    // Quick action cards
    var qaCards = document.querySelectorAll('.u-qa-card');
    qaCards.forEach(function (card) {
        card.addEventListener('click', function (e) {
            // Add ripple effect or visual feedback
            this.style.transform = 'scale(0.95)';
            setTimeout(function () {
                card.style.transform = '';
            }, 150);
        });
    });

    function installSearchableSelects() {
        if (window._userHomeSSReady) return;
        window._userHomeSSReady = true;

        function closeAll() {
            document.querySelectorAll('.ss-panel.is-open').forEach(function (panel) {
                panel.classList.remove('is-open');
            });
            document.querySelectorAll('.ss-btn.is-open').forEach(function (button) {
                button.classList.remove('is-open');
            });
        }

        function buildSS(select) {
            if (select._ssBuilt) return;
            select._ssBuilt = true;

            var placeholder = select.dataset.placeholder || 'Select';
            var wrap = document.createElement('div');
            wrap.className = 'ss-wrap';
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ss-btn';

            var text = document.createElement('span');
            text.className = 'ss-btn-txt';
            var current = select.options[select.selectedIndex];
            text.textContent = current && current.value ? current.text : placeholder;
            if (!current || !current.value) btn.classList.add('is-placeholder');

            var icon = document.createElement('i');
            icon.className = 'fas fa-chevron-down ss-btn-arr';
            btn.appendChild(text);
            btn.appendChild(icon);
            wrap.insertBefore(btn, select);

            var panel = document.createElement('div');
            panel.className = 'ss-panel';

            var search = document.createElement('input');
            search.type = 'text';
            search.className = 'ss-search';
            search.placeholder = 'Type to search...';
            panel.appendChild(search);

            var list = document.createElement('ul');
            list.className = 'ss-list';
            Array.from(select.options).forEach(function (option) {
                var item = document.createElement('li');
                item.className = 'ss-item' + (!option.value ? ' is-placeholder' : '') + (option.selected && option.value ? ' is-sel' : '');
                item.dataset.val = option.value;
                item.dataset.text = option.text;
                item.textContent = option.text;
                list.appendChild(item);
            });
            panel.appendChild(list);
            wrap.insertBefore(panel, select);

            btn.addEventListener('click', function (event) {
                event.stopPropagation();
                var isOpen = panel.classList.contains('is-open');
                closeAll();
                if (!isOpen) {
                    panel.classList.add('is-open');
                    btn.classList.add('is-open');
                    search.value = '';
                    filter('');
                    setTimeout(function () { search.focus(); }, 20);
                }
            });

            search.addEventListener('input', function () {
                filter(search.value.toLowerCase());
            });
            search.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            list.addEventListener('click', function (event) {
                var item = event.target.closest('.ss-item');
                if (!item) return;
                select.value = item.dataset.val;
                text.textContent = item.dataset.val ? item.dataset.text : placeholder;
                btn.classList.toggle('is-placeholder', !item.dataset.val);
                list.querySelectorAll('.ss-item.is-sel').forEach(function (selected) {
                    selected.classList.remove('is-sel');
                });
                if (item.dataset.val) item.classList.add('is-sel');
                select.dispatchEvent(new Event('change', { bubbles: true }));
                closeAll();
            });

            function filter(query) {
                list.querySelectorAll('.ss-item').forEach(function (item) {
                    item.style.display = !query || item.dataset.text.toLowerCase().includes(query) ? '' : 'none';
                });
            }
        }

        document.addEventListener('click', closeAll);
        window.buildSearchableSelects = function (root) {
            (root || document).querySelectorAll('select.ss').forEach(buildSS);
        };
    }
})();
