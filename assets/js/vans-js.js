window.initVansPage = function () {

    var tbody = document.getElementById('vans-tbody');
    var countBadge = document.getElementById('van-count');
    var searchInput = document.getElementById('van-search');
    var statusFilter = document.getElementById('van-status-filter');
    var dateFrom = document.getElementById('van-date-from');
    var dateTo = document.getElementById('van-date-to');
    var dateClear = document.getElementById('van-date-clear');
    var openAddBtn = document.getElementById('open-add-modal');

    if (!tbody) return;

    /* ── Bootstrap modal helpers ──────────────── */
    function getModal(id) {
        var el = document.getElementById(id);
        return el ? new bootstrap.Modal(el) : null;
    }

    var addModal = getModal('addModal');
    var editModal = getModal('editModal');
    var seatModal = getModal('seatModal');

    /* ── Add van button ───────────────────────── */
    if (openAddBtn && addModal) {
        openAddBtn.addEventListener('click', function () { addModal.show(); });
    }

    /* ── Row count badge ──────────────────────── */
    function updateCount() {
        var rows = tbody.querySelectorAll('tr.van-row');
        var visible = 0;
        rows.forEach(function (r) {
            if (r.style.display !== 'none') visible++;
        });
        if (countBadge) {
            countBadge.textContent = visible + ' van' + (visible !== 1 ? 's' : '');
        }
        AdminUI.setClearButtonState(dateClear, filtersActive());
    }

    function filtersActive() {
        return !!((searchInput && searchInput.value.trim()) ||
            (statusFilter && statusFilter.value) ||
            (dateFrom && dateFrom.value) ||
            (dateTo && dateTo.value));
    }

    function updateGroupRows() {
        tbody.querySelectorAll('tr.admin-status-group-row').forEach(function (groupRow) {
            var key = groupRow.dataset.groupKey || '';
            var hasVisible = Array.from(tbody.querySelectorAll('tr.van-row[data-status="' + key + '"]'))
                .some(function (row) { return row.style.display !== 'none'; });
            groupRow.style.display = hasVisible ? '' : 'none';
        });
    }
    updateCount();
    updateGroupRows();

    /* ── Search + status filter ───────────────── */
    function applyFilters() {
        if (!tbody.querySelector('tr.van-row')) return;
        var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var status = statusFilter ? statusFilter.value : '';
        var from = dateFrom ? dateFrom.value : '';
        var to = dateTo ? dateTo.value : '';

        tbody.querySelectorAll('tr.van-row').forEach(function (row) {
            var matchQ = !q
                || (row.dataset.plate || '').toLowerCase().includes(q)
                || (row.dataset.model || '').toLowerCase().includes(q);
            var matchS = !status || row.dataset.status === status;
            var matchD = withinDate(row.dataset.created || '', from, to);
            row.style.display = (matchQ && matchS && matchD) ? '' : 'none';
        });
        updateCount();
        updateGroupRows();
        renderEmptyState(status, from || to);
    }

    if (searchInput) searchInput.addEventListener('input', AdminUI.debounce(applyFilters, 350));
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (dateFrom) dateFrom.addEventListener('change', applyFilters);
    if (dateTo) dateTo.addEventListener('change', applyFilters);
    if (dateClear) dateClear.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        applyFilters();
    });

    /* ── Row highlight on click ───────────────── */
    tbody.addEventListener('click', function (e) {
        if (e.target.closest('.row-actions')) return;
        var row = e.target.closest('tr.van-row');
        if (!row) return;
        tbody.querySelectorAll('tr.van-row.selected').forEach(function (r) {
            r.classList.remove('selected');
        });
        row.classList.add('selected');
    });

    document.querySelector('#addModal form').addEventListener('submit', function (e) {
        e.preventDefault();

        const form = this;
        const formData = new FormData(form);

        formData.append('csrf_token', getCsrf());

        fetch('../../controllers/Vans/AddVan.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {

                if (data.success) {

                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message
                    }).then(() => {
                        location.reload(); // or append row dynamically later
                    });

                } else {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });

                }

            })
            .catch(() => {
                Swal.fire('Error', 'Network error', 'error');
            });
    });

    /* ── Edit button ──────────────────────────── */
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.icon-btn.edit');
        if (!btn || !editModal) return;

        document.getElementById('edit-id').value = btn.dataset.id || '';
        document.getElementById('edit-plate').value = btn.dataset.plate || '';
        document.getElementById('edit-model').value = btn.dataset.model || '';
        document.getElementById('edit-capacity').value = btn.dataset.capacity || '';

        var sel = document.getElementById('edit-status');
        if (sel) sel.value = btn.dataset.status || 'active';

        editModal.show();
    });
    document.querySelector('#editModal form').addEventListener('submit', function (e) {
        e.preventDefault();

        const form = this;
        const formData = new FormData(form);
        formData.append('csrf_token', getCsrf());

        fetch('../../controllers/Vans/EditVan.php', {
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
            .catch(() => {
                Swal.fire('Error', 'Network error', 'error');
            });
    });
    /* ── Toggle status ────────────────────────── */
    // tbody.addEventListener('click', function (e) {
    //     var btn = e.target.closest('.icon-btn.toggle');
    //     if (!btn) return;

    //     var id = btn.dataset.id;
    //     var status = btn.dataset.status;
    //     var next = status === 'active' ? 'inactive' : 'active';

    //     Swal.fire({
    //         title: 'Toggle Status?',
    //         text: 'Set this van to ' + next + '?',
    //         icon: 'question',
    //         showCancelButton: true,
    //         confirmButtonText: 'Yes, toggle',
    //         confirmButtonColor: '#2e3a4d',
    //     }).then(function (res) {
    //         if (!res.isConfirmed) return;
    //         post('../../controllers/vans/ToggleVan.php', {
    //             van_id: id, csrf_token: getCsrf()
    //         }).then(function (d) {
    //             if (d.success) location.reload();
    //             else Swal.fire('Error', d.message || 'Toggle failed.', 'error');
    //         }).catch(function () {
    //             Swal.fire('Error', 'Network error.', 'error');
    //         });
    //     });
    // });

    tbody.addEventListener('click', function (e) {

        const toggleBtn = e.target.closest('.icon-btn.toggle');
        if (!toggleBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const currentStatus = toggleBtn.dataset.status;
        const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';

        const csrf = getCsrf();

        Swal.fire({
            title: 'Toggle Status?',
            text: 'Set this van to ' + nextStatus + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, toggle',
            confirmButtonColor: '#2e3a4d',
        }).then(function (res) {
            if (!res.isConfirmed) return;

            post('../../controllers/Vans/ToggleVan.php', {
                van_id: toggleBtn.dataset.id,
                status: nextStatus,
                csrf_token: csrf
            }).then(function (d) {

                if (d.success) {

                    // update UI instantly (no reload)
                    toggleBtn.dataset.status = nextStatus;

                    const row = toggleBtn.closest('tr');
                    row.dataset.status = nextStatus;
                    row.classList.remove('status-' + currentStatus);
                    row.classList.add('status-' + nextStatus);

                    const badge = row.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge ' + nextStatus;
                        badge.textContent = nextStatus.charAt(0).toUpperCase() + nextStatus.slice(1);
                    }

                    // update icon
                    const icon = toggleBtn.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-' + (nextStatus === 'active' ? 'toggle-on' : 'toggle-off');
                    }

                    AdminUI.moveRowToGroup(row, nextStatus, {
                        label: nextStatus === 'active' ? 'Active Vans' : 'Inactive Vans',
                        icon: 'fas fa-van-shuttle',
                        hint: nextStatus === 'active' ? 'Available for trips' : 'Not in service',
                        colspan: 7
                    });
                    applyFilters();
                    AdminUI.refreshGroups(tbody, 'tr.van-row', function (row) { return row.dataset.status || ''; });
                    AdminUI.notify('success', d.message || 'Van status updated successfully.');

                } else {
                    AdminUI.notify('error', d.message || 'Unable to update van status. Please try again.');
                }

            }).catch(function () {
                AdminUI.notify('error', 'Unable to update van status. Please try again.');
            });
        });
    });

    /* ── Delete button ────────────────────────── */
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.icon-btn.delete');
        if (!btn) return;

        var id = btn.dataset.id;
        var plate = btn.dataset.plate;

        Swal.fire({
            title: 'Delete ' + plate + '?',
            text: 'This will permanently remove the van and all its seats.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            confirmButtonColor: '#ef4444',
        }).then(function (res) {
            if (!res.isConfirmed) return;
            post('../../controllers/Vans/DeleteVan.php', {
                van_id: id,
                csrf_token: getCsrf()
            }).then(function (d) {

                if (d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: d.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: d.message
                    });
                }

            }).catch(function () {
                Swal.fire('Error', 'Network error.', 'error');
            });
        });
    });

    /* ── View seat layout button ──────────────── */
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.icon-btn.view');
        if (!btn || !seatModal) return;

        var plate = btn.dataset.plate || '';
        var model = btn.dataset.model || '';
        var passengerCapacity = parseInt(btn.dataset.capacity, 10) || 0;
        var seats = [];

        try { seats = JSON.parse(btn.dataset.seats || '[]'); } catch (_) { }

        var titleEl = document.getElementById('seat-modal-title');
        var subEl = document.getElementById('seat-modal-sub');
        if (titleEl) titleEl.textContent = model || 'Seat Layout';
        if (subEl) subEl.textContent = plate + ' · ' + passengerCapacity + ' passenger seats';

        renderSeatLayout(seats, passengerCapacity);
        seatModal.show();
    });

    /* ════════════════════════════════════════════
       SEAT LAYOUT RENDERER  (read-only)

       Grid: 3 columns × up to 5 rows
       Position [row=0, col=0] = DRIVER seat
       Remaining seats fill left→right, top→bottom

       DB seats array: [{ seat_number, seat_row, seat_col }, ...]
       seat_row / seat_col are 1-based from PHP.

       Mapping to grid (0-based internally):
         seat_row 1, seat_col 1 → grid row 0, col 0  (DRIVER)
         seat_row 1, seat_col 2 → grid row 0, col 1  (passenger 1)
         seat_row 1, seat_col 3 → grid row 0, col 2  (passenger 2)
         seat_row 2, seat_col 1 → grid row 1, col 0  (passenger 3)
         … and so on
    ═════════════════════════════════════════════ */
    function renderSeatLayout(seats, capacity) {
        var grid = document.getElementById('vsv-grid');
        if (!grid) return;
        grid.innerHTML = '';

        /* Build a flat ordered list of seat objects:
           index 0 = driver, index 1–13 = passengers */
        var ordered = buildOrderedSeats(seats, capacity);

        if (!ordered.length) {
            grid.innerHTML = '<p class="vsv-empty-msg">No seats configured.</p>';
            return;
        }

        /* Render 3 cols × ceil(ordered.length / 3) rows */
        var totalCells = Math.ceil(ordered.length / 3) * 3; // pad to full rows

        for (var i = 0; i < totalCells; i++) {
            var seat = ordered[i] || null;
            var el = document.createElement('div');

            if (!seat) {
                /* Empty trailing cell — keep grid shape */
                el.className = 'vsv-seat vsv-empty-slot';
            } else if (i === 0) {
                /* Driver seat */
                el.className = 'vsv-seat driver';
                el.innerHTML = '<i class="fas fa-steering-wheel"></i><span>DRIVER</span>';
                el.title = 'Driver';
            } else {
                /* Passenger seat */
                var state = seat.occupied ? 'occupied' : 'available';
                el.className = 'vsv-seat ' + state;
                el.innerHTML = '<i class="fas fa-chair"></i><span>' + esc(seat.label) + '</span>';
                el.title = 'Seat ' + seat.label;
            }

            grid.appendChild(el);
        }
    }

    /**
     * Returns a flat array of seat objects ordered by position.
     * Index 0 = driver, 1–13 = passengers in reading order.
     * Each object: { label: string, occupied: bool }
     */
    function buildOrderedSeats(seats, passengerCapacity) {

        // If DB has seat records
        if (seats && seats.length > 0) {

            var sorted = seats.slice().sort(function (a, b) {
                var rA = parseInt(a.seat_row, 10) || 0;
                var rB = parseInt(b.seat_row, 10) || 0;
                var cA = parseInt(a.seat_col, 10) || 0;
                var cB = parseInt(b.seat_col, 10) || 0;
                return rA !== rB ? rA - rB : cA - cB;
            });

            var result = [];

            // ALWAYS FIRST = DRIVER (not from DB, not counted)
            result.push({ label: 'DRIVER', occupied: false });

            sorted.forEach(function (s) {
                result.push({
                    label: (s.seat_number || '').toString(),
                    occupied: (s.status || '') === 'occupied'
                });
            });

            return result;
        }

        // Fallback generation (NO driver in capacity)
        var result = [];
        result.push({ label: 'DRIVER', occupied: false });

        var labels = generateLabels(passengerCapacity);

        labels.forEach(function (lbl) {
            result.push({ label: lbl, occupied: false });
        });

        return result;
    }
    
    function generateLabels(count) {
        var labels = [];
        for (var i = 1; i <= count; i++) labels.push(String(i));
        return labels;
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function getCsrf() {
        var el = document.getElementById('page-csrf-token');
        return el ? el.value : '';
    }

    function post(url, data) {
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        }).then(function (r) { return r.json(); });
    }

    function renderEmptyState(status, dateFiltered) {
        tbody.querySelectorAll('.js-empty-row').forEach(function (row) { row.remove(); });
        var hasVisible = Array.from(tbody.querySelectorAll('tr.van-row')).some(function (row) {
            return row.style.display !== 'none';
        });
        if (hasVisible) return;
        var label = status ? status + ' vans' : 'vans';
        var message = dateFiltered ? 'No ' + label + ' found for the selected date range.' : 'No ' + label + ' match your current filters.';
        tbody.insertAdjacentHTML('beforeend', '<tr class="js-empty-row"><td colspan="7"><div class="empty-state"><i class="fas fa-search"></i><p>' + message + '</p></div></td></tr>');
    }

    function withinDate(raw, from, to) {
        if (!from && !to) return true;
        if (!raw) return false;
        var value = String(raw).slice(0, 10);
        if (from && value < from) return false;
        if (to && value > to) return false;
        return true;
    }

};
document.addEventListener('DOMContentLoaded', window.initVansPage);
