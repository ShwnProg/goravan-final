/**
 * schedules-js.js
 *
 * Fixed version — all logic inside window.initSchedulesPage()
 * so nav.js can call it after AJAX page swaps.
 *
 * Searchable-select (SS) widget is registered ONCE globally
 * (guarded by window._ssWidgetReady) then rebuilt per-page
 * inside initSchedulesPage.
 */

/* ══════════════════════════════════════════════════════════════════════════
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

/* ══════════════════════════════════════════════════════════════════════════
   CONSTANTS
══════════════════════════════════════════════════════════════════════════ */

var STATUS_META = {
    boarding     : { label: 'Not Departed', color: '#f97316' },
    not_departed : { label: 'Not Departed', color: '#f97316' },
    departed     : { label: 'Departed',     color: '#3b82f6' },
    arrived      : { label: 'Arrived',      color: '#22c55e' },
    completed    : { label: 'Completed',    color: '#16a34a' },
    cancelled    : { label: 'Cancelled',    color: '#ef4444' },
};

var SCHEDULE_PAGE_SIZE = 10;
var scheduleCurrentPage = 1;

/* ══════════════════════════════════════════════════════════════════════════
   PAGE INIT  —  called by nav.js after every page swap
══════════════════════════════════════════════════════════════════════════ */
window.initSchedulesPage = function () {

    /* Guard — only run on the schedules page */
    var tbody = document.getElementById('schedules-tbody');
    if (!tbody) return;

    /* ── Element refs ────────────────────────────────────────────────── */
    var searchInput = document.getElementById('schedule-search');
    var countBadge  = document.getElementById('schedule-count');
    var viewTitle   = document.getElementById('schedule-view-title');
    var dateSelect  = document.getElementById('schedule-date-select');
    var dateFrom    = document.getElementById('schedule-date-from');
    var dateTo      = document.getElementById('schedule-date-to');
    var dateClear   = document.getElementById('schedule-date-clear');
    var statusTabs  = document.getElementById('schedule-status-tabs');
    var openAddBtn  = document.getElementById('open-add-modal');
    var editForm    = document.getElementById('editForm');
    var addModalEl  = document.getElementById('addModal');
    var editModalEl = document.getElementById('editModal');
    var tableWrap   = document.querySelector('.schedules-card .schedules-table-wrap');

    if (tableWrap) {
        tableWrap.scrollLeft = 0;
    }

    /* ── Build SS widgets for this page ──────────────────────────────── */
    window.buildSearchableSelects(document);

    /* ── Bootstrap modals ────────────────────────────────────────────── */
    var addModal  = addModalEl  ? bootstrap.Modal.getOrCreateInstance(addModalEl)  : null;
    var editModal = editModalEl ? bootstrap.Modal.getOrCreateInstance(editModalEl) : null;

    /* ── Open add-modal button ───────────────────────────────────────── */
    if (openAddBtn && addModal) {
        openAddBtn.addEventListener('click', function () {
            addModal.show();
        });
    }

    /* ── Status filter ref (declared here so applyFilters can close over it) ── */
    var statusFilter = document.getElementById('schedule-status-filter');

    /* ══════════════════════════════════════════════════════════════════
       applyFilters()  —  ONE function, handles search + status together.
       Called by both the search input and the status dropdown.
    ══════════════════════════════════════════════════════════════════ */
    function applyFilters() {
        var q      = searchInput  ? searchInput.value.toLowerCase().trim() : '';
        var status = statusFilter ? statusFilter.value : '';
        var exact  = dateSelect ? dateSelect.value : '';
        var from   = dateFrom ? dateFrom.value : '';
        var to     = dateTo ? dateTo.value : '';
        var rows = Array.from(tbody.querySelectorAll('tr.schedule-row'));
        if (!rows.length) {
            updateCount();
            return;
        }

        rows.forEach(function (row) {
            /* Build searchable text from every meaningful data attribute */
            var text = (
                (row.dataset.routeDisplay || '') + ' ' +
                (row.dataset.routeVia     || '') + ' ' +
                (row.dataset.driverName   || '') + ' ' +
                (row.dataset.vanPlate     || '') + ' ' +
                (row.dataset.status       || '')
            ).toLowerCase();

            var matchSearch = !q      || text.includes(q);
            var matchStatus = !status || row.dataset.status === status;
            var filterDate = row.dataset.filterDate || row.dataset.date || '';
            var matchExactDate = !exact || filterDate === exact;
            var matchDate = matchExactDate && withinDateRange(filterDate, from, to);

            row.dataset.filterMatch = (matchSearch && matchStatus && matchDate) ? '1' : '0';
        });

        var matchedRows = rows.filter(function (row) { return row.dataset.filterMatch === '1'; });
        var totalPages = Math.max(1, Math.ceil(matchedRows.length / SCHEDULE_PAGE_SIZE));
        scheduleCurrentPage = Math.min(scheduleCurrentPage, totalPages);
        var start = (scheduleCurrentPage - 1) * SCHEDULE_PAGE_SIZE;
        var end = start + SCHEDULE_PAGE_SIZE;

        rows.forEach(function (row) {
            var index = matchedRows.indexOf(row);
            var isVisible = index >= start && index < end;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) {
                var indexCell = row.querySelector('td:first-child');
                if (indexCell) indexCell.textContent = String(index + 1);
            }
        });

        clearHiddenSelection();
        updateCount();
        renderSchedulePagination(matchedRows.length, totalPages);
        renderEmptyState(matchedRows.length, status, exact || from || to);
    }

    /* ── Count badge ─────────────────────────────────────────────────── */
    function updateCount() {
        if (!countBadge) return;
        /* :not([style*="display: none"]) misses rows hidden with display:'' reset,
           so check offsetParent instead — but simplest reliable way is to recount */
        var visible = tbody.querySelectorAll('tr.schedule-row[data-filter-match="1"]').length;
        if (viewTitle) viewTitle.textContent = scheduleViewTitle(statusFilter ? statusFilter.value : '');
        countBadge.textContent = visible + ' schedule' + (visible !== 1 ? 's' : '');
        AdminUI.setClearButtonState(dateClear, scheduleFiltersActive());
    }

    function scheduleViewTitle(status) {
        var titles = {
            not_departed: 'Not Departed',
            boarding: 'Not Departed',
            departed: 'Departed Schedules',
            arrived: 'Arrived Schedules',
            completed: 'Completed Schedules',
            cancelled: 'Cancelled Schedules'
        };
        return titles[status] || 'All Schedules';
    }

    function scheduleFiltersActive() {
        return !!((searchInput && searchInput.value.trim()) ||
            (statusFilter && statusFilter.value && statusFilter.value !== 'not_departed') ||
            (dateSelect && dateSelect.value) ||
            (dateFrom && dateFrom.value) ||
            (dateTo && dateTo.value));
    }

    function syncStatusTabs() {
        if (!statusTabs || !statusFilter) return;
        statusTabs.querySelectorAll('button[data-status]').forEach(function (tab) {
            tab.classList.toggle('active', (tab.dataset.status || '') === (statusFilter.value || ''));
        });
    }

    /* ── Wire events — both call the same applyFilters() ─────────────── */
    var debouncedApply = AdminUI.debounce(function () { scheduleCurrentPage = 1; applyFilters(); }, 350);
    if (searchInput)  searchInput.addEventListener('input', debouncedApply);
    if (statusFilter) statusFilter.addEventListener('change', function () { scheduleCurrentPage = 1; syncStatusTabs(); applyFilters(); });
    if (dateSelect) dateSelect.addEventListener('change', function () { scheduleCurrentPage = 1; applyFilters(); });
    if (dateFrom) dateFrom.addEventListener('change', function () { scheduleCurrentPage = 1; applyFilters(); });
    if (dateTo) dateTo.addEventListener('change', function () { scheduleCurrentPage = 1; applyFilters(); });
    if (dateClear) dateClear.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = 'not_departed';
        if (dateSelect) dateSelect.value = '';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        scheduleCurrentPage = 1;
        syncStatusTabs();
        clearPreview();
        applyFilters();
    });
    if (statusTabs) {
        statusTabs.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-status]');
            if (!btn) return;
            if (statusFilter) statusFilter.value = btn.dataset.status || '';
            scheduleCurrentPage = 1;
            syncStatusTabs();
            clearPreview();
            applyFilters();
        });
    }

    /* Run once on init so count is correct immediately */
    syncStatusTabs();
    applyFilters();

    /* ── Row click → highlight + preview ────────────────────────────── */
    tbody.addEventListener('click', function (e) {
        if (e.target.closest('.row-actions')) return;
        var row = e.target.closest('tr.schedule-row');
        if (!row) return;
        tbody.querySelectorAll('tr.schedule-row.selected').forEach(function (r) {
            r.classList.remove('selected');
        });
        row.classList.add('selected');
        showPreview(row);
    });

    /* ── Action buttons (delegated) ──────────────────────────────────── */
    tbody.addEventListener('click', function (e) {

        /* EDIT */
        var editBtn = e.target.closest('.icon-btn.edit');
        if (editBtn) {
            e.stopPropagation();
            var row = editBtn.closest('tr.schedule-row');
            if (!row || !editModal) return;
            populateEditModal(row);
            editModal.show();
            return;
        }

        /* DELETE */
        var delBtn = e.target.closest('.icon-btn.delete');
        if (delBtn) {
            e.stopPropagation();
            var row = delBtn.closest('tr.schedule-row');
            if (!row) return;
            deleteSchedule(row.dataset.id, row.dataset.routeDisplay || 'this schedule', row.dataset.status || '');
            return;
        }

        var cancelBtn = e.target.closest('.icon-btn.cancel-schedule');
        if (cancelBtn) {
            e.stopPropagation();
            var row = cancelBtn.closest('tr.schedule-row');
            if (!row) return;
            cancelSchedule(row.dataset.id, row.dataset.routeDisplay || 'this schedule');
            return;
        }

    });

    /* ── Edit form submit (AJAX) ─────────────────────────────────────── */
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var id = document.getElementById('edit-id').value;
            if (id) submitEdit(id);
        });
    }

    /* ── Close status dropdown on outside click ──────────────────────── */
    /* ══════════════════════════════════════════════════════════════════
       PREVIEW PANEL
    ══════════════════════════════════════════════════════════════════ */
    function showPreview(row) {
        var emptyEl   = document.getElementById('schedule-empty');
        var previewEl = document.getElementById('schedule-preview');
        var labelEl   = document.getElementById('schedule-label');
        if (!emptyEl || !previewEl) return;

        var route    = row.dataset.routeDisplay || 'N/A';
        var via      = row.dataset.routeVia || '';
        var driver   = row.dataset.driverName   || 'N/A';
        var plate    = row.dataset.vanPlate     || 'N/A';
        var model    = row.dataset.vanModel     || '';
        var capacity = row.dataset.vanCapacity  || '—';
        var date     = row.dataset.date         || '';
        var time     = row.dataset.time         || '';
        var status   = row.dataset.status       || 'not_departed';
        var meta     = STATUS_META[status] || { label: status, color: '#6b7280' };

        var departure = '—';
        if (date && time) {
            try {
                departure = new Date(date + 'T' + time).toLocaleString('en-US', {
                    month: 'short', day: 'numeric',
                    hour: 'numeric', minute: '2-digit'
                });
            } catch (_) { departure = date + ' ' + time; }
        }

        if (labelEl) labelEl.textContent = via ? route + ' · ' + via : route;

        setText('preview-route',    route);
        setText('preview-origin', row.dataset.origin || 'N/A');
        setText('preview-destination', row.dataset.destination || 'N/A');
        setText('preview-via', via || 'N/A');
        setText('preview-full-route', row.dataset.fullRoute || (via ? route + ' · ' + via : route));
        setText('preview-driver',   driver);
        setText('preview-van',      model ? plate + ' - ' + model : plate);
        setText('preview-capacity', capacity + ' seats');
        setText('preview-total-seats', row.dataset.totalSeats || capacity);
        setText('preview-available-seats', row.dataset.availableSeats || '0');
        setText('preview-booked-seats', row.dataset.bookedSeats || '0');
        setText('preview-departure', departure);
        setText('preview-eta', row.dataset.etaDisplay || 'Auto-managed');
        setText('preview-status-desc', statusDescription(status));
        setText('preview-created-at', row.dataset.createdAt || 'N/A');
        setText('preview-updated-at', row.dataset.updatedAt || 'N/A');

        var statusEl = document.getElementById('preview-status');
        if (statusEl) {
            /* Reset all status classes before setting new one */
            statusEl.className = 'detail-badge badge ' + status;
            statusEl.textContent = meta.label;
        }

        var departedRow = document.getElementById('preview-departed-row');
        var departedAt = document.getElementById('preview-departed-at');
        if (departedRow && departedAt) {
            if (row.dataset.departedAt) {
                departedAt.textContent = row.dataset.departedAt;
                departedRow.style.display = 'flex';
            } else {
                departedRow.style.display = 'none';
            }
        }

        var arrivedRow = document.getElementById('preview-arrived-row');
        var arrivedAt  = document.getElementById('preview-arrived-at');
        if (arrivedRow && arrivedAt) {
            if (row.dataset.arrivedAt) {
                arrivedAt.textContent   = row.dataset.arrivedAt;
                arrivedRow.style.display = 'flex';
            } else {
                arrivedRow.style.display = 'none';
            }
        }

        var completedRow = document.getElementById('preview-completed-row');
        var completedAt = document.getElementById('preview-completed-at');
        if (completedRow && completedAt) {
            if (row.dataset.completedAt) {
                completedAt.textContent = row.dataset.completedAt;
                completedRow.style.display = 'flex';
            } else {
                completedRow.style.display = 'none';
            }
        }

        emptyEl.style.display   = 'none';
        previewEl.style.display = 'block';
    }

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function clearPreview() {
        tbody.querySelectorAll('tr.schedule-row.selected').forEach(function (row) {
            row.classList.remove('selected');
        });
        var emptyEl = document.getElementById('schedule-empty');
        var previewEl = document.getElementById('schedule-preview');
        var labelEl = document.getElementById('schedule-label');
        if (labelEl) labelEl.textContent = 'Select a schedule';
        if (previewEl) previewEl.style.display = 'none';
        if (emptyEl) emptyEl.style.display = 'flex';
    }

    function clearHiddenSelection() {
        var selected = tbody.querySelector('tr.schedule-row.selected');
        if (selected && selected.style.display === 'none') {
            clearPreview();
        }
    }

    function statusDescription(status) {
        var descriptions = {
            boarding: 'Ready for the assigned driver to start the trip.',
            not_departed: 'Ready for the assigned driver to start the trip.',
            departed: 'The van has left the terminal and is on the road.',
            arrived: 'The trip has reached its destination.',
            completed: 'The driver has completed the trip.',
            cancelled: 'This schedule is not running.'
        };
        return descriptions[status] || 'Schedule status is being tracked.';
    }

    /* ══════════════════════════════════════════════════════════════════
       EDIT MODAL — populate + sync SS
    ══════════════════════════════════════════════════════════════════ */
    function renderSchedulePagination(total, totalPages) {
        var card = document.querySelector('.schedules-card');
        if (!card) return;

        var pager = document.getElementById('schedule-pagination');
        if (!pager) {
            pager = document.createElement('div');
            pager.id = 'schedule-pagination';
            pager.className = 'admin-pagination';
            card.appendChild(pager);
        }

        if (total <= SCHEDULE_PAGE_SIZE) {
            pager.innerHTML = '';
            pager.style.display = 'none';
            return;
        }

        var from = (scheduleCurrentPage - 1) * SCHEDULE_PAGE_SIZE + 1;
        var to = Math.min(total, scheduleCurrentPage * SCHEDULE_PAGE_SIZE);
        pager.style.display = '';
        pager.innerHTML =
            '<span>' + from + '-' + to + ' of ' + total + '</span>' +
            '<div>' +
                '<button type="button" data-page="prev" ' + (scheduleCurrentPage === 1 ? 'disabled' : '') + '>Previous</button>' +
                '<button type="button" data-page="next" ' + (scheduleCurrentPage === totalPages ? 'disabled' : '') + '>Next</button>' +
            '</div>';

        pager.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                scheduleCurrentPage += btn.dataset.page === 'next' ? 1 : -1;
                applyFilters();
            });
        });
    }

    function populateEditModal(row) {
        document.getElementById('edit-id').value   = row.dataset.id    || '';
        document.getElementById('edit-date').value = row.dataset.date  || '';
        document.getElementById('edit-time').value = row.dataset.time  || '';
        var eta = row.dataset.eta || '';
        document.getElementById('edit-eta-date').value = eta ? eta.slice(0, 10) : '';
        document.getElementById('edit-eta-time').value = eta ? eta.slice(11, 16) : '';

        /* Sync each searchable select */
        syncField('edit-route',  row.dataset.route);
        syncField('edit-driver', row.dataset.driver);
        syncField('edit-van',    row.dataset.van);
        var statusInput = document.getElementById('edit-status');
        if (statusInput) statusInput.value = row.dataset.status || '';
        setText('edit-status-label', formatStatus(row.dataset.status || 'not_departed') + ' - driver controlled');
    }

    function syncField(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        /* If SS not built yet on this element, build it first */
        if (!el._ssBuilt) window.buildSearchableSelects(editModalEl);
        window.syncSS(el, value || '');
    }

    /* ══════════════════════════════════════════════════════════════════
       AJAX — EDIT
    ══════════════════════════════════════════════════════════════════ */
    function submitEdit(scheduleId) {
        /* Build payload manually from each field.
           Avoids FormData issues with hidden SS selects
           and guarantees we only send what we intend. */
        var payload = {
            schedule_id   : scheduleId,
            route_id      : getVal('edit-route'),
            driver_id     : getVal('edit-driver'),
            van_id        : getVal('edit-van'),
            departure_date: getVal('edit-date'),
            departure_time: getVal('edit-time'),
            eta_date      : getVal('edit-eta-date'),
            eta_time      : getVal('edit-eta-time'),
            trip_status   : getVal('edit-status'),
            csrf_token    : getCsrf(),
        };

        /* Basic client-side validation */
        if (!payload.route_id || !payload.driver_id || !payload.van_id ||
            !payload.departure_date || !payload.departure_time ||
            !payload.eta_date || !payload.eta_time) {
            Swal.fire('Validation', 'Please fill in all required fields.', 'warning');
            return;
        }

        fetchPost('../../controllers/Schedules/EditSchedule.php', payload)
            .then(function (data) {
                if (data.no_changes) {
                    Swal.fire({ icon: 'info', title: 'No Changes', text: data.message });
                    return;
                }
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Saved!', text: data.message })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Save failed.' });
                }
            })
            .catch(function () {
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
    }

    /* ══════════════════════════════════════════════════════════════════
       AJAX — DELETE
    ══════════════════════════════════════════════════════════════════ */
    function deleteSchedule(scheduleId, routeLabel, status) {
        Swal.fire({
            title            : 'Delete Schedule?',
            text             : 'Delete "' + routeLabel + '"? This cannot be undone.',
            icon             : 'warning',
            showCancelButton : true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor : '#6b7280',
            confirmButtonText : 'Yes, delete it!',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            fetchPost('../../controllers/Schedules/DeleteSchedule.php', {
                schedule_id: scheduleId,
                csrf_token : getCsrf(),
            }).then(function (data) {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: data.message })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: data.title || 'Cannot delete schedule',
                        text: data.message || 'Delete failed.'
                    });
                }
            }).catch(function () {
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        });
    }

    function cancelSchedule(scheduleId, routeLabel) {
        Swal.fire({
            title            : 'Cancel Schedule?',
            text             : 'Cancel "' + routeLabel + '"? Users and the assigned driver will no longer see it as active.',
            icon             : 'warning',
            showCancelButton : true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor : '#6b7280',
            confirmButtonText : 'Yes, cancel it',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            fetchPost('../../controllers/Schedules/ToggleSchedule.php', {
                schedule_id: scheduleId,
                status: 'cancelled',
                csrf_token : getCsrf(),
            }).then(function (data) {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Cancelled!', text: data.message })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Cancel failed.' });
                }
            }).catch(function () {
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        });
    }

    /* ══════════════════════════════════════════════════════════════════
    ══════════════════════════════════════════════════════════════════ */
    function renderEmptyState(total, status, dateFiltered) {
        tbody.querySelectorAll('.js-empty-row').forEach(function (row) { row.remove(); });
        if (total > 0) return;
        var hasFilters = !!((searchInput && searchInput.value.trim()) || dateFiltered);
        var label = status ? formatStatus(status).toLowerCase() + ' schedules' : 'schedules';
        var message = hasFilters
            ? 'No schedules match your current filters.'
            : 'No ' + label + ' found.';
        tbody.insertAdjacentHTML('beforeend', '<tr class="js-empty-row"><td colspan="7"><div class="empty-state"><i class="fas fa-search"></i><p>' + message + '</p></div></td></tr>');
    }

    function withinDateRange(raw, from, to) {
        if (!from && !to) return true;
        if (!raw) return false;
        var value = String(raw).slice(0, 10);
        if (from && value < from) return false;
        if (to && value > to) return false;
        return true;
    }

    /* ══════════════════════════════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════════════════════════════ */

    /** Get value from a form field by ID */
    function getVal(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    /**
     * Get CSRF token.
     * Reads from the standalone hidden input rendered OUTSIDE any modal
     * by the bare <?= csrf_field() ?> at the bottom of the schedules wrapper.
     */
    function getCsrf() {
        /* The bare csrf_field() outside modals is the reliable one */
        var inputs = document.querySelectorAll('input[name="csrf_token"]');
        /* Last one is least likely to be inside a closed/hidden modal */
        if (inputs.length) return inputs[inputs.length - 1].value;
        return '';
    }

    /** POST with application/x-www-form-urlencoded and return parsed JSON */
    function fetchPost(url, data) {
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k] || '');
        }).join('&');
        return fetch(url, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : body,
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    /** Nicely format raw status key → display label */
    function formatStatus(status) {
        var meta = STATUS_META[status];
        if (meta) return meta.label;
        return status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ');
    }

    function formatDateTime(date) {
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var hours = date.getHours();
        var minutes = String(date.getMinutes()).padStart(2, '0');
        var period = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return months[date.getMonth()] + ' ' +
            String(date.getDate()).padStart(2, '0') + ', ' +
            date.getFullYear() + ' ' +
            hours + ':' + minutes + ' ' + period;
    }

}; // end initSchedulesPage

/* Auto-init for direct (non-AJAX) page loads */
document.addEventListener('DOMContentLoaded', window.initSchedulesPage);
