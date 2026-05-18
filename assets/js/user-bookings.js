/* user-bookings.js — Bookings page specific JavaScript */

(function () {
    // Filter tab handling
    var filterTabs = document.querySelectorAll('.u-ftab');
    filterTabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            // Remove active class from all tabs
            filterTabs.forEach(function (t) {
                t.classList.remove('active');
            });
            // Add active class to clicked tab
            this.classList.add('active');
        });
    });

    // Booking item click handling
    var bookingItems = document.querySelectorAll('.u-bk-item');
    bookingItems.forEach(function (item) {
        item.addEventListener('click', function (e) {
            // Add visual feedback
            this.style.transform = 'scale(0.98)';
            setTimeout(function () {
                item.style.transform = '';
            }, 150);
        });
    });

    // Status badge color mapping (for dynamic updates)
    var statusColors = {
        'pending': 'var(--u-warn)',
        'approved': 'var(--u-success)',
        'rejected': 'var(--u-danger)',
        'cancelled': 'var(--u-grey)',
        'completed': 'var(--u-info)'
    };

    // Function to update badge colors dynamically
    function updateBadgeColors() {
        var badges = document.querySelectorAll('.u-badge');
        badges.forEach(function (badge) {
            var status = badge.classList[1]; // Get the status class
            if (statusColors[status]) {
                badge.style.color = statusColors[status];
            }
        });
    }

    // Call on page load
    updateBadgeColors();
})();