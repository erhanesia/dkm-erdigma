/**
 * Dashboard charts.
 *
 * Each chart is declared in Blade as `<canvas data-chart="line" data-chart-payload="...">`,
 * so the view owns the data and this module owns only the presentation.
 */
import {
    Chart,
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

/*
 * Keyed by the Bootstrap colour names the enums already use, so a payload can
 * pass `AttendanceStatus::color()` straight through. `info` and `secondary`
 * are there for Izin and Sakit, which would otherwise both fall back to green.
 */
const PALETTE = {
    primary: '#0f9670',
    success: '#16a34a',
    warning: '#d97706',
    danger: '#dc2626',
    info: '#0284c7',
    secondary: '#a8a29e',
    neutral: '#a8a29e',
};

Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#78716c';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.legend.labels.padding = 16;

const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        tooltip: {
            backgroundColor: '#1c1917',
            padding: 12,
            cornerRadius: 8,
            titleFont: { weight: '700' },
            displayColors: true,
            boxPadding: 4,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { maxRotation: 0, autoSkipPadding: 16 },
        },
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: '#e7e5e4' },
            ticks: { precision: 0, padding: 8 },
        },
    },
};

/**
 * Fills a canvas gradient so an area chart fades out rather than sitting as a
 * solid slab of colour.
 */
function verticalFade(context, hex) {
    const { ctx, chartArea } = context.chart;

    if (!chartArea) {
        return 'transparent';
    }

    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, `${hex}38`);
    gradient.addColorStop(1, `${hex}00`);

    return gradient;
}

/** Live chart instances, so they can be released before a page swap. */
const instances = [];

/**
 * Chart.js refuses to attach to a canvas it already owns ("Canvas is already in
 * use"). Destroying on navigation keeps that from happening when someone
 * returns to a page, and releases the listeners each chart registers.
 */
export function destroyCharts() {
    while (instances.length > 0) {
        instances.pop().destroy();
    }
}

export function initCharts() {
    destroyCharts();

    document.querySelectorAll('[data-chart]').forEach((canvas) => {
        const payload = JSON.parse(canvas.dataset.chartPayload ?? '{}');
        const type = canvas.dataset.chart;

        if (!payload.labels?.length) {
            return;
        }

        // One ring of shares: every slice has its own colour and there are no
        // axes, so none of the line and bar options below apply.
        if (type === 'doughnut') {
            instances.push(new Chart(canvas, {
                type,
                data: {
                    labels: payload.labels,
                    datasets: (payload.datasets ?? []).map((dataset) => ({
                        ...dataset,
                        backgroundColor: (dataset.colors ?? []).map((name) => PALETTE[name] ?? PALETTE.primary),
                        borderColor: '#fff',
                        borderWidth: 2,
                        hoverOffset: 4,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        tooltip: baseOptions.plugins.tooltip,
                        legend: { display: payload.legend === true, position: 'bottom' },
                    },
                },
            }));

            return;
        }

        const datasets = (payload.datasets ?? []).map((dataset) => {
            const colour = PALETTE[dataset.color] ?? PALETTE.primary;

            return type === 'line'
                ? {
                    ...dataset,
                    borderColor: colour,
                    backgroundColor: (context) => verticalFade(context, colour),
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: colour,
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2,
                    tension: 0.35,
                    fill: true,
                }
                : {
                    ...dataset,
                    backgroundColor: colour,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 28,
                };
        });

        instances.push(new Chart(canvas, {
            type,
            data: { labels: payload.labels, datasets },
            options: {
                ...baseOptions,
                plugins: {
                    ...baseOptions.plugins,
                    legend: { display: datasets.length > 1, position: 'bottom' },
                },
                scales: payload.stacked
                    ? {
                        x: { ...baseOptions.scales.x, stacked: true },
                        y: { ...baseOptions.scales.y, stacked: true },
                    }
                    : baseOptions.scales,
            },
        }));
    });
}
