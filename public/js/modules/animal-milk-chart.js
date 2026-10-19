/*
 * Animal profile → Milk tab: line chart of morning, evening and total litres per day (last 60 days).
 * Values arrive as decimal strings from the server; Number() is used only to draw the chart, never to calculate.
 * Colours come from the Appearance CSS variables (App.chartColors).
 */
(function () {
    'use strict';

    const canvas = document.getElementById('animal-milk-chart');
    if (!canvas || !window.Chart) return;

    const css = getComputedStyle(document.documentElement);
    Chart.defaults.font.family = css.getPropertyValue('--bs-body-font-family').trim() || Chart.defaults.font.family;
    Chart.defaults.color = css.getPropertyValue('--bs-body-color').trim() || Chart.defaults.color;
    Chart.defaults.borderColor = css.getPropertyValue('--bs-border-color').trim() || Chart.defaults.borderColor;

    const colors = App.chartColors();
    const read = (key) => JSON.parse(canvas.dataset[key] || '[]').map((v) => (v === null ? null : Number(v)));
    const line = (key, color, width) => ({
        label: canvas.dataset['label' + key.charAt(0).toUpperCase() + key.slice(1)],
        data: read(key),
        borderColor: color,
        backgroundColor: color,
        borderWidth: width,
        pointRadius: 2,
        spanGaps: true,
        tension: 0.25,
    });

    let chart = null;
    const draw = () => {
        if (chart) return;
        chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: JSON.parse(canvas.dataset.labels || '[]'),
                datasets: [line('total', colors[0], 3), line('morning', colors[1], 1.5), line('evening', colors[2], 1.5)],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'L' } } },
            },
        });
    };

    // the canvas sits in a hidden tab: draw when the Milk tab is first shown (correct size), or now if visible
    const tab = document.getElementById('tab-milk');
    if (tab) tab.addEventListener('shown.bs.tab', draw, { once: true });
    if (canvas.offsetParent !== null) draw();
})();
