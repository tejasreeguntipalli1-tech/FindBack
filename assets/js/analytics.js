/**
 * High-Quality Analytics Engine (Chart.js)
 * Implements Prompt Sections 49, 50, 51:
 * - Consistent color palette
 * - Meaningful questions answered
 * - Responsive sizing and clean tooltips
 * - Empty-state handling
 */

document.addEventListener('DOMContentLoaded', () => {
    const analyticsContainer = document.getElementById('analyticsDashboard');
    if (!analyticsContainer) return;

    fetch(`${window.APP_BASE_URL}/api/analytics_data.php`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                console.error("Failed to load analytics data", data);
                return;
            }

            // Consistent Color Palette
            const colors = {
                lost: '#e11d48',        // Crimson
                found: '#0d9488',       // Teal
                recovered: '#4f46e5',   // Indigo
                pending: '#f59e0b',     // Amber
                rejected: '#ef4444',    // Danger Red
                verified: '#10b981',    // Emerald
                primary: '#2563eb',     // Blue
                gridLines: '#f1f5f9',
                textDark: '#1e293b',
                textMuted: '#64748b'
            };

            // Update KPI cards in DOM if present
            if (data.kpi) {
                const setEl = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.innerText = val;
                };
                setEl('kpi-total-reports', data.kpi.total_reports);
                setEl('kpi-active-lost', data.kpi.active_lost);
                setEl('kpi-active-found', data.kpi.active_found);
                setEl('kpi-recovered-reports', data.kpi.recovered_reports);
                setEl('kpi-recovery-rate', data.kpi.recovery_rate_label || (data.kpi.recovery_rate !== null ? data.kpi.recovery_rate + '%' : 'No recovery data yet'));
                setEl('kpi-pending-verification', data.kpi.pending_verification);
                setEl('kpi-resolved-reports', data.kpi.resolved_reports);
            }

            // Section 29: Graceful Empty State Chart Helper
            function renderEmptyChart(canvasId, message = 'No report data available yet') {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const parent = canvas.parentElement;
                if (parent) {
                    parent.innerHTML = `
                        <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                            <i class="fa-solid fa-chart-simple fa-2x mb-2 text-secondary opacity-50"></i>
                            <span class="small">${message}</span>
                        </div>
                    `;
                }
            }

            // Chart Defaults
            Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
            Chart.defaults.color = colors.textMuted;

            // ==========================================
            // CHART 1: LOST VS FOUND (Doughnut Chart)
            // ==========================================
            const totalC1 = (data.chart1 && data.chart1.data) ? data.chart1.data.reduce((a, b) => a + b, 0) : 0;
            if (totalC1 === 0) {
                renderEmptyChart('chartLostVsFound', 'No lost or found items recorded yet');
            } else {
                const ctx1 = document.getElementById('chartLostVsFound');
                if (ctx1) {
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: data.chart1.labels,
                        datasets: [{
                            data: data.chart1.data,
                            backgroundColor: [colors.lost, colors.found, colors.recovered],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 16 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const val = context.raw || 0;
                                        const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                        return ` ${context.label}: ${val} (${pct}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
                }
            }

            // ==========================================
            // CHART 2: REPORT ACTIVITY OVER TIME (Line Chart)
            // ==========================================
            const totalC2 = (data.chart2 && data.chart2.lost && data.chart2.found)
                ? (data.chart2.lost.reduce((a, b) => a + b, 0) + data.chart2.found.reduce((a, b) => a + b, 0))
                : 0;
            if (totalC2 === 0) {
                renderEmptyChart('chartActivityOverTime', 'No report activity recorded in the past 7 days');
            } else {
                const ctx2 = document.getElementById('chartActivityOverTime');
                if (ctx2) {
                    new Chart(ctx2, {
                        type: 'line',
                        data: {
                            labels: data.chart2.labels,
                            datasets: [
                                {
                                    label: 'Lost Items Reported',
                                    data: data.chart2.lost,
                                    borderColor: colors.lost,
                                    backgroundColor: 'rgba(225, 29, 72, 0.08)',
                                    fill: true,
                                    tension: 0.35,
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                },
                                {
                                    label: 'Found Items Reported',
                                    data: data.chart2.found,
                                    borderColor: colors.found,
                                    backgroundColor: 'rgba(13, 148, 136, 0.08)',
                                    fill: true,
                                    tension: 0.35,
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: { boxWidth: 12, padding: 12 }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: colors.gridLines }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 },
                                    grid: { color: colors.gridLines }
                                }
                            }
                        }
                    });
                }
            }

            // ==========================================
            // CHART 3: ITEMS BY CATEGORY (Horizontal Bar Chart)
            // ==========================================
            const totalC3 = (data.chart3 && data.chart3.data)
                ? data.chart3.data.reduce((a, b) => a + b, 0)
                : 0;
            if (totalC3 === 0) {
                renderEmptyChart('chartItemsByCategory', 'No categorical data available yet');
            } else {
                const ctx3 = document.getElementById('chartItemsByCategory');
                if (ctx3) {
                    new Chart(ctx3, {
                        type: 'bar',
                        data: {
                            labels: data.chart3.labels,
                            datasets: [{
                                label: 'Total Reports',
                                data: data.chart3.data,
                                backgroundColor: colors.primary,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 },
                                    grid: { color: colors.gridLines }
                                },
                                y: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            }

            // ==========================================
            // CHART 4: RECOVERY PERFORMANCE (Multi-Bar / Trend)
            // ==========================================
            const totalC4 = (data.chart4 && data.chart4.reports)
                ? data.chart4.reports.reduce((a, b) => a + b, 0)
                : 0;
            if (totalC4 === 0) {
                renderEmptyChart('chartRecoveryPerformance', 'No recovery history available yet');
            } else {
                const ctx4 = document.getElementById('chartRecoveryPerformance');
                if (ctx4) {
                    new Chart(ctx4, {
                        type: 'bar',
                        data: {
                            labels: data.chart4.labels,
                            datasets: [
                                {
                                    label: 'Total Submissions',
                                    data: data.chart4.reports,
                                    backgroundColor: '#cbd5e1',
                                    borderRadius: 4
                                },
                                {
                                    label: 'Successful Recoveries',
                                    data: data.chart4.recovered,
                                    backgroundColor: colors.recovered,
                                    borderRadius: 4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: { boxWidth: 12, padding: 12 }
                                }
                            },
                            scales: {
                                x: { grid: { display: false } },
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 },
                                    grid: { color: colors.gridLines }
                                }
                            }
                        }
                    });
                }
            }

            // ==========================================
            // CHART 5: REPORT STATUS LIFECYCLE (Bar / Polar Chart)
            // ==========================================
            const totalC5 = (data.chart5 && data.chart5.data)
                ? data.chart5.data.reduce((a, b) => a + b, 0)
                : 0;
            if (totalC5 === 0) {
                renderEmptyChart('chartReportStatus', 'No status records to display');
            } else {
                const ctx5 = document.getElementById('chartReportStatus');
                if (ctx5) {
                    new Chart(ctx5, {
                        type: 'bar',
                        data: {
                            labels: data.chart5.labels,
                            datasets: [{
                                data: data.chart5.data,
                                backgroundColor: [
                                    colors.pending,
                                    colors.verified,
                                    colors.recovered,
                                    colors.rejected
                                ],
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: { grid: { display: false } },
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 },
                                    grid: { color: colors.gridLines }
                                }
                            }
                        }
                    });
                }
            }

        })
        .catch(err => {
            console.error("Error initializing Chart.js analytics:", err);
        });
});
