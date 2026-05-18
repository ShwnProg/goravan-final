/* user-payments.js - Payment list filtering and receipt modal */

(function () {
    'use strict';

    var payments = [];
    var filtered = [];
    var modalEl = document.getElementById('paymentDetailModal');
    var detailModal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var refundModalEl = document.getElementById('refundRequestModal');
    var refundModal = refundModalEl ? bootstrap.Modal.getOrCreateInstance(refundModalEl) : null;
    var activePayment = null;

    document.addEventListener('DOMContentLoaded', function () {
        bindFilters();
        bindPrint();
        bindRefund();
        loadPayments();
    });

    function bindFilters() {
        ['paymentSearch', 'paymentStatusFilter', 'paymentDateFrom', 'paymentDateTo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener(id === 'paymentSearch' ? 'input' : 'change', function () {
                syncDateRange(id);
                applyFilters();
            });
        });

        var clearBtn = document.getElementById('paymentClearFilters');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                ['paymentSearch', 'paymentStatusFilter', 'paymentDateFrom', 'paymentDateTo'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
                syncDateRange();
                applyFilters();
            });
        }

        var list = document.getElementById('paymentList');
        if (list) {
            list.addEventListener('click', function (e) {
                var card = e.target.closest('.payment-card-item');
                if (!card) return;
                var payment = payments.find(function (item) {
                    return String(item.payment_id) === String(card.dataset.paymentId);
                });
                if (payment) openDetail(payment);
            });
        }

        syncDateRange();
    }

    function bindPrint() {
        var btn = document.getElementById('downloadReceiptBtn');
        if (btn) {
            btn.addEventListener('click', function () {
                window.print();
            });
        }
    }

    function bindRefund() {
        var requestBtn = document.getElementById('requestRefundBtn');
        if (requestBtn) {
            requestBtn.addEventListener('click', function () {
                if (!activePayment || !refundModal) return;
                setValue('refundPaymentId', activePayment.payment_id || '');
                setValue('refundReason', 'change_of_plans');
                setValue('refundCustomNote', '');
                if (detailModal) detailModal.hide();
                refundModal.show();
            });
        }

        var submitBtn = document.getElementById('submitRefundRequestBtn');
        if (submitBtn) {
            submitBtn.addEventListener('click', submitRefundRequest);
        }

        var cancelBtn = document.getElementById('cancelRefundRequestBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', cancelRefundRequest);
        }
    }

    function loadPayments() {
        var userId = (document.getElementById('paymentsUserId') || {}).value || '';
        return fetch('../../controllers/users/PaymentController.php?action=list&user_id=' + encodeURIComponent(userId), {
            headers: { 'Accept': 'application/json' }
        })
            .then(parseJsonResponse)
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Unable to load payments.');
                payments = data.data || [];
                applyFilters();
            })
            .catch(function (err) {
                renderEmpty(err.message || 'Unable to load payments.');
            });
    }

    function applyFilters() {
        var q = ((document.getElementById('paymentSearch') || {}).value || '').toLowerCase().trim();
        var status = ((document.getElementById('paymentStatusFilter') || {}).value || '').toLowerCase();
        var from = ((document.getElementById('paymentDateFrom') || {}).value || '');
        var to = ((document.getElementById('paymentDateTo') || {}).value || '');

        filtered = payments.filter(function (p) {
            var haystack = [
                p.reference_code,
                p.route_display,
                p.payment_method,
                p.payment_reference,
                p.passenger_name
            ].join(' ').toLowerCase();

            var paidDate = (p.paid_at || p.created_at || '').slice(0, 10);
            var matchQ = !q || haystack.includes(q);
            var matchStatus = !status || String(p.status).toLowerCase() === status;
            var matchFrom = !from || paidDate >= from;
            var matchTo = !to || paidDate <= to;

            return matchQ && matchStatus && matchFrom && matchTo;
        });

        renderStats();
        renderFilterFeedback();
        renderList();
    }

    function syncDateRange(changedId) {
        var fromEl = document.getElementById('paymentDateFrom');
        var toEl = document.getElementById('paymentDateTo');
        if (!fromEl || !toEl) return;

        if (fromEl.value && toEl.value && fromEl.value > toEl.value) {
            if (changedId === 'paymentDateTo') {
                fromEl.value = toEl.value;
            } else {
                toEl.value = fromEl.value;
            }
        }

        toEl.min = fromEl.value || '';
        fromEl.max = toEl.value || '';
    }

    function renderFilterFeedback() {
        var el = document.getElementById('paymentFilterFeedback');
        if (!el) return;

        var hasFilters = ['paymentSearch', 'paymentStatusFilter', 'paymentDateFrom', 'paymentDateTo'].some(function (id) {
            return ((document.getElementById(id) || {}).value || '').trim() !== '';
        });

        if (!payments.length) {
            el.textContent = 'No payment records yet.';
            return;
        }

        el.textContent = hasFilters
            ? 'Showing ' + filtered.length + ' of ' + payments.length + ' payment' + (payments.length === 1 ? '' : 's') + '.'
            : 'Showing all ' + payments.length + ' payment' + (payments.length === 1 ? '' : 's') + '.';
    }

    function renderStats() {
        var paid = payments.filter(function (p) {
            return p.status === 'paid';
        });
        var totalSpent = paid.reduce(function (sum, p) {
            return sum + parseFloat(p.amount || 0);
        }, 0);
        var sorted = paid.slice().sort(function (a, b) {
            return new Date(b.paid_at || b.created_at || 0) - new Date(a.paid_at || a.created_at || 0);
        });

        setText('totalSpent', peso(totalSpent));
        setText('totalTrips', paid.length);
        setText('lastPayment', sorted[0] ? formatDate(sorted[0].paid_at || sorted[0].created_at) : '-');
    }

    function renderList() {
        var list = document.getElementById('paymentList');
        if (!list) return;

        if (!filtered.length) {
            renderEmpty('No payments match your filters.');
            return;
        }

        list.innerHTML = filtered.map(function (p) {
            var method = methodMeta(p.payment_method);
            return '<article class="payment-card-item" data-payment-id="' + esc(p.payment_id) + '">' +
                '<div class="payment-main">' +
                    '<div class="payment-ref">' + esc(p.reference_code) + '</div>' +
                    '<div class="payment-route">' + routeHtml(p.route_display) + '</div>' +
                    '<div class="payment-meta">' + esc(formatDateTime(p.departure_date, p.departure_time)) + ' · ' + esc(p.seats_count || 0) + ' seat(s)</div>' +
                '</div>' +
                '<div>' +
                    '<div class="payment-method">' +
                        '<i class="' + method.icon + '"></i><span>' + esc(method.label) + '</span>' +
                    '</div>' +
                    '<div class="passenger-type-row">' + passengerTypeBadges(p) + '</div>' +
                '</div>' +
                '<div class="payment-side">' +
                    '<div class="payment-amount">' + esc(peso(p.amount)) + '</div>' +
                    '<span class="pay-badge ' + esc(p.status) + '">' + esc(statusLabel(p.status)) + '</span>' +
                '</div>' +
                '<div class="payment-chevron"><i class="fa-solid fa-chevron-right"></i></div>' +
            '</article>';
        }).join('');
    }

    function renderEmpty(message) {
        var list = document.getElementById('paymentList');
        if (!list) return;
        list.innerHTML = '<div class="payment-empty">' +
            '<img src="/images/vanny-waiting.png" alt="Vanny waiting for payments" class="vanny-mascot payment-empty-vanny" loading="lazy" decoding="async">' +
            '<p>' + esc(message) + '</p>' +
        '</div>';
    }

    function openDetail(payment) {
        activePayment = payment;
        var method = methodMeta(payment.payment_method);
        setText('detailReference', payment.reference_code || '-');
        setHTML('detailRoute', routeHtml(payment.route_display || '-'));
        setText('detailDate', formatDateTime(payment.departure_date, payment.departure_time));
        setHTML('detailSeats', seatSummary(payment.seat_numbers));
        setHTML('detailPassenger', passengerDetails(payment));
        setHTML('detailPassengerType', passengerTypeBadges(payment));
        setText('detailMethod', method.label);
        setText('detailPaymentRef', payment.payment_reference || '-');
        setText('detailStatus', statusLabel(payment.status || '-'));
        setHTML('detailAmount', paymentBreakdown(payment));
        renderRefundNotes(payment);

        var refundBtn = document.getElementById('requestRefundBtn');
        if (refundBtn) {
            refundBtn.hidden = !canRequestRefund(payment);
        }
        var cancelRefundBtn = document.getElementById('cancelRefundRequestBtn');
        if (cancelRefundBtn) {
            cancelRefundBtn.hidden = !canCancelRefund(payment);
        }

        if (detailModal) detailModal.show();
    }

    function canRequestRefund(payment) {
        if (String(payment.payment_method || '').toLowerCase() === 'cash') return false;
        return String(payment.status || '').toLowerCase() === 'paid' &&
            String(payment.booking_status || '').toLowerCase() === 'approved';
    }

    function canCancelRefund(payment) {
        return String(payment.status || '').toLowerCase() === 'refund_requested';
    }

    function renderRefundNotes(payment) {
        var el = document.getElementById('detailRefundNotes');
        if (!el) return;

        var timeline = refundTimeline(payment.notes || {});
        if (!timeline.length) {
            el.hidden = true;
            el.innerHTML = '';
            return;
        }

        el.hidden = false;
        el.innerHTML = '<span class="refund-note-title">Refund updates</span>' +
            timeline.map(function (item) {
                return '<div class="refund-note-item">' +
                    '<strong>' + esc(item.label) + '</strong>' +
                    '<span>' + esc(item.message) + '</span>' +
                    (item.when ? '<small>' + esc(item.when) + '</small>' : '') +
                '</div>';
            }).join('');
    }

    function refundTimeline(notes) {
        if (typeof notes === 'string') {
            try {
                notes = JSON.parse(notes);
            } catch (e) {
                notes = {};
            }
        }

        var history = Array.isArray(notes.refund_history) ? notes.refund_history : [];
        if (!history.length && notes.refund) history = [notes.refund];

        return history.map(function (event) {
            var label = event.actor === 'admin' ? 'Admin response' : 'Your note';
            var reason = statusLabel(event.reason || event.type || 'refund');
            var note = event.admin_note || event.user_note || event.custom_note || '';
            var decision = event.decision ? statusLabel(event.decision) + ' - ' : '';

            return {
                label: label,
                message: decision + reason + (note ? ' - ' + note : ''),
                when: event.created_at ? formatDate(event.created_at) : ''
            };
        });
    }

    function submitRefundRequest() {
        var submitBtn = document.getElementById('submitRefundRequestBtn');
        var paymentId = (document.getElementById('refundPaymentId') || {}).value || '';
        var reason = (document.getElementById('refundReason') || {}).value || '';
        var customNote = (document.getElementById('refundCustomNote') || {}).value || '';
        var csrf = (document.getElementById('paymentsCsrfToken') || {}).value || '';

        if (!paymentId || !reason) return;

        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('payment_id', paymentId);
        fd.append('reason', reason);
        fd.append('custom_note', customNote);

        if (submitBtn) submitBtn.disabled = true;

        fetch('../../controllers/users/PaymentController.php?action=request_refund', {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json' }
        })
            .then(parseJsonResponse)
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Unable to request refund.');
                if (refundModal) refundModal.hide();
                toast(data.message || 'Refund request submitted. Your booking remains active while admin reviews it.', 'success');
                return loadPayments();
            })
            .catch(function (err) {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Refund Request Failed', text: err.message });
                } else {
                    alert(err.message);
                }
            })
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    function cancelRefundRequest() {
        if (!activePayment || !canCancelRefund(activePayment)) return;

        var runCancel = function () {
            var cancelBtn = document.getElementById('cancelRefundRequestBtn');
            var csrf = (document.getElementById('paymentsCsrfToken') || {}).value || '';
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('payment_id', activePayment.payment_id || '');

            if (cancelBtn) cancelBtn.disabled = true;

            fetch('../../controllers/users/PaymentController.php?action=cancel_refund', {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            })
                .then(parseJsonResponse)
                .then(function (data) {
                    if (!data.success) throw new Error(data.message || 'Unable to cancel refund request.');
                    if (detailModal) detailModal.hide();
                    toast(data.message || 'Refund request cancelled.', 'success');
                    return loadPayments();
                })
                .catch(function (err) {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Cancel Refund Failed', text: err.message });
                    } else {
                        alert(err.message);
                    }
                })
                .finally(function () {
                    if (cancelBtn) cancelBtn.disabled = false;
                });
        };

        if (window.Swal) {
            Swal.fire({
                icon: 'question',
                title: 'Cancel refund request?',
                text: 'This withdraws your refund request and keeps your booking active.',
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel request'
            }).then(function (result) {
                if (result.isConfirmed) runCancel();
            });
        } else if (confirm('Cancel refund request?')) {
            runCancel();
        }
    }

    function parseJsonResponse(res) {
        return res.text().then(function (text) {
            if (!text.trim()) {
                throw new Error('Server returned an empty response. Please refresh and try again.');
            }

            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error('Server returned an invalid response. Please refresh and try again.');
            }
        });
    }

    function toast(message, icon) {
        if (window.Swal) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon || 'success',
                title: message,
                showConfirmButton: false,
                timer: 2600,
                timerProgressBar: true
            });
        }
    }

    function methodMeta(method) {
        return {
            gcash: { icon: 'fa-regular fa-credit-card', label: 'GCash' },
            paymaya: { icon: 'fa-regular fa-credit-card', label: 'PayMaya' },
            card: { icon: 'fa-regular fa-credit-card', label: 'Card' },
            cash: { icon: 'fa-solid fa-money-bill-1', label: 'Cash' }
        }[method] || { icon: 'fa-regular fa-credit-card', label: capitalize(method || 'Payment') };
    }

    function labelPassengerType(type) {
        return {
            regular: 'Regular',
            student: 'Student',
            senior: 'Senior Citizen',
            pwd: 'PWD'
        }[type] || 'Regular';
    }

    function passengerTypeSummary(payment) {
        var counts = passengerTypeCounts(payment);
        return Object.keys(counts).map(function (type) {
            return counts[type] + ' ' + labelPassengerType(type);
        }).join(', ');
    }

    function passengerTypeBadges(payment) {
        var counts = passengerTypeCounts(payment);
        return Object.keys(counts).map(function (type) {
            var label = counts[type] + ' ' + labelPassengerType(type);
            return '<span class="pay-badge passenger type-' + passengerTypeClass(type) + '">' +
                '<i class="' + passengerTypeIcon(type) + '"></i>' +
                '<span>' + esc(label) + '</span>' +
            '</span>';
        }).join('');
    }

    function passengerTypeCounts(payment) {
        var passengers = Array.isArray(payment.passengers) ? payment.passengers : [];
        if (!passengers.length) {
            var fallbackType = String(payment.passenger_type || 'regular').toLowerCase();
            var fallback = {};
            fallback[fallbackType] = 1;
            return fallback;
        }

        var counts = {};
        passengers.forEach(function (p) {
            var type = String(p.type || 'regular').toLowerCase();
            counts[type] = (counts[type] || 0) + 1;
        });
        return counts;
    }

    function passengerTypeIcon(type) {
        return {
            regular: 'fa-regular fa-user',
            student: 'fa-solid fa-graduation-cap',
            senior: 'fa-solid fa-person-cane',
            pwd: 'fa-solid fa-wheelchair'
        }[type] || 'fa-regular fa-user';
    }

    function passengerTypeClass(type) {
        return String(type || 'regular').toLowerCase().replace(/[^a-z0-9_-]/g, '');
    }

    function passengerNameSummary(payment) {
        var passengers = Array.isArray(payment.passengers) ? payment.passengers : [];
        return passengers.map(function (p) {
            return (p.seat_number || '-') + ': ' + (p.name || '-') + ' (' + labelPassengerType(p.type) + ')';
        }).join(', ');
    }

    function passengerDetails(payment) {
        var passengers = Array.isArray(payment.passengers) ? payment.passengers : [];
        if (!passengers.length) {
            return '<span class="receipt-value-text">' + esc(payment.passenger_name || '-') + '</span>';
        }

        return '<span class="receipt-passenger-group">' +
            '<strong>' + esc(payment.passenger_name || passengers[0].name || '-') + '</strong>' +
            '<span class="receipt-seat-list">' + passengers.map(function (p) {
                return '<span class="receipt-seat">' + esc(p.seat_number || '-') + ' <small>' + esc(labelPassengerType(p.type)) + '</small></span>';
            }).join('') + '</span>' +
        '</span>';
    }

    function seatSummary(value) {
        var seats = String(value || '').split(',').map(function (seat) {
            return seat.trim();
        }).filter(Boolean);

        if (!seats.length) return '<span class="receipt-value-text">-</span>';

        return '<span class="receipt-seat-list">' + seats.map(function (seat) {
            return '<span class="receipt-seat">' + esc(seat) + '</span>';
        }).join('') + '</span>';
    }

    function paymentBreakdown(payment) {
        var base = parseFloat(payment.base_total || (payment.notes && payment.notes.base_total) || 0);
        var discount = parseFloat(payment.discount_amount || 0);
        var cashFee = parseFloat(payment.cash_fee || (payment.notes && payment.notes.cash_fee) || 0);
        if (!base && !discount && !cashFee) return peso(payment.amount);
        return '<span class="receipt-money-breakdown">' +
            '<span><small>Base fare</small><b>' + esc(peso(base)) + '</b></span>' +
            '<span><small>Discount</small><b>-' + esc(peso(discount)) + '</b></span>' +
            (cashFee > 0 ? '<span><small>Cash handling fee</small><b>' + esc(peso(cashFee)) + '</b></span>' : '') +
            '<span class="receipt-money-total"><small>Total</small><b>' + esc(peso(payment.amount)) + '</b></span>' +
        '</span>';
    }

    function routeHtml(value) {
        var route = String(value || '').replace(/\s*→\s*/g, ' -> ');
        var parts = route.split(/\s*->\s*/);
        if (parts.length >= 2) {
            return esc(parts[0]) + ' <i class="fa-solid fa-arrow-right route-arrow-icon"></i> ' + esc(parts.slice(1).join(' -> '));
        }
        return esc(value || '-');
    }

    function formatDateTime(date, time) {
        if (!date) return '-';
        var dt = new Date(date + 'T' + (time || '00:00:00'));
        if (Number.isNaN(dt.getTime())) return date + (time ? ' ' + time : '');
        return dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) +
            ' · ' + dt.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    }

    function formatDate(value) {
        if (!value) return '-';
        var dt = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(dt.getTime())) return value;
        return dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function peso(value) {
        return '₱' + (parseFloat(value || 0)).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function capitalize(value) {
        value = String(value || '');
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function statusLabel(value) {
        return String(value || '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (char) { return char.toUpperCase(); });
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function setValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function setHTML(id, value) {
        var el = document.getElementById(id);
        if (el) el.innerHTML = value;
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
