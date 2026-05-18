// Guard so rebuilding the SS system never runs twice on the page
if (!window._ssReady) {
    window._ssReady = true;

    (function () {

        function closeAll() {
            document.querySelectorAll('.ss-panel.is-open').forEach(p => p.classList.remove('is-open'));
            document.querySelectorAll('.ss-btn.is-open').forEach(b => b.classList.remove('is-open'));
        }

        function buildSS(select) {
            if (select._ssBuilt) return;
            select._ssBuilt = true;

            const ph = select.dataset.placeholder || '— Select —';
            const wrap = document.createElement('div');
            wrap.className = 'ss-wrap';
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ss-btn';

            const txt = document.createElement('span');
            txt.className = 'ss-btn-txt';
            const current = select.options[select.selectedIndex];
            if (current?.value) {
                txt.textContent = current.text;
            } else {
                txt.textContent = ph;
                btn.classList.add('is-placeholder');
            }
            btn.appendChild(txt);
            btn.appendChild(Object.assign(document.createElement('i'), {
                className: 'fas fa-chevron-down ss-btn-arr'
            }));
            wrap.insertBefore(btn, select);

            const panel = document.createElement('div');
            panel.className = 'ss-panel';

            let sinput = null;
            if (select.options.length > 5) {
                const sw = document.createElement('div');
                sw.className = 'ss-search-wrap';
                sw.appendChild(Object.assign(document.createElement('i'), { className: 'fas fa-search' }));
                sinput = Object.assign(document.createElement('input'), {
                    type: 'text', className: 'ss-search',
                    placeholder: 'Type to search…', autocomplete: 'off'
                });
                sw.appendChild(sinput);
                panel.appendChild(sw);
            }

            const ul = document.createElement('ul');
            ul.className = 'ss-list';

            const noResults = Object.assign(document.createElement('li'), {
                className: 'ss-no-results', textContent: 'No results found'
            });
            noResults.style.display = 'none';
            ul.appendChild(noResults);

            Array.from(select.options).forEach(opt => {
                const li = Object.assign(document.createElement('li'), {
                    className: 'ss-item'
                        + (!opt.value ? ' is-placeholder' : '')
                        + (opt.selected && opt.value ? ' is-sel' : ''),
                    textContent: opt.text
                });
                li.dataset.val = opt.value;
                li.dataset.text = opt.text;
                ul.appendChild(li);
            });

            panel.appendChild(ul);
            wrap.insertBefore(panel, select);

            btn.addEventListener('click', e => {
                e.stopPropagation();
                const wasOpen = panel.classList.contains('is-open');
                closeAll();
                if (!wasOpen) {
                    panel.classList.add('is-open');
                    btn.classList.add('is-open');
                    if (sinput) {
                        sinput.value = '';
                        filterList('');
                        setTimeout(() => sinput.focus(), 20);
                    }
                }
            });

            sinput?.addEventListener('input', function () { filterList(this.value.toLowerCase()); });
            sinput?.addEventListener('click', e => e.stopPropagation());

            ul.addEventListener('click', e => {
                const li = e.target.closest('.ss-item');
                if (!li) return;
                select.value = li.dataset.val;
                txt.textContent = li.dataset.val ? li.dataset.text : ph;
                btn.classList.toggle('is-placeholder', !li.dataset.val);
                ul.querySelectorAll('.ss-item.is-sel').forEach(x => x.classList.remove('is-sel'));
                if (li.dataset.val) li.classList.add('is-sel');
                closeAll();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });

            function filterList(q) {
                let hasVisible = false;
                ul.querySelectorAll('.ss-item').forEach(li => {
                    if (li.classList.contains('is-placeholder')) {
                        li.style.display = q ? 'none' : '';
                        return;
                    }
                    const match = !q || li.dataset.text.toLowerCase().includes(q);
                    li.style.display = match ? '' : 'none';
                    if (match) hasVisible = true;
                });
                noResults.style.display = (q && !hasVisible) ? '' : 'none';
            }
        }

        document.addEventListener('click', closeAll);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

        window.buildSearchableSelects = function (root) {
            (root || document).querySelectorAll('select.ss').forEach(buildSS);
        };

        window.syncSS = function (selectEl, value) {
            if (!selectEl?._ssBuilt) return;
            selectEl.value = value;
            const wrap = selectEl.closest('.ss-wrap');
            if (!wrap) return;
            const btn = wrap.querySelector('.ss-btn');
            const txt = wrap.querySelector('.ss-btn-txt');
            const ul = wrap.querySelector('.ss-list');
            const ph = selectEl.dataset.placeholder || '— Select —';
            const opt = selectEl.options[selectEl.selectedIndex];
            if (opt?.value) {
                txt.textContent = opt.text;
                btn.classList.remove('is-placeholder');
            } else {
                txt.textContent = ph;
                btn.classList.add('is-placeholder');
            }
            ul?.querySelectorAll('.ss-item').forEach(li => {
                li.classList.toggle('is-sel', li.dataset.val === value && value !== '');
            });
        };

    })();
}

const ROUTE_COORDS = {
    "Maasin City": [10.1335, 124.8460],
    "Bontoc": [10.3559, 124.9693],
    "Sogod": [10.3856, 124.9806],
    "Malitbog": [10.1581, 125.0012],
    "Padre Burgos": [10.0296, 125.0170],
    "Limasawa": [9.9303, 125.0746],
    "Liloan": [10.1581, 125.1253],
    "Macrohon": [10.0766, 124.9401],
    "San Juan": [10.2641, 125.1735],
    "Silago": [10.5284, 125.1627],
    "Hinunangan": [10.3946, 125.1985],
    "Hinundayan": [10.3511, 125.2510],
    "St. Bernard": [10.2801, 125.1383],
    "San Ricardo": [9.9130, 125.2763],
    "Tomas Oppus": [10.2548, 124.9856],
    "San Francisco": [10.0575, 125.1576],
    "Libagon": [10.2968, 125.0505],
    "Anahawan": [10.2740, 125.2578],
    "Bato": [10.3279, 124.7919],
    "Pintuyan": [9.9446, 125.2492],
};

const MAX_STOPS = 3;

let _map = null, _markers = [], _polyline = null;

function destroyMap() {
    if (_map) { _map.remove(); _map = null; _markers = []; _polyline = null; }
}

function initMap() {
    destroyMap();
    _map = L.map('route-map', { attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(_map);
    _map.setView([10.2, 124.95], 10);
}

function clearLayers() {
    _markers.forEach(m => _map.removeLayer(m));
    _markers = [];
    if (_polyline) { _map.removeLayer(_polyline); _polyline = null; }
}

function showRoute(origin, destination, stops) {
    const mapEl = document.getElementById('route-map');
    const emptyEl = document.getElementById('map-empty');
    if (!mapEl || !emptyEl) return;

    emptyEl.style.display = 'none';
    mapEl.style.display = 'block';
    if (!_map) initMap();

    setTimeout(() => {
        _map.invalidateSize();
        clearLayers();

        const mapLabel = document.getElementById('map-label');
        if (mapLabel) {
            mapLabel.innerHTML =
                `<span style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                    <span>${origin}</span>
                    <i class="fas fa-arrow-right" style="font-size:10px;opacity:.6"></i>
                    <span>${destination}</span>
                </span>`;
        }

        const originLbl = document.getElementById('map-origin-label');
        const destLbl = document.getElementById('map-dest-label');
        if (originLbl) originLbl.textContent = origin;
        if (destLbl) destLbl.textContent = destination;

        const stopsList = document.getElementById('map-stops-list');
        if (stopsList) {
            stopsList.innerHTML = stops.map(stop =>
                `<div class="map-stop">
                    <div class="stop-dot via"></div>
                    <div><div class="stop-label">Via</div><span>${stop}</span></div>
                </div>
                <div style="margin-left:4px"><div class="stop-connector"></div></div>`
            ).join('');
        }

        document.getElementById('map-route-info')?.classList.add('visible');

        const allPoints = [origin, ...stops, destination];
        const latLngs = [];
        const missing = [];

        allPoints.forEach(name => {
            if (ROUTE_COORDS[name]) latLngs.push(ROUTE_COORDS[name]);
            else missing.push(name);
        });

        if (missing.length) {
            console.warn('[Routes] No coordinates for:', missing.join(', '));
            return;
        }

        allPoints.forEach((name, i) => {
            const isFirst = i === 0;
            const isLast = i === allPoints.length - 1;
            const color = isFirst ? '#16a34a' : isLast ? '#ef4444' : '#3b82f6';
            const label = isFirst ? `<b>From:</b> ${name}` : isLast ? `<b>To:</b> ${name}` : `<b>Via:</b> ${name}`;

            const icon = L.divIcon({
                className: '',
                html: `<div style="width:13px;height:13px;background:${color};border:2.5px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.3)"></div>`,
                iconAnchor: [6, 6]
            });

            _markers.push(L.marker(latLngs[i], { icon }).addTo(_map).bindPopup(label));
        });

        _polyline = L.polyline(latLngs, {
            color: '#F97316', weight: 4, opacity: 0.85, lineJoin: 'round'
        }).addTo(_map);

        _map.fitBounds(_polyline.getBounds(), { padding: [40, 40] });
    }, 80);
}

function buildLocationOptions() {
    return '<option value="">None</option>' +
        Object.keys(ROUTE_COORDS).map(n => `<option value="${n}">${n}</option>`).join('');
}

function updateStopsUI(container, addBtn, counterEl) {
    const count = container.querySelectorAll('.stop-row').length;
    if (counterEl) counterEl.textContent = `${count} / ${MAX_STOPS}`;
    if (addBtn) {
        addBtn.disabled = count >= MAX_STOPS;
        addBtn.classList.toggle('btn-add-stop--disabled', count >= MAX_STOPS);
    }
}

function addStopRow(container, addBtn, counterEl, value = '') {
    if (container.querySelectorAll('.stop-row').length >= MAX_STOPS) return;

    const idx = container.querySelectorAll('.stop-row').length + 1;
    const row = document.createElement('div');
    row.className = 'stop-row dynamic-stop-row';
    row.innerHTML =
        `<span class="stop-num">${idx}</span>
         <select name="stops[]" class="ss stop-ss" data-placeholder="Select stop">
             ${buildLocationOptions()}
         </select>
         <button type="button" class="btn-remove-stop" title="Remove stop">
             <i class="fas fa-times"></i>
         </button>`;

    container.appendChild(row);
    window.buildSearchableSelects(row);
    if (value) window.syncSS(row.querySelector('select.ss'), value);
    updateStopsUI(container, addBtn, counterEl);

    row.querySelector('.btn-remove-stop').addEventListener('click', () => {
        row.remove();
        container.querySelectorAll('.stop-row .stop-num').forEach((el, i) => el.textContent = i + 1);
        updateStopsUI(container, addBtn, counterEl);
    });
}

function clearStops(container, addBtn, counterEl) {
    container.querySelectorAll('.stop-row').forEach(r => r.remove());
    updateStopsUI(container, addBtn, counterEl);
}


// ── AJAX Operations ──────────────────────────────────────────────────────── 

/**
 * Edit Route via AJAX
 */
function editRoute(routeId) {
    const editForm = document.getElementById('editForm');
    if (!editForm) return;

    const stopsInputs = Array.from(document.querySelectorAll('#edit-stops-container select.ss'))
        .map(select => select.value)
        .filter(val => val !== '');

    const formData = new FormData(editForm);
    formData.set('route_id', routeId);
    formData.delete('stops[]');
    stopsInputs.forEach(stop => formData.append('stops[]', stop));
    formData.append('csrf_token', getCsrf());

    fetch('../../controllers/routes/EditRoute.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.no_changes) {
                Swal.fire({
                    icon: 'info',
                    title: 'No Changes',
                    text: data.message
                });
                return;
            }

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: data.message
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(err => {
            console.error('[Routes Edit] Fetch error:', err);
            Swal.fire('Error', 'Network error. Please try again.', 'error');
        });
}

/**
 * Delete Route via AJAX
 */
function deleteRoute(routeId, routeName) {
    Swal.fire({
        title: 'Delete Route?',
        text: routeName,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete'
    }).then(result => {
        if (!result.isConfirmed) return;

        const formData = new FormData();
        formData.append('route_id', routeId);
        formData.append('csrf_token', getCsrf());

        fetch('../../controllers/routes/DeleteRoute.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(err => {
                console.error('[Routes Delete] Fetch error:', err);
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
    });
}

/**
 * Toggle Route Status via AJAX
 */
function toggleRouteStatus(routeId, currentActive) {
    const newActive = currentActive == 1 ? 0 : 1;
    const nextStatus = newActive == 1 ? 'active' : 'inactive';

    Swal.fire({
        title: 'Toggle Status?',
        text: 'Set this route to ' + nextStatus + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, toggle',
        confirmButtonColor: '#2e3a4d',
    }).then(result => {
        if (!result.isConfirmed) return;

        const formData = new FormData();
        formData.append('route_id', routeId);
        formData.append('is_active', newActive);
        formData.append('csrf_token', getCsrf());

        fetch('../../controllers/routes/ToggleRoute.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector('.route-row[data-id="' + routeId + '"]');
                    if (row) {
                        row.dataset.active = String(newActive);
                        row.dataset.status = nextStatus;
                        AdminUI.setRowStatus(row, nextStatus);
                        AdminUI.moveRowToGroup(row, nextStatus, {
                            groupKey: nextStatus,
                            label: nextStatus === 'active' ? 'Active Routes' : 'Inactive Routes',
                            icon: 'fas fa-road',
                            hint: nextStatus === 'active' ? 'Available for scheduling' : 'Hidden from scheduling',
                            colspan: 7
                        });
                        AdminUI.refreshGroups(document.querySelector('.routes-table tbody'), 'tr.route-row', row => row.dataset.active === '1' ? 'active' : 'inactive');
                    }

                    document.querySelectorAll('.icon-btn.toggle[data-id="' + routeId + '"]').forEach(btn => {
                        btn.dataset.active = String(newActive);
                        const icon = btn.querySelector('i');
                        if (icon) icon.className = 'fas fa-' + (newActive == 1 ? 'toggle-on' : 'toggle-off');
                    });

                    AdminUI.notify('success', data.message || 'Route status updated successfully.');
                } else {
                    AdminUI.notify('error', data.message || 'Unable to update route status. Please try again.');
                }
            })
            .catch(err => {
                console.error('[Routes Toggle] Fetch error:', err);
                AdminUI.notify('error', 'Unable to update route status. Please try again.');
            });
    });
}

/**
 * Get CSRF token
 */
function getCsrf() {
    const el = document.querySelector('input[name="csrf_token"]');
    return el ? el.value : '';
}


function handleActionClick(e) {

    // Edit
    const editBtn = e.target.closest('.icon-btn.edit');
    if (editBtn) {
        e.stopPropagation();
        const editContainer = document.getElementById('edit-stops-container');
        const editAddBtn = document.getElementById('edit-add-stop-btn');
        const editCounter = document.getElementById('edit-stops-counter');

        document.getElementById('edit-id').value = editBtn.dataset.id;
        window.syncSS(document.getElementById('edit-origin'), editBtn.dataset.origin);
        window.syncSS(document.getElementById('edit-destination'), editBtn.dataset.destination);
        window.syncSS(document.getElementById('edit-status'), editBtn.dataset.active);
        document.getElementById('edit-fare').value = editBtn.dataset.fare;

        clearStops(editContainer, editAddBtn, editCounter);
        (editBtn.dataset.stops ? JSON.parse(editBtn.dataset.stops) : [])
            .forEach(stop => addStopRow(editContainer, editAddBtn, editCounter, stop));

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).show();
        return;
    }

    // Delete
    const delBtn = e.target.closest('.icon-btn.delete');
    if (delBtn) {
        e.stopPropagation();
        deleteRoute(delBtn.dataset.id, delBtn.dataset.route);
        return;
    }

    // Toggle
    const toggleBtn = e.target.closest('.icon-btn.toggle');
    if (toggleBtn) {
        e.stopPropagation();
        toggleRouteStatus(toggleBtn.dataset.id, toggleBtn.dataset.active);
        return;
    }
}


// ── INIT (runs once when this script is loaded) ───────────────
document.addEventListener('DOMContentLoaded', () => {

    destroyMap();
    window.buildSearchableSelects(document);

    // Route count badge
    const rows = document.querySelectorAll('.route-row');
    const countEl = document.getElementById('route-count');
    const dateFrom = document.getElementById('route-date-from');
    const dateTo = document.getElementById('route-date-to');
    const dateClear = document.getElementById('route-date-clear');
    const searchInput = document.getElementById('route-search');
    const statusFilter = document.getElementById('route-status-filter');
    if (countEl) countEl.textContent = `${rows.length} route${rows.length !== 1 ? 's' : ''}`;

    function updateRouteGroups() {
        document.querySelectorAll('.routes-table .admin-status-group-row').forEach(groupRow => {
            const key = groupRow.dataset.groupKey || '';
            const hasVisible = Array.from(document.querySelectorAll('.routes-table .route-row'))
                .some(row => (row.dataset.active === '1' ? 'active' : 'inactive') === key && row.style.display !== 'none');
            groupRow.style.display = hasVisible ? '' : 'none';
        });
    }
    updateRouteGroups();

    // Search filter
    function applyRouteFilters() {
        if (!rows.length) return;
        const q = (document.getElementById('route-search')?.value || '').toLowerCase();
        const status = statusFilter?.value || '';
        const from = dateFrom?.value || '';
        const to = dateTo?.value || '';
        rows.forEach(row => {
            const match =
                row.dataset.origin.toLowerCase().includes(q) ||
                row.dataset.destination.toLowerCase().includes(q);
            const rowStatus = row.dataset.active === '1' ? 'active' : 'inactive';
            const matchStatus = !status || rowStatus === status;
            row.style.display = match && matchStatus && withinDate(row.dataset.created || '', from, to) ? '' : 'none';
        });
        updateRouteGroups();
        if (countEl) {
            const visible = Array.from(rows).filter(row => row.style.display !== 'none').length;
            countEl.textContent = `${visible} route${visible !== 1 ? 's' : ''}`;
        }
        AdminUI.setClearButtonState(dateClear, !!((searchInput?.value || '').trim() || statusFilter?.value || dateFrom?.value || dateTo?.value));
        renderRouteEmptyState(from || to);
    }

    searchInput?.addEventListener('input', AdminUI.debounce(applyRouteFilters, 350));
    statusFilter?.addEventListener('change', applyRouteFilters);
    dateFrom?.addEventListener('change', applyRouteFilters);
    dateTo?.addEventListener('change', applyRouteFilters);
    dateClear?.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        applyRouteFilters();
    });
    applyRouteFilters();

    // Click row → show map
    rows.forEach(row => {
        row.addEventListener('click', function (e) {
            if (e.target.closest('.row-actions')) return;
            rows.forEach(r => r.classList.remove('selected'));
            this.classList.add('selected');
            const stops = this.dataset.stops ? JSON.parse(this.dataset.stops) : [];
            showRoute(this.dataset.origin, this.dataset.destination, stops);
        });
    });

    // Action buttons (edit / delete / toggle) via event delegation
    document.getElementById('page-content')?.addEventListener('click', handleActionClick);

    // Edit form submission (AJAX)
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const routeId = document.getElementById('edit-id').value;
            if (routeId) {
                editRoute(routeId);
            }
        });
    }

    // Add modal
    const addContainer = document.getElementById('stops-container');
    const addBtn = document.getElementById('add-stop-btn');
    const addCounter = document.getElementById('add-stops-counter');

    document.getElementById('open-add-modal')?.addEventListener('click', () => {
        clearStops(addContainer, addBtn, addCounter);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addModal')).show();
    });

    addBtn?.addEventListener('click', () => addStopRow(addContainer, addBtn, addCounter));

    // Edit modal stop add button
    const editContainer = document.getElementById('edit-stops-container');
    const editAddBtn = document.getElementById('edit-add-stop-btn');
    const editCounter = document.getElementById('edit-stops-counter');

    editAddBtn?.addEventListener('click', () => addStopRow(editContainer, editAddBtn, editCounter));

    function renderRouteEmptyState(dateFiltered) {
        const tbody = document.querySelector('.routes-table tbody');
        if (!tbody) return;
        tbody.querySelectorAll('.js-empty-row').forEach(row => row.remove());
        const hasVisible = Array.from(rows).some(row => row.style.display !== 'none');
        if (hasVisible) return;
        const message = dateFiltered ? 'No routes found for the selected date range.' : 'No routes match your current filters.';
        tbody.insertAdjacentHTML('beforeend', '<tr class="js-empty-row"><td colspan="7"><div class="empty-state"><i class="fas fa-search"></i><p>' + message + '</p></div></td></tr>');
    }

    function withinDate(raw, from, to) {
        if (!from && !to) return true;
        if (!raw) return false;
        const value = String(raw).slice(0, 10);
        if (from && value < from) return false;
        if (to && value > to) return false;
        return true;
    }
});
