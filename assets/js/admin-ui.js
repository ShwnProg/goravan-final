(function () {
    function statusLabel(status) {
        return String(status || '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    }

    function notify(type, message, title) {
        var titles = {
            success: 'Success',
            error: 'Unable to Complete Request',
            warning: 'Action Needed',
            info: 'Notice'
        };

        if (!window.Swal) {
            window.alert(message || title || titles[type] || 'Notice');
            return Promise.resolve();
        }

        return Swal.fire({
            icon: type || 'info',
            title: title || titles[type] || 'Notice',
            text: message || '',
            toast: true,
            position: 'top-right',
            timer: type === 'error' ? 4200 : 2400,
            showConfirmButton: false,
            timerProgressBar: true
        });
    }

    function confirm(options) {
        return Swal.fire({
            title: options.title || 'Confirm Action',
            text: options.text || '',
            html: options.html,
            icon: options.icon || 'question',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Confirm',
            cancelButtonText: options.cancelText || 'Cancel',
            confirmButtonColor: options.confirmColor || '#2E3A4D',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            focusCancel: true
        });
    }

    function updateBadge(container, status) {
        var badge = container ? container.querySelector('.badge') : null;
        if (!badge) return;
        badge.className = 'badge ' + status;
        badge.textContent = statusLabel(status);
    }

    function setRowStatus(row, status, options) {
        if (!row) return;
        var oldStatus = options && options.oldStatus ? options.oldStatus : (row.dataset.status || row.dataset.verifyStatus || '');
        var classPrefix = options && options.classPrefix ? options.classPrefix : 'status-';

        if (options && options.datasetKey) {
            row.dataset[options.datasetKey] = status;
        } else {
            row.dataset.status = status;
        }

        Array.from(row.classList).forEach(function (className) {
            if (className.indexOf(classPrefix) === 0) row.classList.remove(className);
        });
        row.classList.add(classPrefix + String(status).replace(/_/g, '-'));

        if (!options || options.updateBadge !== false) updateBadge(row, status);
        row.classList.add('admin-row-updated');
        window.setTimeout(function () { row.classList.remove('admin-row-updated'); }, 900);
        return oldStatus;
    }

    function ensureGroup(tbody, key, templateGroupRow, label, icon, hint, colspan) {
        var selector = 'tr.admin-status-group-row[data-group-key="' + key + '"], tr.payment-group-row[data-group-key="' + key + '"]';
        var group = tbody.querySelector(selector);
        if (group) return group;

        group = document.createElement('tr');
        group.className = templateGroupRow && templateGroupRow.classList.contains('payment-group-row')
            ? 'payment-group-row'
            : 'admin-status-group-row';
        group.dataset.groupKey = key;
        group.innerHTML =
            '<td colspan="' + (colspan || 99) + '">' +
                '<div class="' + (group.className === 'payment-group-row' ? 'payment-group-label' : 'admin-status-group-label') + '">' +
                    '<i class="' + (icon || 'fas fa-circle') + '"></i>' +
                    '<span>' + (label || statusLabel(key)) + '</span>' +
                    '<small>' + (hint || 'Updated records') + '</small>' +
                '</div>' +
            '</td>';
        tbody.appendChild(group);
        return group;
    }

    function moveRowToGroup(row, status, meta) {
        if (!row) return;
        var tbody = row.closest('tbody');
        if (!tbody) return;

        var key = meta && meta.groupKey ? meta.groupKey : status;
        var group = ensureGroup(
            tbody,
            key,
            tbody.querySelector('tr.admin-status-group-row, tr.payment-group-row'),
            meta && meta.label,
            meta && meta.icon,
            meta && meta.hint,
            meta && meta.colspan
        );

        var nextGroup = null;
        var cursor = group.nextElementSibling;
        while (cursor) {
            if (cursor.matches('.admin-status-group-row, .payment-group-row')) {
                nextGroup = cursor;
                break;
            }
            cursor = cursor.nextElementSibling;
        }

        if (nextGroup) {
            tbody.insertBefore(row, nextGroup);
        } else {
            tbody.appendChild(row);
        }
    }

    function refreshGroups(tbody, rowSelector, statusGetter) {
        if (!tbody) return;
        tbody.querySelectorAll('tr.admin-status-group-row, tr.payment-group-row').forEach(function (groupRow) {
            var key = groupRow.dataset.groupKey || '';
            var count = 0;
            tbody.querySelectorAll(rowSelector).forEach(function (row) {
                var rowKey = statusGetter ? statusGetter(row) : (row.dataset.status || row.dataset.groupKey || '');
                if (rowKey === key && row.style.display !== 'none') count++;
            });

            groupRow.style.display = count ? '' : 'none';
            var small = groupRow.querySelector('small');
            if (small) {
                if (!small.dataset.originalHint) small.dataset.originalHint = small.textContent || '';
                small.textContent = count ? small.dataset.originalHint + ' - ' + count : small.dataset.originalHint;
            }
        });
    }

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            var ctx = this;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(ctx, args);
            }, delay || 350);
        };
    }

    function setClearButtonState(button, active) {
        if (!button) return;
        button.disabled = !active;
        button.classList.toggle('is-active', !!active);
        button.setAttribute('aria-disabled', active ? 'false' : 'true');
    }

    window.AdminUI = {
        statusLabel: statusLabel,
        notify: notify,
        confirm: confirm,
        setRowStatus: setRowStatus,
        moveRowToGroup: moveRowToGroup,
        refreshGroups: refreshGroups,
        debounce: debounce,
        setClearButtonState: setClearButtonState
    };
})();
