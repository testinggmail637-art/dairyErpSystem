/*
 * Dashboard charts: 30-day milk production (cow / buffalo, stacked areas + daily average), 12-month income vs expenses
 * (paired bars + profit line), and two doughnuts (herd by stage, this month by category) with a clickable legend.
 * Values arrive as decimal strings from the server; Number() is used only to draw, never to calculate.
 * Colours come from the Appearance CSS variables, so the charts follow the theme and every preset.
 */
(function () {
    'use strict';

    if (!window.Chart) return;

    const css = getComputedStyle(document.documentElement);
    const cssVar = (name) => css.getPropertyValue(name).trim();
    Chart.defaults.font.family = cssVar('--bs-body-font-family') || Chart.defaults.font.family;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = cssVar('--bs-secondary-color') || Chart.defaults.color;
    Chart.defaults.borderColor = cssVar('--app-line') || Chart.defaults.borderColor;
    Chart.defaults.maintainAspectRatio = false;

    // canvas cannot paint color-mix() or var(); resolve any CSS colour to rgb() through a hidden element
    const probe = document.createElement('span');
    probe.style.display = 'none';
    document.body.appendChild(probe);
    const resolve = (color) => {
        probe.style.color = '';
        probe.style.color = color;
        return getComputedStyle(probe).color || color;
    };
    const alpha = (color, pct) => resolve('color-mix(in srgb, ' + color + ' ' + pct + '%, transparent)');

    const theme = {
        primary: cssVar('--bs-primary'),
        success: cssVar('--bs-success'),
        warning: cssVar('--bs-warning'),
        secondary: cssVar('--bs-secondary'),
        text: resolve(cssVar('--bs-body-color')),
        muted: resolve(cssVar('--bs-secondary-color')),
        card: resolve(cssVar('--app-card-bg') || cssVar('--bs-body-bg')),
    };

    const qty = (v) => Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const inr = (v) => '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    const inrShort = (v) => {
        const n = Math.abs(Number(v));
        const sign = Number(v) < 0 ? '-' : '';
        if (n >= 1e7) return sign + '₹' + (n / 1e7).toFixed(1).replace(/\.0$/, '') + ' Cr';
        if (n >= 1e5) return sign + '₹' + (n / 1e5).toFixed(1).replace(/\.0$/, '') + ' L';
        if (n >= 1e3) return sign + '₹' + (n / 1e3).toFixed(0) + 'k';
        return sign + '₹' + n;
    };
    const read = (id) => {
        const el = document.getElementById(id);
        return el ? [el, JSON.parse(el.dataset.chart || 'null')] : [null, null];
    };
    const gradient = (ctx, area, color, top, bottom) => {
        const g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        g.addColorStop(0, alpha(color, top));
        g.addColorStop(1, alpha(color, bottom));
        return g;
    };
    const tooltip = {
        backgroundColor: theme.card,
        titleColor: theme.text,
        bodyColor: theme.text,
        footerColor: theme.text,
        borderColor: alpha(theme.text, 15),
        borderWidth: 1,
        padding: 10,
        cornerRadius: 8,
        boxPadding: 4,
        usePointStyle: true,
        titleFont: { weight: '600' },
        footerFont: { weight: '600' },
    };

    // vertical guide line under the cursor (line chart)
    const hoverLine = {
        id: 'hoverLine',
        afterDatasetsDraw(chart) {
            const active = chart.tooltip && chart.tooltip.getActiveElements();
            if (!active || !active.length) return;
            const { ctx, chartArea } = chart;
            const x = active[0].element.x;
            ctx.save();
            ctx.beginPath();
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.lineWidth = 1;
            ctx.strokeStyle = alpha(theme.text, 25);
            ctx.setLineDash([3, 3]);
            ctx.stroke();
            ctx.restore();
        },
    };

    const averageLine = (label, value, color, length) => ({
        type: 'line',
        label: label,
        data: Array(length).fill(Number(value)),
        borderColor: alpha(color, 70),
        borderDash: [6, 5],
        borderWidth: 1.5,
        pointRadius: 0,
        pointHoverRadius: 0,
        fill: false,
        order: 0,
    });

    /* ---------- milk production: stacked areas + average ---------- */
    let [el, data] = read('chart-production');
    if (el && data) {
        const labelTotal = el.dataset.labelTotal; // el is reused below, so read it now
        const cowColor = theme.primary;
        const buffaloColor = theme.warning || theme.success;
        const area = (key, color) => ({
            label: el.dataset['label' + key.charAt(0).toUpperCase() + key.slice(1)],
            data: (data.series[key] || []).map(Number),
            borderColor: resolve(color),
            backgroundColor: (c) => (c.chart.chartArea ? gradient(c.chart.ctx, c.chart.chartArea, color, 35, 2) : alpha(color, 15)),
            fill: true,
            stack: 'milk',
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBorderWidth: 2,
            pointHoverBackgroundColor: theme.card,
            tension: 0.4,
            order: 1,
        });
        new Chart(el, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    area('cow', cowColor),
                    area('buffalo', buffaloColor),
                    averageLine(el.dataset.labelAverage, data.average, theme.text, data.labels.length),
                ],
            },
            plugins: [hoverLine],
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8 } },
                    tooltip: Object.assign({}, tooltip, {
                        itemSort: (a, b) => a.datasetIndex - b.datasetIndex,
                        filter: (item) => item.datasetIndex < 2,
                        callbacks: {
                            label: (c) => ' ' + c.dataset.label + ': ' + qty(c.parsed.y) + ' L',
                            footer: (items) => labelTotal + ': ' + qty(items.reduce((s, i) => s + i.parsed.y, 0)) + ' L',
                        },
                    }),
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        border: { display: false },
                        grid: { drawTicks: false },
                        ticks: { padding: 8, maxTicksLimit: 6, callback: (v) => v + ' L' },
                    },
                },
            },
        });
    }

    /* ---------- income vs expenses: paired bars + profit line ---------- */
    [el, data] = read('chart-finance');
    if (el && data) {
        const incomeColor = theme.success;
        const expenseColor = cssVar('--bs-danger');
        const labels = { income: el.dataset.labelIncome, expenses: el.dataset.labelExpenses, profit: el.dataset.labelProfit };
        const bars = (key, label, color) => ({
            type: 'bar',
            label: label,
            data: data[key].map(Number),
            backgroundColor: (c) => (c.chart.chartArea ? gradient(c.chart.ctx, c.chart.chartArea, color, 90, 55) : alpha(color, 70)),
            hoverBackgroundColor: resolve(color),
            borderRadius: { topLeft: 6, topRight: 6 },
            borderSkipped: 'bottom',
            maxBarThickness: 22,
            categoryPercentage: 0.7,
            barPercentage: 0.9,
            order: 2,
        });
        const financeChart = new Chart(el, {
            data: {
                labels: data.labels,
                datasets: [
                    bars('income', labels.income, incomeColor),
                    bars('expenses', labels.expenses, expenseColor),
                    {
                        type: 'line',
                        label: labels.profit,
                        data: data.profit.map(Number),
                        borderColor: theme.text,
                        backgroundColor: theme.card,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBorderWidth: 2,
                        pointBackgroundColor: (c) => (c.raw < 0 ? resolve(expenseColor) : theme.card),
                        cubicInterpolationMode: 'monotone', // never bends past a point (no fake profit between zeros)
                        order: 1,
                    },
                ],
            },
            plugins: [hoverLine],
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, sort: (a, b) => a.datasetIndex - b.datasetIndex } },
                    tooltip: Object.assign({}, tooltip, {
                        itemSort: (a, b) => a.datasetIndex - b.datasetIndex,
                        callbacks: {
                            label: (c) => ' ' + c.dataset.label + ': ' + inr(c.parsed.y),
                            footer: (items) => {
                                const income = items.find((i) => i.datasetIndex === 0);
                                const profit = items.find((i) => i.datasetIndex === 2);
                                if (!income || !profit || !income.parsed.y) return '';
                                return ((profit.parsed.y / income.parsed.y) * 100).toFixed(0) + '% margin';
                            },
                        },
                    }),
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0 } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { drawTicks: false, color: (c) => (c.tick.value === 0 ? alpha(theme.text, 35) : Chart.defaults.borderColor) },
                        ticks: { padding: 8, maxTicksLimit: 6, callback: inrShort },
                    },
                },
            },
        });

        // Day / Week / Month / Year buttons: fetch that period and redraw in place (totals and subtitle too)
        const periods = document.querySelector('.chart-periods');
        if (periods) {
            const buttons = periods.querySelectorAll('[data-period]');
            const select = (active) => buttons.forEach((b) => {
                b.classList.toggle('active', b === active);
                b.setAttribute('aria-pressed', b === active ? 'true' : 'false');
            });
            let current = periods.querySelector('.active');

            periods.addEventListener('click', (e) => {
                const button = e.target.closest('[data-period]');
                if (!button || button === current) return;
                const previous = current;
                current = button;
                select(button);
                periods.setAttribute('aria-busy', 'true');

                fetch(periods.dataset.financeUrl + '?period=' + encodeURIComponent(button.dataset.period), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then((r) => {
                        if (!r.ok) throw new Error(r.status);
                        return r.json();
                    })
                    .then((json) => {
                        if (current !== button) return; // a newer click won
                        financeChart.data.labels = json.labels;
                        financeChart.data.datasets[0].data = json.income.map(Number);
                        financeChart.data.datasets[1].data = json.expenses.map(Number);
                        financeChart.data.datasets[2].data = json.profit.map(Number);
                        financeChart.update();
                        document.querySelectorAll('[data-finance-text]').forEach((node) => {
                            const key = node.dataset.financeText;
                            node.textContent = key === 'subtitle' ? json.subtitle : json.display[key];
                            if (key === 'total_profit') {
                                const loss = String(json.total_profit).startsWith('-');
                                node.classList.toggle('text-danger', loss);
                                node.classList.toggle('text-success', !loss);
                            }
                        });
                    })
                    .catch(() => {
                        current = previous;
                        select(previous);
                        const message = periods.dataset.financeError;
                        window.Swal ? Swal.fire({ icon: 'error', text: message }) : window.alert(message);
                    })
                    .finally(() => periods.removeAttribute('aria-busy'));
            });
        }
    }

    /* ---------- doughnuts with an HTML legend (value + share, click to hide) ---------- */
    const doughnut = (id) => {
        const [el, rows] = read(id);
        if (!el || !rows || !rows.length) return;
        const key = el.dataset.value;
        const money = el.dataset.money === '1';
        const values = rows.map((r) => Number(r[key]));
        const total = values.reduce((s, v) => s + v, 0) || 1;
        // no danger red in a doughnut: a slice is a share, not an alarm
        const base = [theme.primary, theme.success, theme.warning, theme.secondary].filter(Boolean);
        const palette = rows.map((_, i) => alpha(base[i % base.length], 100 - Math.floor(i / base.length) * 45));

        const chart = new Chart(el, {
            type: 'doughnut',
            data: {
                labels: rows.map((r) => r.label),
                datasets: [{ data: values, backgroundColor: palette, borderColor: theme.card, borderWidth: 3, hoverOffset: 6, borderRadius: 4 }],
            },
            options: {
                cutout: '72%',
                layout: { padding: 6 },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltip, {
                        callbacks: {
                            label: (c) => ' ' + (money ? inr(c.parsed) : c.parsed) + ' · ' + ((c.parsed / total) * 100).toFixed(0) + '%',
                        },
                    }),
                },
            },
        });

        const legend = document.querySelector('[data-legend-for="' + id + '"]');
        if (!legend) return;
        rows.forEach((row, i) => {
            const li = document.createElement('li');
            li.tabIndex = 0;
            li.setAttribute('role', 'button');
            li.innerHTML = '<i></i><span class="chart-legend-label"></span><span class="chart-legend-value"></span><span class="chart-legend-pct"></span>';
            li.querySelector('i').style.background = palette[i];
            li.querySelector('.chart-legend-label').textContent = row.label;
            li.querySelector('.chart-legend-value').textContent = money ? inr(values[i]) : values[i];
            li.querySelector('.chart-legend-pct').textContent = ((values[i] / total) * 100).toFixed(0) + '%';
            const toggle = () => {
                chart.toggleDataVisibility(i);
                chart.update();
                li.classList.toggle('is-off', !chart.getDataVisibility(i));
            };
            li.addEventListener('click', toggle);
            li.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
            li.addEventListener('mouseenter', () => {
                chart.setActiveElements([{ datasetIndex: 0, index: i }]);
                chart.update();
            });
            li.addEventListener('mouseleave', () => {
                chart.setActiveElements([]);
                chart.update();
            });
            legend.appendChild(li);
        });
    };
    // long ₹ figures in a doughnut centre are shortened (₹15.4 L); the full amount stays in the title
    document.querySelectorAll('[data-short-money]').forEach((e) => { e.textContent = inrShort(e.dataset.shortMoney); });
    doughnut('chart-herd');
    doughnut('chart-categories');
})();
