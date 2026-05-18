window.initUsersPage = function () {

    var tbody = document.getElementById('users-tbody');
    var countBadge = document.getElementById('user-count');
    var searchInput = document.getElementById('user-search');
    var veriFilter = document.getElementById('user-verify-filter');
    var dateFrom = document.getElementById('user-date-from');
    var dateTo = document.getElementById('user-date-to');
    var dateClear = document.getElementById('user-date-clear');
    var statusTabs = document.getElementById('user-status-tabs');
    var viewTitle = document.getElementById('user-view-title');
    var pageSize = 10;
    var currentPage = 1;

    if (!tbody) return;

    /* ── Modal instances ─────────────────────── */
    var viewModal = _modal('viewModal');
    var currentViewedUserRow = null;

    /* ── Count badge ─────────────────────────── */
    function updateCount() {
        var visible = tbody.querySelectorAll('tr.user-row[data-filter-match="1"]').length;
        if (viewTitle) viewTitle.textContent = userViewTitle(veriFilter ? veriFilter.value : '');
        if (countBadge) {
            countBadge.textContent = visible + ' user' + (visible !== 1 ? 's' : '');
        }
        AdminUI.setClearButtonState(dateClear, filtersActive());
    }

    function userViewTitle(status) {
        var titles = {
            pending: 'Pending Verifications',
            approved: 'Verified Users',
            rejected: 'Rejected Verifications'
        };
        return titles[status] || 'All Users';
    }

    function filtersActive() {
        return !!((searchInput && searchInput.value.trim()) ||
            (veriFilter && veriFilter.value && veriFilter.value !== 'pending') ||
            (dateFrom && dateFrom.value) ||
            (dateTo && dateTo.value));
    }

    /* ── Search + filter ─────────────────────── */
    function applyFilters() {
        var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var veri = veriFilter ? veriFilter.value : '';
        var from = dateFrom ? dateFrom.value : '';
        var to = dateTo ? dateTo.value : '';

        var rows = Array.from(tbody.querySelectorAll('tr.user-row'));
        if (!rows.length) {
            updateCount();
            return;
        }
        rows.forEach(function (row) {
            var matchQ = !q
                || (row.dataset.fullname || '').toLowerCase().includes(q)
                || (row.dataset.email || '').toLowerCase().includes(q)
                || (row.dataset.contact || '').toLowerCase().includes(q);
            var matchV = !veri || (row.dataset.verifyStatus || '') === veri;
            var matchD = withinDate(row.dataset.created || '', from, to);
            row.dataset.filterMatch = matchQ && matchV && matchD ? '1' : '0';
        });

        var matchedRows = rows.filter(function (row) { return row.dataset.filterMatch === '1'; });
        var totalPages = Math.max(1, Math.ceil(matchedRows.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        var start = (currentPage - 1) * pageSize;
        var end = start + pageSize;

        rows.forEach(function (row) {
            var index = matchedRows.indexOf(row);
            row.style.display = index >= start && index < end ? '' : 'none';
        });
        updateGroupRows();
        updateCount();
        renderPagination(matchedRows.length, totalPages);
        renderEmptyState(matchedRows.length, veri, from || to);
    }

    function updateGroupRows() {
        tbody.querySelectorAll('tr.admin-status-group-row').forEach(function (groupRow) {
            var key = groupRow.dataset.groupKey || '';
            var hasVisible = Array.from(tbody.querySelectorAll('tr.user-row[data-verify-status="' + key + '"]'))
                .some(function (row) { return row.style.display !== 'none'; });
            groupRow.style.display = hasVisible ? '' : 'none';
        });
    }

    var debouncedApply = AdminUI.debounce(function () { currentPage = 1; applyFilters(); }, 350);
    if (searchInput) searchInput.addEventListener('input', debouncedApply);
    if (veriFilter) veriFilter.addEventListener('change', function () { currentPage = 1; applyFilters(); });
    if (dateFrom) dateFrom.addEventListener('change', function () { currentPage = 1; applyFilters(); });
    if (dateTo) dateTo.addEventListener('change', function () { currentPage = 1; applyFilters(); });
    if (dateClear) dateClear.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (veriFilter) veriFilter.value = 'pending';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        if (statusTabs) {
            statusTabs.querySelectorAll('button').forEach(function (tab) {
                tab.classList.toggle('active', (tab.dataset.status || '') === 'pending');
            });
        }
        currentPage = 1;
        applyFilters();
    });
    if (statusTabs) {
        statusTabs.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-status]');
            if (!btn) return;
            statusTabs.querySelectorAll('button').forEach(function (tab) { tab.classList.remove('active'); });
            btn.classList.add('active');
            if (veriFilter) veriFilter.value = btn.dataset.status || '';
            currentPage = 1;
            applyFilters();
        });
    }
    applyFilters();

    function renderPagination(total, totalPages) {
        var card = document.querySelector('.users-card');
        if (!card) return;

        var pager = document.getElementById('user-pagination');
        if (!pager) {
            pager = document.createElement('div');
            pager.id = 'user-pagination';
            pager.className = 'admin-pagination';
            card.appendChild(pager);
        }

        if (total <= pageSize) {
            pager.innerHTML = '';
            pager.style.display = 'none';
            return;
        }

        var from = (currentPage - 1) * pageSize + 1;
        var to = Math.min(total, currentPage * pageSize);
        pager.style.display = '';
        pager.innerHTML =
            '<span>' + from + '-' + to + ' of ' + total + '</span>' +
            '<div>' +
                '<button type="button" data-page="prev" ' + (currentPage === 1 ? 'disabled' : '') + '>Previous</button>' +
                '<button type="button" data-page="next" ' + (currentPage === totalPages ? 'disabled' : '') + '>Next</button>' +
            '</div>';

        pager.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentPage += btn.dataset.page === 'next' ? 1 : -1;
                applyFilters();
            });
        });
    }

    /* ── Row highlight ───────────────────────── */
    tbody.addEventListener('click', function (e) {
        if (e.target.closest('.row-actions')) return;
        var row = e.target.closest('tr.user-row');
        if (!row) return;
        tbody.querySelectorAll('tr.user-row.selected').forEach(function (r) {
            r.classList.remove('selected');
        });
        row.classList.add('selected');
    });

    /* ── VIEW: open modal + load docs ───────── */
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.icon-btn.view');
        if (!btn || !viewModal) return;
        e.stopPropagation();
        currentViewedUserRow = btn.closest('tr.user-row');

        document.getElementById('view-fullname').textContent = btn.dataset.fullname || '—';
        document.getElementById('view-email').textContent = btn.dataset.email || '—';
        document.getElementById('view-contact').textContent = btn.dataset.contact || 'N/A';
        document.getElementById('view-birthdate').textContent = btn.dataset.birthdate
            ? new Date(btn.dataset.birthdate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
            : 'N/A';
        document.getElementById('view-doc-count').textContent = (btn.dataset.docCount || '0') + ' document(s)';
        document.getElementById('view-created').textContent = btn.dataset.created
            ? new Date(btn.dataset.created).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
            : 'N/A';
        var statusBadge = document.getElementById('view-verification-status');
        var verifyStatus = btn.dataset.verifyStatus || 'no_submission';
        if (statusBadge) {
            statusBadge.className = 'badge ' + (verifyStatus === 'no_submission' ? 'no-submission' : verifyStatus);
            statusBadge.textContent = verifyStatus === 'approved' ? 'Verified' : AdminUI.statusLabel(verifyStatus);
        }

        var docsContainer = document.getElementById('udv-docs-container');
        docsContainer.innerHTML = '<p class="text-muted-sm">Loading documents…</p>';

        viewModal.show();
        
        fetch('../../controllers/UsersController.php?action=get-docs&user_id=' + encodeURIComponent(btn.dataset.id))
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    docsContainer.innerHTML = '<p class="text-muted-sm">' + (data.message || 'Error loading documents.') + '</p>';
                    return;
                }
                _renderDocs(docsContainer, data.documents || []);
            })
            .catch(() => {
                docsContainer.innerHTML = '<p class="text-muted-sm">Network error.</p>';
            });
    });

    /* ── Render document list ────────────────── */
    function _renderDocs(container, docs) {
        if (!docs.length) {
            container.innerHTML =
                '<div class="udv-empty-docs"><i class="fas fa-inbox"></i><p>No documents submitted</p></div>';
            return;
        }

        var html = '<div class="udv-docs-list">';
        docs.forEach(function (doc) {
            var status = _esc(doc.status || 'pending');
            var docType = _esc(doc.document_type || 'N/A');
            var submitted = doc.submitted_at
                ? new Date(doc.submitted_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                : 'N/A';

            html += '<div class="udv-doc-item">';

            html += '  <div class="udv-doc-header">';
            html += '    <div class="udv-doc-info">';
            html += '      <span class="udv-doc-type">' + docType + '</span>';
            html += '      <span class="badge ' + status + '">' + _ucFirst(status) + '</span>';
            html += '    </div>';
            html += '    <span class="udv-doc-date">Submitted: ' + submitted + '</span>';
            html += '  </div>';

            /* Document preview area */
            if (doc.file_path) {
                html += '<div class="udv-doc-preview-area" data-file="' + _esc(doc.file_path) + '" data-type="' + _esc(doc.document_type || '') + '">';
                html += '  <div class="udv-preview-placeholder">';
                html += '    <i class="fas fa-file"></i>';
                html += '    <p>Click to preview</p>';
                html += '  </div>';
                html += '</div>';
            }

            /* Action buttons - only if status is pending */
            if (doc.status === 'pending') {
                html += '<div class="udv-doc-actions">';
                html += '  <button class="udv-btn-small approve" data-doc-id="' + _esc(doc.document_id_pk) + '"><i class="fas fa-check"></i> Approve</button>';
                html += '  <button class="udv-btn-small reject" data-doc-id="' + _esc(doc.document_id_pk) + '"><i class="fas fa-times"></i> Reject</button>';
                html += '</div>';
            }

            html += '</div>';
        });
        html += '</div>';
        container.innerHTML = html;

        /* Attach preview handlers */
        container.querySelectorAll('.udv-doc-preview-area').forEach(function (el) {
            el.addEventListener('click', function () {
                var filePath = el.dataset.file;
                var docType = el.dataset.type;
                _showDocumentPreview(filePath, docType);
            });
        });

        /* Attach approval handlers */
        container.querySelectorAll('.udv-btn-small').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var docId = btn.dataset.docId;
                var status = btn.classList.contains('approve') ? 'approved' : 'rejected';
                AdminUI.confirm({
                    title: status === 'approved' ? 'Approve Verification?' : 'Reject Verification?',
                    text: status === 'approved'
                        ? 'This user verification will be marked as approved.'
                        : 'This user verification will be marked as rejected.',
                    icon: status === 'approved' ? 'question' : 'warning',
                    confirmText: status === 'approved' ? 'Approve' : 'Reject',
                    confirmColor: status === 'approved' ? '#16a34a' : '#ef4444'
                }).then(function (result) {
                    if (result.isConfirmed) _updateDocStatus(docId, status);
                });
            });
        });
    }

    /* ── Document preview modal ──────────────── */
    function _showDocumentPreview(filePath, docType) {
        var ext = filePath.split('.').pop().toLowerCase();
        var isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
        var isPdf = ext === 'pdf';

        var modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.setAttribute('id', 'doc-preview-modal-' + Date.now());
        modal.setAttribute('tabindex', '-1');

        var content = '<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">';
        content += '<div class="modal-header"><h6 class="modal-title">' + _esc(docType) + ' - Preview</h6>';
        content += '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        content += '<div class="modal-body" style="text-align: center; max-height: 70vh; overflow-y: auto;">';

        if (isImage) {
            content += '<img src="../../uploads/documents/' + _esc(filePath) + '" style="max-width: 100%; max-height: 100%;">';
        } else if (isPdf) {
            content += '<iframe src="../../uploads/documents/' + _esc(filePath) + '" style="width: 100%; height: 600px;"></iframe>';
        } else {
            content += '<p><i class="fas fa-file"></i> <strong>' + _esc(filePath) + '</strong></p>';
            content += '<p><a href="../../uploads/documents/' + _esc(filePath) + '" download class="btn btn-sm btn-primary"><i class="fas fa-download"></i> Download</a></p>';
        }

        content += '</div></div></div>';
        modal.innerHTML = content;
        document.body.appendChild(modal);

        var previewModal = bootstrap.Modal.getOrCreateInstance(modal);
        previewModal.show();

        modal.addEventListener('hidden.bs.modal', function () {
            document.body.removeChild(modal);
        });
    }

    function _updateDocStatus(docId, status) {
        _post('../../controllers/UsersController.php?action=update-doc', {
            document_id: docId,
            status: status,
            csrf_token: _csrf()
        }).then(function (d) {
            if (d.success) {
                var docItem = document.querySelector('.udv-btn-small[data-doc-id="' + docId + '"]')?.closest('.udv-doc-item');
                if (docItem) {
                    var badge = docItem.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge ' + status;
                        badge.textContent = _ucFirst(status);
                    }
                    var actions = docItem.querySelector('.udv-doc-actions');
                    if (actions) actions.remove();
                }

                if (currentViewedUserRow) {
                    currentViewedUserRow.dataset.verifyStatus = status;
                    AdminUI.setRowStatus(currentViewedUserRow, status, { datasetKey: 'verifyStatus' });
                    AdminUI.moveRowToGroup(currentViewedUserRow, status, userGroupMeta(status));
                    var rowBadge = currentViewedUserRow.querySelector('.badge');
                    if (rowBadge && status === 'approved') rowBadge.textContent = 'Verified';
                    var modalBadge = document.getElementById('view-verification-status');
                    if (modalBadge) {
                        modalBadge.className = 'badge ' + status;
                        modalBadge.textContent = status === 'approved' ? 'Verified' : AdminUI.statusLabel(status);
                    }
                    applyFilters();
                    AdminUI.refreshGroups(tbody, 'tr.user-row', function (row) { return row.dataset.verifyStatus || ''; });
                }

                AdminUI.notify('success', d.message || (status === 'approved' ? 'User verification approved successfully.' : 'User verification rejected successfully.'));
            } else {
                AdminUI.notify('error', d.message || 'Unable to update verification status. Please try again.');
            }
        }).catch(function () { AdminUI.notify('error', 'Unable to update verification status. Please try again.'); });
    }

    function renderEmptyState(total, status, dateFiltered) {
        tbody.querySelectorAll('.js-empty-row').forEach(function (row) { row.remove(); });
        if (total > 0) return;
        var labels = {
            pending: 'pending verifications',
            approved: 'verified users',
            rejected: 'rejected verifications',
            no_submission: 'users without submissions'
        };
        var label = labels[status] || 'users';
        var message = dateFiltered
            ? 'No ' + label + ' found for the selected date range.'
            : 'No ' + label + ' available.';
        tbody.insertAdjacentHTML('beforeend', '<tr class="js-empty-row"><td colspan="8"><div class="empty-state"><i class="fas fa-search"></i><p>' + message + '</p></div></td></tr>');
    }

    function withinDate(raw, from, to) {
        if (!from && !to) return true;
        if (!raw) return false;
        var value = String(raw).slice(0, 10);
        if (from && value < from) return false;
        if (to && value > to) return false;
        return true;
    }

    /* ── Helpers ─────────────────────────────── */
    function _modal(id) {
        var el = document.getElementById(id);
        return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    function _val(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function _csrf() {
        var el = document.getElementById('page-csrf-token');
        return el ? el.value : '';
    }

    function _post(url, data) {
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) { return r.json(); });
    }

    function _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function _ucFirst(str) {
        return String(str).charAt(0).toUpperCase() + String(str).slice(1);
    }

    function userGroupMeta(status) {
        var groups = {
            pending: { label: 'Pending Verifications', icon: 'fas fa-clock', hint: 'Needs review', colspan: 8 },
            approved: { label: 'Approved Verifications', icon: 'fas fa-circle-check', hint: 'Verified users', colspan: 8 },
            rejected: { label: 'Rejected Verifications', icon: 'fas fa-circle-xmark', hint: 'Needs resubmission', colspan: 8 },
            no_submission: { label: 'No Submission', icon: 'fas fa-inbox', hint: 'No documents yet', colspan: 8 }
        };
        return groups[status] || { label: AdminUI.statusLabel(status), icon: 'fas fa-users', hint: 'Other statuses', colspan: 8 };
    }

    function _resetAddForm() {
        ['add-fullname', 'add-email', 'add-contact', 'add-birthdate'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
    }
};

document.addEventListener('DOMContentLoaded', window.initUsersPage);
