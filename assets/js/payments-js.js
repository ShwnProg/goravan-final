window.initPaymentsPage = function () {

    var tbody        = document.getElementById('payments-tbody');
    var countBadge   = document.getElementById('payment-count');
    var searchInput  = document.getElementById('payment-search');
    var statusFilter = document.getElementById('payment-status-filter');
    var methodFilter = document.getElementById('payment-method-filter');
    var dateFrom     = document.getElementById('payment-date-from');
    var dateTo       = document.getElementById('payment-date-to');
    var dateClear    = document.getElementById('payment-date-clear');
    var statusTabs   = document.getElementById('payment-status-tabs');
    var viewTitle    = document.getElementById('payment-view-title');

    if (!tbody) return;

    var viewModal = _modal('viewModal');
    var refundReviewModal = _modal('refundReviewModal');

    /* ── Count badge ─────────────────────────── */
    function updateCount() {
        var visible = Array.prototype.filter.call(tbody.querySelectorAll('tr.payment-row'), function (row) {
            return row.style.display !== 'none';
        }).length;
        if (viewTitle) viewTitle.textContent = paymentViewTitle(statusFilter ? statusFilter.value : '');
        if (countBadge) {
            countBadge.textContent = visible + ' payment' + (visible !== 1 ? 's' : '');
        }
        AdminUI.setClearButtonState(dateClear, filtersActive());
    }

    function paymentViewTitle(status) {
        var titles = {
            pending: 'Pending Payments',
            pending_cash: 'Cash Pending Payments',
            cash_unpaid: 'Cash Pending Payments',
            unpaid: 'Unpaid Payments',
            failed: 'Failed Payments',
            paid: 'Paid Payments',
            refund_requested: 'Refund Requests',
            refunded: 'Refunded Payments',
            cancelled: 'Cancelled Payments',
            rejected: 'Rejected Payments'
        };
        return titles[status] || 'All Payments';
    }

    function filtersActive() {
        return !!((searchInput && searchInput.value.trim()) ||
            (statusFilter && statusFilter.value && statusFilter.value !== 'pending') ||
            (methodFilter && methodFilter.value) ||
            (dateFrom && dateFrom.value) ||
            (dateTo && dateTo.value));
    }

    function updateGroupRows() {
        tbody.querySelectorAll('tr.payment-group-row').forEach(function (groupRow) {
            var key = groupRow.dataset.groupKey || '';
            var hasVisibleRows = false;

            tbody.querySelectorAll('tr.payment-row').forEach(function (row) {
                if ((row.dataset.groupKey || '') === key && row.style.display !== 'none') {
                    hasVisibleRows = true;
                }
            });

            groupRow.style.display = hasVisibleRows ? '' : 'none';
        });
    }

    /* ── Search + status filter ──────────────── */
    function applyFilters() {
        var q      = searchInput  ? searchInput.value.toLowerCase().trim() : '';
        var status = statusFilter ? statusFilter.value : '';
        var method = methodFilter ? methodFilter.value : '';
        var from   = dateFrom ? dateFrom.value : '';
        var to     = dateTo ? dateTo.value : '';
        if (!tbody.querySelector('tr.payment-row')) {
            updateCount();
            return;
        }

        tbody.querySelectorAll('tr.payment-row').forEach(function (row) {
            var matchQ = !q
                || (row.dataset.bookingRef || '').toLowerCase().includes(q)
                || (row.dataset.userName   || '').toLowerCase().includes(q)
                || (row.dataset.userEmail  || '').toLowerCase().includes(q)
                || (row.dataset.ref        || '').toLowerCase().includes(q);
            var matchS = !status || (row.dataset.status || '') === status;
            var matchM = !method || (row.dataset.method || '').toLowerCase() === method;
            var dateValue = row.dataset.filterDate || row.dataset.paidAt || row.dataset.created || '';
            var matchD = _withinDate(dateValue, from, to);
            row.style.display = matchQ && matchS && matchM && matchD ? '' : 'none';
        });
        updateGroupRows();
        updateCount();
        renderEmptyState(status, from || to);
    }

    var debouncedApply = AdminUI.debounce(applyFilters, 350);
    if (searchInput)  searchInput.addEventListener('input', debouncedApply);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (methodFilter) methodFilter.addEventListener('change', applyFilters);
    if (dateFrom) dateFrom.addEventListener('change', applyFilters);
    if (dateTo) dateTo.addEventListener('change', applyFilters);
    if (dateClear) dateClear.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = 'pending';
        if (methodFilter) methodFilter.value = '';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        if (statusTabs) {
            statusTabs.querySelectorAll('button').forEach(function (tab) {
                tab.classList.toggle('active', (tab.dataset.status || '') === 'pending');
            });
        }
        applyFilters();
    });
    if (statusTabs) {
        statusTabs.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-status]');
            if (!btn) return;
            statusTabs.querySelectorAll('button').forEach(function (tab) { tab.classList.remove('active'); });
            btn.classList.add('active');
            if (statusFilter) statusFilter.value = btn.dataset.status || '';
            applyFilters();
        });
    }
    applyFilters();

    /* ── Row highlight ───────────────────────── */
    tbody.addEventListener('click', function (e) {
        if (e.target.closest('.row-actions')) return;
        var row = e.target.closest('tr.payment-row');
        if (!row) return;
        tbody.querySelectorAll('tr.payment-row.selected').forEach(function (r) {
            r.classList.remove('selected');
        });
        row.classList.add('selected');
    });

    /* ── VIEW: open read-only modal ──────────── */
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.icon-btn.view');
        if (!btn || !viewModal) return;
        e.stopPropagation();

        var status = btn.dataset.status || 'paid';

        document.getElementById('view-booking-ref').textContent  = btn.dataset.bookingRef || '—';
        document.getElementById('view-route').textContent        = btn.dataset.route       || '—';
        document.getElementById('view-user-name').textContent    = btn.dataset.userName   || '—';
        document.getElementById('view-user-email').textContent   = btn.dataset.userEmail  || '—';
        document.getElementById('view-user-phone').textContent   = btn.dataset.userPhone  || 'N/A';
        document.getElementById('view-amount').textContent       = '₱ ' + parseFloat(btn.dataset.amount || 0).toFixed(2);
        document.getElementById('view-method').textContent       = _ucFirst(btn.dataset.method || '—');
        document.getElementById('view-payment-ref').textContent  = btn.dataset.ref        || '—';
        document.getElementById('view-notes').textContent        = _notesSummary(btn.dataset.notes || '');

        var statusEl = document.getElementById('view-status-badge');
        statusEl.textContent = _statusLabel(status);
        statusEl.className   = 'badge ' + status;

        document.getElementById('view-created').textContent  = _formatDate(btn.dataset.created);
        document.getElementById('view-paid-at').textContent  = btn.dataset.paidAt ? _formatDate(btn.dataset.paidAt) : '—';

        viewModal.show();
    });

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.icon-btn.refund-review');
        if (!btn || !refundReviewModal) return;
        e.stopPropagation();

        document.getElementById('refund-review-payment-id').value = btn.dataset.id || '';
        document.getElementById('refund-review-sub').textContent =
            (btn.dataset.bookingRef || 'Booking') + ' · ₱ ' + parseFloat(btn.dataset.amount || 0).toFixed(2);
        document.getElementById('refund-review-decision').value = 'approve';
        document.getElementById('refund-review-reason').value = 'valid_request';
        document.getElementById('refund-review-custom').value = '';
        document.getElementById('refund-request-summary').innerHTML = _refundSummaryHtml(btn.dataset.notes || '');

        refundReviewModal.show();
    });

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.icon-btn.paid');
        if (!btn) return;
        e.stopPropagation();

        AdminUI.confirm({
            title: 'Mark payment as paid?',
            text: (btn.dataset.method || '').toLowerCase() === 'cash'
                ? 'Confirm that cash was received for this booking.'
                : 'Confirm that this payment has been received.',
            icon: 'question',
            confirmText: 'Mark paid',
            confirmColor: '#16a34a'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var fd = new FormData();
            fd.append('csrf_token', (document.getElementById('page-csrf-token') || {}).value || '');
            fd.append('payment_id', btn.dataset.id || '');
            fd.append('status', 'paid');

            btn.disabled = true;
            fetch('../../controllers/PaymentsController.php?action=update_status', {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            })
                .then(parseJsonResponse)
                .then(function (data) {
                    if (!data.success) throw new Error(data.message || 'Unable to update payment.');
                    updatePaymentRowAfterReview(btn.dataset.id || '', 'paid');
                    AdminUI.notify('success', data.message || 'Payment marked as paid.');
                })
                .catch(function (err) {
                    AdminUI.notify('error', err.message || 'Unable to update payment.');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    });

    var submitRefundReview = document.getElementById('submit-refund-review');
    if (submitRefundReview) {
        submitRefundReview.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('csrf_token', (document.getElementById('page-csrf-token') || {}).value || '');
            fd.append('payment_id', (document.getElementById('refund-review-payment-id') || {}).value || '');
            fd.append('decision', (document.getElementById('refund-review-decision') || {}).value || '');
            fd.append('reason', (document.getElementById('refund-review-reason') || {}).value || '');
            fd.append('custom_note', (document.getElementById('refund-review-custom') || {}).value || '');

            submitRefundReview.disabled = true;
            fetch('../../controllers/PaymentsController.php?action=review_refund', {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            })
                .then(parseJsonResponse)
                .then(function (data) {
                    if (!data.success) throw new Error(data.message || 'Unable to review refund.');
                    updatePaymentRowAfterReview((document.getElementById('refund-review-payment-id') || {}).value || '', data.status || 'paid');
                    if (refundReviewModal) refundReviewModal.hide();
                    AdminUI.notify('success', data.message || 'Refund review saved successfully.');
                })
                .catch(function (err) {
                    if (window.Swal) {
                        AdminUI.notify('error', err.message || 'Unable to review refund. Please try again.', 'Refund Review Failed');
                    } else {
                        alert(err.message);
                    }
                })
                .finally(function () {
                    submitRefundReview.disabled = false;
                });
        });
    }

    /* ── Helpers ─────────────────────────────── */
    function _modal(id) {
        var el = document.getElementById(id);
        return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    function renderEmptyState(status, dateFiltered) {
        tbody.querySelectorAll('.js-empty-row').forEach(function (row) { row.remove(); });
        var hasVisible = Array.prototype.some.call(tbody.querySelectorAll('tr.payment-row'), function (row) {
            return row.style.display !== 'none';
        });
        if (hasVisible) return;

        var label = status ? paymentViewTitle(status).toLowerCase() : 'payments';
        var message = dateFiltered
            ? 'No ' + label + ' found for the selected date range.'
            : 'No ' + label + ' available.';
        tbody.insertAdjacentHTML('beforeend', '<tr class="js-empty-row"><td colspan="9"><div class="empty-state"><i class="fas fa-search"></i><p>' + message + '</p></div></td></tr>');
    }

    function _withinDate(raw, from, to) {
        if (!from && !to) return true;
        if (!raw) return false;
        var value = String(raw).slice(0, 10);
        if (from && value < from) return false;
        if (to && value > to) return false;
        return true;
    }

    function updatePaymentRowAfterReview(paymentId, status) {
        var row = tbody.querySelector('tr.payment-row[data-id="' + paymentId + '"]');
        if (!row) return;

        row.dataset.status = status;
        row.dataset.paymentStatus = status;
        row.dataset.groupKey = paymentGroupMeta(status).groupKey || status;
        AdminUI.setRowStatus(row, status);
        AdminUI.moveRowToGroup(row, status, paymentGroupMeta(status));

        row.querySelectorAll('.icon-btn.refund-review').forEach(function (btn) { btn.remove(); });
        row.querySelectorAll('.icon-btn.paid').forEach(function (btn) { btn.remove(); });
        row.querySelectorAll('.icon-btn.view').forEach(function (btn) {
            btn.dataset.status = status;
            btn.dataset.paymentStatus = status;
        });

        applyFilters();
        AdminUI.refreshGroups(tbody, 'tr.payment-row', function (row) { return row.dataset.groupKey || row.dataset.status || ''; });
    }

    function paymentGroupMeta(status) {
        var groups = {
            pending: { groupKey: 'pending', label: 'Pending Payments', icon: 'fas fa-clock', hint: 'Waiting for approval', colspan: 9 },
            pending_cash: { groupKey: 'pending', label: 'Pending Cash Payments', icon: 'fas fa-money-bill-1-wave', hint: 'Pay on-site', colspan: 9 },
            cash_unpaid: { groupKey: 'pending', label: 'Pending Cash Payments', icon: 'fas fa-money-bill-1-wave', hint: 'Pay on-site', colspan: 9 },
            unpaid: { groupKey: 'pending', label: 'Unpaid Payments', icon: 'fas fa-clock', hint: 'Waiting for payment', colspan: 9 },
            failed: { groupKey: 'failed', label: 'Failed Payments', icon: 'fas fa-triangle-exclamation', hint: 'Payment failed', colspan: 9 },
            paid: { groupKey: 'paid', label: 'Paid Payments', icon: 'fas fa-circle-check', hint: 'Completed payments', colspan: 9 },
            rejected: { groupKey: 'rejected', label: 'Rejected Payments', icon: 'fas fa-circle-xmark', hint: 'Booking rejected', colspan: 9 },
            refund_requested: { groupKey: 'refund', label: 'Refund Requests', icon: 'fas fa-rotate-left', hint: 'Needs admin review', colspan: 9 },
            cancelled: { groupKey: 'cancelled', label: 'Cancelled Payments', icon: 'fas fa-ban', hint: 'Inactive payments', colspan: 9 },
            refunded: { groupKey: 'refunded', label: 'Refunded Payments', icon: 'fas fa-receipt', hint: 'Refund completed', colspan: 9 }
        };
        return groups[status] || { groupKey: status, label: AdminUI.statusLabel(status) + ' Payments', icon: 'fas fa-credit-card', hint: 'Other statuses', colspan: 9 };
    }

    function _ucFirst(str) {
        return String(str).charAt(0).toUpperCase() + String(str).slice(1);
    }

    function _statusLabel(str) {
        return String(str || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) {
            return c.toUpperCase();
        });
    }

    function _refundSummary(raw) {
        return _refundSummaryText(raw);
    }

    function _refundSummaryText(raw) {
        if (!raw) return 'No refund note found.';
        try {
            var timeline = _refundTimeline(JSON.parse(raw));
            if (!timeline.length) return 'No refund note found.';
            return timeline.map(function (item) {
                return item.label + ': ' + item.message + (item.when ? ' - ' + item.when : '');
            }).join('\n');
        } catch (e) {
            return raw;
        }
    }

    function _refundSummaryHtml(raw) {
        if (!raw) return '<span>No refund note found.</span>';
        try {
            var timeline = _refundTimeline(JSON.parse(raw));
            if (!timeline.length) return '<span>No refund note found.</span>';
            return timeline.map(function (item) {
                return '<div class="refund-note-row">' +
                    '<strong>' + _esc(item.label) + '</strong>' +
                    '<span>' + _esc(item.message) + '</span>' +
                    (item.when ? '<small>' + _esc(item.when) + '</small>' : '') +
                '</div>';
            }).join('');
        } catch (e) {
            return '<span>' + _esc(raw) + '</span>';
        }
    }

    function _refundTimeline(notes) {
        var history = Array.isArray(notes.refund_history) ? notes.refund_history : [];
        if (!history.length && notes.refund) history = [notes.refund];

        return history.map(function (event) {
            var label = event.actor === 'admin' ? 'Admin response' : 'User note';
            var reason = _statusLabel(event.reason || event.type || 'refund');
            var note = event.admin_note || event.user_note || event.custom_note || '';
            var decision = event.decision ? _statusLabel(event.decision) + ' - ' : '';

            return {
                label: label,
                message: decision + reason + (note ? ' - ' + note : ''),
                when: event.created_at ? _formatDate(event.created_at) : ''
            };
        });
    }

    function _legacyRefundSummary(raw) {
        if (!raw) return 'No refund note found.';
        try {
            var notes = JSON.parse(raw);
            var refund = notes.refund || {};
            var label = _statusLabel(refund.reason || 'refund request');
            var custom = refund.custom_note ? ' · ' + refund.custom_note : '';
            var actor = refund.actor ? ' by ' + refund.actor : '';
            var when = refund.created_at ? ' · ' + _formatDate(refund.created_at) : '';
            return label + actor + custom + when;
        } catch (e) {
            return raw;
        }
    }

    function _notesSummary(raw) {
        if (!raw) return 'No notes';
        try {
            var notes = JSON.parse(raw);
            var parts = [];
            if (notes.passenger_name) parts.push('Passenger: ' + notes.passenger_name);
            if (notes.seats_count) parts.push('Seats: ' + notes.seats_count);
            if (notes.refund) parts.push('Refund: ' + _refundSummary(raw));
            return parts.length ? parts.join('\n') : 'No notes';
        } catch (e) {
            return raw;
        }
    }

    function _formatDate(str) {
        if (!str) return '—';
        return new Date(str).toLocaleDateString('en-US', {
            year:   'numeric',
            month:  'short',
            day:    'numeric',
            hour:   '2-digit',
            minute: '2-digit'
        });
    }

    function parseJsonResponse(res) {
        return res.text().then(function (text) {
            if (!text.trim()) {
                throw new Error('Server returned an empty response. Please refresh and try again.');
            }

            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Server returned an invalid response. Please refresh and try again.');
            }
        });
    }

    function _esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

document.addEventListener('DOMContentLoaded', window.initPaymentsPage);
