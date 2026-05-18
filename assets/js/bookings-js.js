/**
 * Bookings Admin Module  ·  bookings-js.js
 *
 * FIX LOG:
 *  - Added loading / disabled state on action buttons while request is in flight
 *  - Page reload after form submission now uses a safe absolute URL (no wrong-page redirect)
 *  - filterBookings() / updateBookingCount() are stable after DOM reload
 *  - delegateRowActions uses data-action attribute to avoid class-name ambiguity
 *  - Session flash messages (success / error) are shown via SweetAlert on load
 */

document.addEventListener('DOMContentLoaded', () => {
    initBookingsModule();
    showFlashMessage();
});

const BOOKING_PAGE_SIZE = 10;
let bookingCurrentPage = 1;

/* ═══════════════════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════════════════ */
function initBookingsModule() {
    const searchInput   = document.getElementById('booking-search');
    const filterStatus  = document.getElementById('booking-filter-status');
    const dateSelect    = document.getElementById('booking-date-select');
    const dateFrom      = document.getElementById('booking-date-from');
    const dateTo        = document.getElementById('booking-date-to');
    const dateClear     = document.getElementById('booking-date-clear');
    const statusTabs    = document.getElementById('booking-status-tabs');
    const bookingsTbody = document.getElementById('bookings-tbody');
    const detailsModalEl = document.getElementById('detailsModal');
    const detailsModal  = detailsModalEl ? new bootstrap.Modal(detailsModalEl) : null;

    const applyFromFilters = () => {
        bookingCurrentPage = 1;
        filterBookings();
        updateBookingCount();
    };
    const debouncedApply = AdminUI.debounce(applyFromFilters, 350);

    if (searchInput) searchInput.addEventListener('input', debouncedApply);

    if (filterStatus) filterStatus.addEventListener('change', applyFromFilters);
    if (dateSelect) dateSelect.addEventListener('change', applyFromFilters);
    if (dateFrom) dateFrom.addEventListener('change', applyFromFilters);
    if (dateTo) dateTo.addEventListener('change', applyFromFilters);
    if (dateClear) {
        dateClear.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (filterStatus) filterStatus.value = 'pending';
            if (dateSelect) dateSelect.value = '';
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';
            if (statusTabs) {
                statusTabs.querySelectorAll('button').forEach(tab => {
                    tab.classList.toggle('active', (tab.dataset.status || '') === 'pending');
                });
            }
            applyFromFilters();
        });
    }
    if (statusTabs) {
        statusTabs.addEventListener('click', e => {
            const btn = e.target.closest('button[data-status]');
            if (!btn) return;
            statusTabs.querySelectorAll('button').forEach(tab => tab.classList.remove('active'));
            btn.classList.add('active');
            if (filterStatus) filterStatus.value = btn.dataset.status || '';
            applyFromFilters();
        });
    }

    delegateRowActions(bookingsTbody, detailsModal);
    filterBookings();
    updateBookingCount();
}

/* ═══════════════════════════════════════════════════════════════════
   FLASH MESSAGE  (reads data attributes injected by PHP into <body>)
═══════════════════════════════════════════════════════════════════ */
function showFlashMessage() {
    const carrier = document.getElementById('page-flash');
    if (!carrier) return;

    const success = carrier.dataset.flashSuccess;
    const error   = carrier.dataset.flashError;

    if (success) {
        AdminUI.notify('success', success, 'Success');
    }

    if (error) {
        AdminUI.notify('error', error);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   FILTER + COUNT
═══════════════════════════════════════════════════════════════════ */
function filterBookings() {
    const searchInput  = document.getElementById('booking-search');
    const filterStatus = document.getElementById('booking-filter-status');
    const searchTerm   = searchInput?.value.toLowerCase().trim() || '';
    const statusFilter = filterStatus?.value || '';
    const exactDate    = document.getElementById('booking-date-select')?.value || '';
    const fromDate     = document.getElementById('booking-date-from')?.value || '';
    const toDate       = document.getElementById('booking-date-to')?.value || '';

    const rows = document.querySelectorAll('.booking-row');
    let visible = 0;

    document.querySelectorAll('.js-empty-row').forEach(row => row.remove());
    if (!rows.length) {
        renderBookingPagination(0, 1);
        return;
    }

    rows.forEach(row => {
        const refCode  = (row.dataset.refCode  || '').toLowerCase();
        const userName = (row.dataset.userName || '').toLowerCase();
        const route    = (row.dataset.route || '').toLowerCase();
        const seats    = (row.dataset.seat || '').toLowerCase();
        const payment  = (row.dataset.payment || '').toLowerCase();
        const status   = row.dataset.status || '';
        const rowDate  = String(row.dataset.created || '').slice(0, 10);

        const matchesSearch = !searchTerm ||
            refCode.includes(searchTerm) ||
            userName.includes(searchTerm) ||
            route.includes(searchTerm) ||
            seats.includes(searchTerm) ||
            payment.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        const matchesExactDate = !exactDate || rowDate === exactDate;
        const matchesDate = matchesExactDate && withinDateRange(row.dataset.created || '', fromDate, toDate);

        row.dataset.filterMatch = matchesSearch && matchesStatus && matchesDate ? '1' : '0';
    });

    const matchedRows = Array.from(rows).filter(row => row.dataset.filterMatch === '1');
    const totalPages = Math.max(1, Math.ceil(matchedRows.length / BOOKING_PAGE_SIZE));
    bookingCurrentPage = Math.min(bookingCurrentPage, totalPages);
    const start = (bookingCurrentPage - 1) * BOOKING_PAGE_SIZE;
    const end = start + BOOKING_PAGE_SIZE;

    rows.forEach(row => {
        if (row.dataset.filterMatch === '1' && matchedRows.indexOf(row) >= start && matchedRows.indexOf(row) < end) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    updateBookingGroups();

    /* empty state */
    const tbody     = document.getElementById('bookings-tbody');

    if (matchedRows.length === 0) {
        tbody.insertAdjacentHTML('beforeend', `
            <tr class="js-empty-row">
                <td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>${bookingEmptyMessage(statusFilter, !!(searchTerm || exactDate || fromDate || toDate))}</p>
                    </div>
                </td>
            </tr>`);
    }

    renderBookingPagination(matchedRows.length, totalPages);
}

function updateBookingGroups() {
    const tbody = document.getElementById('bookings-tbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr.admin-status-group-row').forEach(groupRow => {
        const key = groupRow.dataset.groupKey || '';
        const hasVisible = Array.from(tbody.querySelectorAll(`tr.booking-row[data-status="${key}"]`))
            .some(row => row.style.display !== 'none');
        groupRow.style.display = hasVisible ? '' : 'none';
    });
}

function updateBookingCount() {
    const visible   = document.querySelectorAll('.booking-row[data-filter-match="1"]').length;
    const countSpan = document.getElementById('booking-count');
    const titleEl = document.getElementById('booking-view-title');
    const status = document.getElementById('booking-filter-status')?.value || '';
    if (titleEl) titleEl.textContent = bookingViewTitle(status);
    if (countSpan) {
        countSpan.textContent = visible === 1 ? '1 booking' : `${visible} bookings`;
    }
    AdminUI.setClearButtonState(document.getElementById('booking-date-clear'), bookingFiltersActive());
}

function bookingEmptyMessage(status, filtered) {
    if (filtered) return 'No bookings match your current filters.';
    const labels = {
        pending: 'pending bookings',
        approved: 'approved bookings',
        completed: 'completed bookings',
        cancelled: 'cancelled bookings',
        rejected: 'rejected bookings'
    };
    return `No ${labels[status] || 'bookings'} found.`;
}

function bookingViewTitle(status) {
    const titles = {
        pending: 'Pending Bookings',
        approved: 'Approved Bookings',
        completed: 'Completed Bookings',
        cancelled: 'Cancelled Bookings',
        rejected: 'Rejected Bookings'
    };
    return titles[status] || 'All Bookings';
}

function bookingFiltersActive() {
    const search = document.getElementById('booking-search')?.value.trim() || '';
    const status = document.getElementById('booking-filter-status')?.value || '';
    const exact = document.getElementById('booking-date-select')?.value || '';
    const from = document.getElementById('booking-date-from')?.value || '';
    const to = document.getElementById('booking-date-to')?.value || '';
    return !!(search || (status && status !== 'pending') || exact || from || to);
}

function renderBookingPagination(total, totalPages) {
    const card = document.querySelector('.bookings-card');
    if (!card) return;

    let pager = document.getElementById('booking-pagination');
    if (!pager) {
        pager = document.createElement('div');
        pager.id = 'booking-pagination';
        pager.className = 'admin-pagination';
        card.appendChild(pager);
    }

    if (total <= BOOKING_PAGE_SIZE) {
        pager.innerHTML = '';
        pager.style.display = 'none';
        return;
    }

    pager.style.display = '';
    const from = (bookingCurrentPage - 1) * BOOKING_PAGE_SIZE + 1;
    const to = Math.min(total, bookingCurrentPage * BOOKING_PAGE_SIZE);
    pager.innerHTML = `
        <span>${from}-${to} of ${total}</span>
        <div>
            <button type="button" data-page="prev" ${bookingCurrentPage === 1 ? 'disabled' : ''}>Previous</button>
            <button type="button" data-page="next" ${bookingCurrentPage === totalPages ? 'disabled' : ''}>Next</button>
        </div>
    `;

    pager.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
            bookingCurrentPage += btn.dataset.page === 'next' ? 1 : -1;
            filterBookings();
            updateBookingCount();
        });
    });
}

/* ═══════════════════════════════════════════════════════════════════
   ROW ACTION DELEGATION
═══════════════════════════════════════════════════════════════════ */
function delegateRowActions(tbody, detailsModal) {
    if (!tbody) return;

    tbody.addEventListener('click', e => {
        const btn = e.target.closest('.icon-btn');
        if (!btn) return;

        const row = btn.closest('.booking-row');
        if (!row) return;

        if (btn.classList.contains('view'))    return showBookingDetails(row, detailsModal);
        if (btn.classList.contains('approve')) return confirmAction(btn, row, 'approved');
        if (btn.classList.contains('reject'))  return confirmAction(btn, row, 'rejected');
        if (btn.classList.contains('cancel'))  return confirmAction(btn, row, 'cancelled');
    });
}

/* ═══════════════════════════════════════════════════════════════════
   BOOKING DETAILS MODAL
═══════════════════════════════════════════════════════════════════ */
function showBookingDetails(row, modal) {
    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '—';
    };

    set('detail-ref-code',        row.dataset.refCode);
    set('detail-passenger-name',  row.dataset.userName);
    set('detail-passenger-email', row.dataset.userEmail);
    set('detail-passenger-phone', row.dataset.userPhone);
    const routeEl = document.getElementById('detail-route');
    if (routeEl) routeEl.innerHTML = routeHtml(row.dataset.route);
    const seatEl = document.getElementById('detail-seat');
    if (seatEl) seatEl.innerHTML = passengerTableFromNotes(row.dataset.notes || '', row.dataset.seat || '');
    set('detail-departure',       row.dataset.departure);
    set('detail-driver',          row.dataset.driver);
    set('detail-van',             row.dataset.van);
    set('detail-payment',
        [
            row.dataset.payment,
            row.dataset.paymentMethod,
            row.dataset.paymentAmount ? `₱${row.dataset.paymentAmount}` : '',
        ].filter(Boolean).join(' · ')
    );

    set('detail-notes', summarizeBookingNotes(row.dataset.notes || ''));

    set('detail-created',
        row.dataset.created
            ? new Date(row.dataset.created).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
            : ''
    );

    /* status badge */
    const statusEl = document.getElementById('detail-status');
    if (statusEl) {
        const status = row.dataset.status || '';
        statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        statusEl.className   = `badge ${status}`;
    }

    modal.show();
}

function passengerTableFromNotes(raw, fallbackSeats) {
    try {
        const notes = raw ? JSON.parse(raw) : {};
        if (Array.isArray(notes.passengers) && notes.passengers.length) {
            const passengerName = notes.passenger_name || notes.passengers[0].name || 'Passenger';
            const contact = notes.contact_number || '';
            const rows = notes.passengers.map(p => `
                <span class="seat-type-chip">
                    <i class="fas fa-chair"></i>
                    ${escapeHtml(p.seat_number || '-')}
                    <small>${escapeHtml(statusLabel(p.type || 'regular'))}</small>
                </span>
            `).join('');
            return `
                <div class="passenger-seat-summary">
                    <div class="passenger-seat-person">
                        <strong>${escapeHtml(passengerName)}</strong>
                        ${contact ? `<span>${escapeHtml(contact)}</span>` : ''}
                    </div>
                    <div class="seat-type-list">${rows}</div>
                </div>
            `;
        }
    } catch (_) {}

    return escapeHtml(fallbackSeats || '-');
}

function summarizeBookingNotes(raw) {
    if (!raw) return 'No booking notes available.';
    try {
        const notes = JSON.parse(raw);
        const parts = [];
        if (notes.passenger_name) parts.push(`Passenger: ${notes.passenger_name}`);
        if (notes.seats_count) parts.push(`Seats: ${notes.seats_count}`);
        if (Array.isArray(notes.passengers) && notes.passengers.length) {
            parts.push(notes.passengers.map(p => `${p.seat_number || '-'}: ${p.type || 'regular'}`).join(', '));
        }
        if (notes.cash_fee && parseFloat(notes.cash_fee) > 0) parts.push(`Cash handling fee: ₱${parseFloat(notes.cash_fee).toFixed(2)}`);
        return parts.length ? parts.join(' | ') : 'No booking notes available.';
    } catch (_) {
        return raw || 'No booking notes available.';
    }
}

function statusLabel(value) {
    return String(value || 'regular')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, ch => ch.toUpperCase());
}

function routeHtml(value) {
    const route = String(value || '').replace(/\s*→\s*/g, ' -> ');
    const parts = route.split(/\s*->\s*/);
    if (parts.length >= 2) {
        return `${escapeHtml(parts[0])} <i class="fas fa-arrow-right route-arrow-icon"></i> ${escapeHtml(parts.slice(1).join(' -> '))}`;
    }
    return escapeHtml(value || '-');
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* ═══════════════════════════════════════════════════════════════════
   CONFIRM + PERFORM ACTION
═══════════════════════════════════════════════════════════════════ */

const ACTION_CONFIG = {
    approved: {
        title:       'Approve Booking?',
        icon:        'question',
        confirmText: 'Yes, Approve',
        confirmColor:'#28a745',
    },
    rejected: {
        title:       'Reject Booking?',
        icon:        'warning',
        confirmText: 'Yes, Reject',
        confirmColor:'#dc3545',
    },
    cancelled: {
        title:       'Cancel Booking?',
        icon:        'warning',
        confirmText: 'Yes, Cancel',
        confirmColor:'#6c757d',
    },
};

function confirmAction(btn, row, newStatus) {
    const cfg     = ACTION_CONFIG[newStatus];
    const refCode = row.dataset.refCode || '';

    AdminUI.confirm({
        title:              cfg.title,
        html:               `Reference: <strong>${refCode}</strong>`,
        icon:               cfg.icon,
        confirmText:        cfg.confirmText,
        confirmColor:       cfg.confirmColor,
        cancelText:         'Go Back',
    }).then(result => {
        if (result.isConfirmed) {
            performBookingAction(btn, row, newStatus);
        }
    });
}

function performBookingAction(btn, row, newStatus) {
    /* ── Get CSRF token ── */
    const csrfToken =
        document.querySelector('#page-csrf-token')?.value ||
        document.querySelector('input[name="csrf_token"]')?.value;

    if (!csrfToken) {
        AdminUI.notify('error', 'CSRF token missing. Please refresh the page.', 'Security Error');
        return;
    }

    /* ── Loading state ── */
    setButtonLoading(btn, true);

    /* ── Build and submit form ── */
    const fields = {
        booking_id: row.dataset.id,
        status:     newStatus,
        csrf_token: csrfToken,
    };

    fetch('../../controllers/Bookings/UpdateStatus.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams(fields).toString()
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Unable to update booking status. Please try again.');
            updateBookingRowAfterStatus(row, data.status || newStatus);
            AdminUI.notify('success', data.message || 'Booking status updated successfully.');
        })
        .catch(err => {
            AdminUI.notify('error', err.message || 'Unable to update booking status. Please try again.');
        })
        .finally(() => setButtonLoading(btn, false));
}

function withinDateRange(raw, from, to) {
    if (!from && !to) return true;
    if (!raw) return false;
    const value = String(raw).slice(0, 10);
    if (from && value < from) return false;
    if (to && value > to) return false;
    return true;
}

function updateBookingRowAfterStatus(row, newStatus) {
    const tbody = document.getElementById('bookings-tbody');
    AdminUI.setRowStatus(row, newStatus);
    AdminUI.moveRowToGroup(row, newStatus, bookingGroupMeta(newStatus));

    const actions = row.querySelector('.row-actions');
    if (actions) {
        actions.querySelectorAll('.approve, .reject, .cancel').forEach(btn => btn.remove());
        if (newStatus === 'approved') {
            actions.insertAdjacentHTML('beforeend', '<button class="icon-btn cancel" title="Cancel"><i class="fas fa-ban"></i></button>');
        }
    }

    filterBookings();
    updateBookingCount();
    AdminUI.refreshGroups(tbody, 'tr.booking-row', row => row.dataset.status || '');
}

function bookingGroupMeta(status) {
    const groups = {
        pending: { label: 'Pending Bookings', icon: 'fas fa-clock', hint: 'Needs review', colspan: 8 },
        approved: { label: 'Approved Bookings', icon: 'fas fa-circle-check', hint: 'Ready for trip', colspan: 8 },
        completed: { label: 'Completed Bookings', icon: 'fas fa-flag-checkered', hint: 'Finished trips', colspan: 8 },
        rejected: { label: 'Rejected Bookings', icon: 'fas fa-circle-xmark', hint: 'Declined requests', colspan: 8 },
        cancelled: { label: 'Cancelled Bookings', icon: 'fas fa-ban', hint: 'Inactive bookings', colspan: 8 }
    };
    return groups[status] || { label: AdminUI.statusLabel(status) + ' Bookings', icon: 'fas fa-ticket-alt', hint: 'Other bookings', colspan: 8 };
}

/* ═══════════════════════════════════════════════════════════════════
   BUTTON LOADING HELPER
═══════════════════════════════════════════════════════════════════ */
function setButtonLoading(btn, loading) {
    const icon = btn.querySelector('i');

    if (loading) {
        btn.disabled = true;
        btn.style.opacity  = '0.65';
        btn.style.cursor   = 'not-allowed';
        if (icon) {
            icon.dataset.originalClass = icon.className;
            icon.className = 'fas fa-spinner fa-spin';
        }
    } else {
        btn.disabled = false;
        btn.style.opacity  = '';
        btn.style.cursor   = '';
        if (icon && icon.dataset.originalClass) {
            icon.className = icon.dataset.originalClass;
            delete icon.dataset.originalClass;
        }
    }
}
