/**
 * dashboard-js.js
 * Bar chart + live activity feed for the admin dashboard.
 * PHP injects: dailyLabels, dailyData
 */

(function () {
    'use strict';

    function initDailyChart() {
        const canvas = document.getElementById('chartDaily');
        if (!canvas || typeof Chart === 'undefined') return;

        if (canvas._dashboardChart) {
            canvas._dashboardChart.destroy();
        }

        const lookup = {};
        (window.dailyLabels || []).forEach((d, i) => {
            lookup[d] = (window.dailyData || [])[i];
        });

        const labels = [];
        const data = [];
        const colors = [];
        const today = new Date();
        const isDark = document.body.classList.contains('admin-dark-mode-active');

        for (let i = 6; i >= 0; i--) {
            const d = new Date(today);
            d.setDate(d.getDate() - i);
            const key = d.toISOString().slice(0, 10);
            labels.push(d.toLocaleDateString('en-PH', { weekday: 'short' }));
            data.push(lookup[key] ?? 0);
            colors.push(i === 0 ? '#F97316' : (isDark ? '#334155' : '#e5e7eb'));
        }

        canvas._dashboardChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors,
                    hoverBackgroundColor: '#F97316',
                    borderRadius: 5,
                    borderSkipped: false,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 500, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#f1f5f9',
                        bodyColor: '#94a3b8',
                        padding: 10,
                        cornerRadius: 6,
                        displayColors: false,
                        callbacks: {
                            label: ctx => `${ctx.parsed.y} booking${ctx.parsed.y !== 1 ? 's' : ''}`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            color: isDark ? '#94a3b8' : '#c0c0c0',
                            font: { size: 10 },
                            maxRotation: 0,
                        },
                    },
                    y: {
                        display: false,
                        beginAtZero: true,
                    },
                },
            },
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[ch]));
    }

    function routeArrows(value) {
        return escapeHtml(value || '').replace(/\s*(?:-&gt;|→)\s*/g, ' <i class="fas fa-arrow-right route-arrow-icon"></i> ');
    }

    function initActivityFeed() {
        const container = document.getElementById('db-activity');
        if (!container) return;

        function render(activities) {
            if (!activities.length) {
                container.innerHTML = `
                    <div class="db-act db-act--empty">
                        <div class="db-act__body">
                            <div class="db-act__text">No recent activity yet.</div>
                            <div class="db-act__time">Updates will appear here automatically.</div>
                        </div>
                    </div>`;
                return;
            }

            container.innerHTML = activities.map(a => `
                <div class="db-act">
                    <div class="db-act__dot" style="background:${escapeHtml(a.color || '#64748b')}"></div>
                    <div class="db-act__body">
                        <div class="db-act__text"><b>${escapeHtml(a.title)}</b></div>
                        <div class="db-act__detail">${routeArrows(a.detail || '')}</div>
                        <div class="db-act__time">${escapeHtml(a.time || '')}</div>
                    </div>
                </div>`).join('');
        }

        function loadActivity() {
            fetch('../../controllers/Dashboard/GetRecentActivity.php', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unable to load activity.');
                    render(data.data || []);
                })
                .catch(() => {
                    container.innerHTML = `
                        <div class="db-act db-act--empty">
                            <div class="db-act__body">
                                <div class="db-act__text">Activity feed unavailable.</div>
                                <div class="db-act__time">It will retry automatically.</div>
                            </div>
                        </div>`;
                });
        }

        loadActivity();
        if (window._dashboardActivityTimer) clearInterval(window._dashboardActivityTimer);
        window._dashboardActivityTimer = setInterval(loadActivity, 15000);
    }

    function initDashboardPage() {
        initDailyChart();
        initActivityFeed();

        if (!window._dashboardThemeObserver && document.body) {
            window._dashboardThemeObserver = new MutationObserver(() => {
                initDailyChart();
            });
            window._dashboardThemeObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ['class'],
            });
        }
    }

    window.initDashboardPage = initDashboardPage;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardPage);
    } else {
        initDashboardPage();
    }
})();
