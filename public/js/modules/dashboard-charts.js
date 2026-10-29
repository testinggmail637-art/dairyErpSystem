/*
 * Dashboard charts: 30-day milk production (cow / buffalo), herd by stage, 12-month expenses, this month by category.
 * Values arrive as decimal strings from the server; Number() is used only to draw, never to calculate.
 * Colours come from the Appearance CSS variables (App.chartColors), so they follow the theme.
 */
(function () {
    'use strict';

    if (!window.Chart) return;

    const css = getComputedStyle(document.documentElement);
    Chart.defaults.font.family = css.getPropertyValue('--bs-body-font-family').trim() || Chart.defaults.font.family;
    Chart.defaults.color = css.getPropertyValue('--bs-secondary-color').trim() || Chart.defaults.color;
    Chart.defaults.borderColor = css.getPropertyValue('--app-line').trim() || Chart.defaults.borderColor;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;

    const colors = App.chartColors();
    const cardBg = css.getPropertyValue('--app-card-bg').trim() || '#fff';
    const fade = (color, pct) => 'color-mix(in srgb, ' + color + ' ' + pct + '%, transparent)';
    const inr = (v) => '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    const read = (id) => {
        const el = document.getElementById(id);
        return el ? [el, JSON.parse(el.dataset.chart || 'null')] : [null, null];
    };

    // canvas cannot paint color-mix(); resolve it to rgb through a hidden element
    const probe = document.createElement('span');
    probe.style.display = 'none';
    document.body.appendChild(probe);
    const resolve = (color) => {
        probe.style.color = color;
        return getComputedStyle(probe).color || color;
    };

    let [el, data] = read('chart-production');
    if (el && data) {
        const line = (key, color) => ({
            label: el.dataset['label' + key.charAt(0).toUpperCase() + key.slice(1)],
            data: (data.series[key] || []).map(Number),
            borderColor: color,
            backgroundColor: resolve(fade(color, 14)),
            fill: true,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.35,
        });
        new Chart(el, {
            type: 'line',
            data: { labels: data.labels, datasets: [line('cow', colors[0]), line('buffalo', colors[2] || colors[1])] },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.y.toFixed(2) + ' L' } } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                    y: { beginAtZero: true, ticks: { callback: (v) => v + ' L' } },
                },
            },
        });
    }

    const doughnut = (id, valueKey, format) => {
        const [el, rows] = read(id);
        if (!el || !rows || !rows.length) return;
        // no danger red in a doughnut: a slice is a share, not an alarm
        const base = ['primary', 'success', 'warning', 'secondary'].map((n) => css.getPropertyValue('--bs-' + n).trim()).filter(Boolean);
        const palette = rows.map((_, i) => resolve(fade(base[i % base.length], 100 - Math.floor(i / base.length) * 45)));
        new Chart(el, {
            type: 'doughnut',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{ data: rows.map((r) => Number(r[valueKey])), backgroundColor: palette, borderColor: cardBg, borderWidth: 2 }],
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: (c) => c.label + ': ' + format(c.parsed) } },
                },
            },
        });
    };
    doughnut('chart-herd', 'count', (v) => v);
    doughnut('chart-categories', 'amount', inr);

    [el, data] = read('chart-expenses');
    if (el && data) {
        const last = data.values.length - 1;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: el.dataset.label,
                    data: data.values.map(Number),
                    backgroundColor: data.values.map((_, i) => resolve(i === last ? colors[0] : fade(colors[0], 45))),
                    borderRadius: 6,
                    maxBarThickness: 36,
                }],
            },
            options: {
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => inr(c.parsed.y) } } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { callback: inr } },
                },
            },
        });
    }
})();
