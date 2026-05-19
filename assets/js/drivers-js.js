if (!window._ssReady) {
    window._ssReady = true;

    (function () {
        // Searchable select code (same as vans-js.js)
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
                }
            });

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

function showDriverPreview(row) {
    const driverEmpty = document.getElementById('driver-empty');
    const driverPreview = document.getElementById('driver-preview');
    if (!driverEmpty || !driverPreview) return;

    const fullname = row.dataset.fullname || '—';
    const license = row.dataset.license || '—';
    const contact = row.dataset.contact || '—';
    const email = row.dataset.email || 'No login email';
    const status = row.dataset.status || '—';

    document.getElementById('preview-name').textContent = fullname;
    document.getElementById('preview-license').textContent = license;
    document.getElementById('preview-contact').textContent = contact;
    document.getElementById('preview-email').textContent = email;
    document.getElementById('preview-status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
    document.getElementById('preview-status').className = 'detail-badge ' + status;

    document.getElementById('driver-label').textContent = fullname;

    driverEmpty.style.display = 'none';
    driverPreview.style.display = 'block';
}

function getCsrf() {
    return document.getElementById('csrf_token')?.value;
}

document.getElementById('drivers-tbody')?.addEventListener('click', function (e) {

    /* ===================== EDIT ===================== */
    const editBtn = e.target.closest('.icon-btn.edit');
    if (editBtn) {

        document.getElementById('edit-id').value = editBtn.dataset.id;
        document.getElementById('edit-fullname').value = editBtn.dataset.fullname;
        document.getElementById('edit-license').value = editBtn.dataset.license;
        document.getElementById('edit-contact').value = editBtn.dataset.contact;
        document.getElementById('edit-email').value = editBtn.dataset.email || '';

        window.syncSS(
            document.getElementById('edit-status'),
            editBtn.dataset.status
        );

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('editModal')
        ).show();

        return;
    }

    /* ===================== DELETE ===================== */
    const delBtn = e.target.closest('.icon-btn.delete');
    if (delBtn) {

        Swal.fire({
            title: 'Delete Driver?',
            text: `Driver "${delBtn.dataset.fullname}" will be permanently removed.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete'
        }).then(result => {
            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('driver_id', delBtn.dataset.id);
            formData.append('csrf_token', getCsrf());

            fetch('../../controllers/Drivers/DeleteDriver.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {

                    if (res.success) {
                        Swal.fire('Deleted!', res.message, 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }

                })
                .catch(() => {
                    Swal.fire('Error', 'Network error', 'error');
                });
        });

        return;
    }

    /* ===================== TOGGLE ===================== */
    const toggleBtn = e.target.closest('.icon-btn.toggle');
    if (toggleBtn) {

        const nextStatus =
            toggleBtn.dataset.status === 'active'
                ? 'inactive'
                : 'active';

        Swal.fire({
            title: 'Toggle Status?',
            text: 'Set this driver to ' + nextStatus + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, toggle',
            confirmButtonColor: '#2e3a4d',
        }).then(result => {
            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('driver_id', toggleBtn.dataset.id);
            formData.append('status', nextStatus);
            formData.append('csrf_token', getCsrf());

            fetch('../../controllers/Drivers/ToggleDriver.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(res => {

                    if (res.success) {
                        const row = toggleBtn.closest('tr.driver-row');
                        if (row) {
                            AdminUI.setRowStatus(row, nextStatus);
                            AdminUI.moveRowToGroup(row, nextStatus, {
                                label: nextStatus === 'active' ? 'Active Drivers' : 'Inactive Drivers',
                                icon: 'fas fa-user-tie',
                                hint: nextStatus === 'active' ? 'Available for assignment' : 'Not available',
                                colspan: 8
                            });
                            AdminUI.refreshGroups(document.getElementById('drivers-tbody'), 'tr.driver-row', row => row.dataset.status || '');
                            if (row.classList.contains('selected')) showDriverPreview(row);
                        }

                        toggleBtn.dataset.status = nextStatus;
                        const icon = toggleBtn.querySelector('i');
                        if (icon) icon.className = 'fas fa-' + (nextStatus === 'active' ? 'toggle-on' : 'toggle-off');

                        AdminUI.notify('success', res.message || 'Driver status updated successfully.');
                    } else {
                        AdminUI.notify('error', res.message || 'Unable to update driver status. Please try again.');
                    }

                })
                .catch(() => {
                    AdminUI.notify('error', 'Unable to update driver status. Please try again.');
                });
        });

        return;
    }
});
document.getElementById('editDriverForm')?.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('csrf_token', getCsrf());

    fetch('../../controllers/Drivers/EditDriver.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(res => {

            if (res.no_changes) {
                Swal.fire({
                    icon: 'info',
                    title: 'No Changes',
                    text: res.message
                });
                return;
            }

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: res.message
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.message
                });
            }

        })
        .catch(err => {
            Swal.fire('Error', 'Network error', 'error');
        });
});
document.addEventListener('DOMContentLoaded', () => {
    window.buildSearchableSelects(document);

    // Driver count badge
    const rows = document.querySelectorAll('.driver-row');
    const countEl = document.getElementById('driver-count');
    const dateFrom = document.getElementById('driver-date-from');
    const dateTo = document.getElementById('driver-date-to');
    const dateClear = document.getElementById('driver-date-clear');
    const searchInput = document.getElementById('driver-search');
    const statusFilter = document.getElementById('driver-status-filter');
    if (countEl) countEl.textContent = `${rows.length} driver${rows.length !== 1 ? 's' : ''}`;

    function updateDriverGroups() {
        document.querySelectorAll('#drivers-tbody .admin-status-group-row').forEach(groupRow => {
            const key = groupRow.dataset.groupKey || '';
            const hasVisible = Array.from(document.querySelectorAll(`#drivers-tbody .driver-row[data-status="${key}"]`))
                .some(row => row.style.display !== 'none');
            groupRow.style.display = hasVisible ? '' : 'none';
        });
    }
    updateDriverGroups();

    // Search filter — name, license, contact
    function applyDriverFilters() {
        if (!rows.length) return;
        const q = (document.getElementById('driver-search')?.value || '').toLowerCase();
        const status = statusFilter?.value || '';
        const from = dateFrom?.value || '';
        const to = dateTo?.value || '';
        rows.forEach(row => {
            const match =
                row.dataset.fullname.toLowerCase().includes(q) ||
                row.dataset.license.toLowerCase().includes(q) ||
                row.dataset.contact.toLowerCase().includes(q) ||
                (row.dataset.email || '').toLowerCase().includes(q);
            const matchStatus = !status || row.dataset.status === status;
            row.style.display = match && matchStatus && withinDate(row.dataset.created || '', from, to) ? '' : 'none';
        });
        updateDriverGroups();
        if (countEl) {
            const visible = Array.from(rows).filter(row => row.style.display !== 'none').length;
            countEl.textContent = `${visible} driver${visible !== 1 ? 's' : ''}`;
        }
        AdminUI.setClearButtonState(dateClear, !!((searchInput?.value || '').trim() || statusFilter?.value || dateFrom?.value || dateTo?.value));
        renderDriverEmptyState(from || to);
    }

    searchInput?.addEventListener('input', AdminUI.debounce(applyDriverFilters, 350));
    statusFilter?.addEventListener('change', applyDriverFilters);
    dateFrom?.addEventListener('change', applyDriverFilters);
    dateTo?.addEventListener('change', applyDriverFilters);
    dateClear?.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        applyDriverFilters();
    });
    applyDriverFilters();

    // Row click → driver preview
    rows.forEach(row => {
        row.addEventListener('click', function (e) {
            if (e.target.closest('.row-actions')) return;
            rows.forEach(r => r.classList.remove('selected'));
            this.classList.add('selected');
            showDriverPreview(this);
        });
    });

    // Add modal open
    document.getElementById('open-add-modal')?.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addModal')).show();
    });

    // Auto-uppercase license inputs
    document.querySelectorAll('input[name="license_number"]').forEach(input => {
        input.addEventListener('input', function () {
            const pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    });

    function renderDriverEmptyState(dateFiltered) {
        const tbody = document.getElementById('drivers-tbody');
        if (!tbody) return;
        tbody.querySelectorAll('.js-empty-row').forEach(row => row.remove());
        const hasVisible = Array.from(rows).some(row => row.style.display !== 'none');
        if (hasVisible) return;
        const message = dateFiltered ? 'No drivers found for the selected date range.' : 'No drivers match your current filters.';
        tbody.insertAdjacentHTML('beforeend', '<tr class="js-empty-row"><td colspan="8"><div class="empty-state"><i class="fas fa-search"></i><p>' + message + '</p></div></td></tr>');
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

