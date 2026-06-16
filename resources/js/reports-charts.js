import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export const palette = {
    indigo: 'rgb(99, 102, 241)',
    violet: 'rgb(139, 92, 246)',
    emerald: 'rgb(16, 185, 129)',
    amber: 'rgb(245, 158, 11)',
    rose: 'rgb(244, 63, 94)',
    sky: 'rgb(14, 165, 233)',
    zinc: 'rgb(161, 161, 170)',
};

const seriesColors = [
    palette.indigo,
    palette.emerald,
    palette.amber,
    palette.sky,
    palette.violet,
    palette.rose,
];

const doughnutColors = [
    'rgb(99, 102, 241)',
    'rgb(16, 185, 129)',
    'rgb(245, 158, 11)',
    'rgb(244, 63, 94)',
    'rgb(14, 165, 233)',
    'rgb(139, 92, 246)',
];

const charts = new Map();

function isDarkMode() {
    return document.documentElement.classList.contains('dark');
}

function withAlpha(color, alpha) {
    if (typeof color !== 'string') {
        return `rgba(99, 102, 241, ${alpha})`;
    }

    const rgba = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);

    if (rgba) {
        return `rgba(${rgba[1]}, ${rgba[2]}, ${rgba[3]}, ${alpha})`;
    }

    return color;
}

function resolveColor(dataset, index) {
    return dataset.borderColor
        || dataset.backgroundColor
        || seriesColors[index % seriesColors.length];
}

function makeVerticalGradient(ctx, chartArea, color, topAlpha = 0.28, bottomAlpha = 0) {
    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, withAlpha(color, topAlpha));
    gradient.addColorStop(0.55, withAlpha(color, topAlpha * 0.35));
    gradient.addColorStop(1, withAlpha(color, bottomAlpha));

    return gradient;
}

function makeHorizontalGradient(ctx, chartArea, color) {
    const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
    gradient.addColorStop(0, withAlpha(color, 0.45));
    gradient.addColorStop(1, withAlpha(color, 0.95));

    return gradient;
}

function deepMerge(target, source) {
    const output = { ...target };

    if (! source || typeof source !== 'object') {
        return output;
    }

    Object.keys(source).forEach((key) => {
        if (source[key] && typeof source[key] === 'object' && ! Array.isArray(source[key])) {
            output[key] = deepMerge(output[key] || {}, source[key]);
        } else {
            output[key] = source[key];
        }
    });

    return output;
}

const saasChartThemePlugin = {
    id: 'saasChartTheme',
    beforeUpdate(chart) {
        const { ctx, chartArea, config } = chart;

        if (! chartArea || ! ctx) {
            return;
        }

        const type = config.type;
        const dark = isDarkMode();
        const indexAxis = config.options?.indexAxis;

        config.data.datasets?.forEach((dataset, index) => {
            const color = resolveColor(dataset, index);

            if (type === 'line') {
                const wantsFill = dataset.fill === true
                    || (dataset.fill !== false && config.data.datasets.length === 1);

                if (wantsFill) {
                    dataset.backgroundColor = makeVerticalGradient(ctx, chartArea, color);
                    dataset.fill = true;
                } else {
                    dataset.fill = false;
                }

                dataset.tension = dataset.tension ?? 0.42;
                dataset.borderWidth = dataset.borderWidth ?? 2.5;
                dataset.borderCapStyle = dataset.borderCapStyle ?? 'round';
                dataset.borderJoinStyle = dataset.borderJoinStyle ?? 'round';
                dataset.pointRadius = dataset.pointRadius ?? 0;
                dataset.pointHoverRadius = dataset.pointHoverRadius ?? 7;
                dataset.pointBackgroundColor = dataset.pointBackgroundColor ?? (dark ? 'rgb(24, 24, 27)' : '#ffffff');
                dataset.pointBorderColor = dataset.pointBorderColor ?? color;
                dataset.pointBorderWidth = dataset.pointBorderWidth ?? 2.5;
                dataset.pointHoverBorderWidth = dataset.pointHoverBorderWidth ?? 3;
                dataset.spanGaps = dataset.spanGaps ?? true;
            }

            if (type === 'bar') {
                const horizontal = indexAxis === 'y';

                if (! dataset.borderRadius) {
                    dataset.borderRadius = horizontal
                        ? { topLeft: 0, bottomLeft: 0, topRight: 10, bottomRight: 10 }
                        : { topLeft: 10, topRight: 10, bottomLeft: 0, bottomRight: 0 };
                }

                dataset.borderSkipped = dataset.borderSkipped ?? false;
                dataset.maxBarThickness = dataset.maxBarThickness ?? 44;
                dataset.borderWidth = dataset.borderWidth ?? 0;

                if (typeof dataset.backgroundColor === 'string' && dataset.backgroundColor.includes('rgba')) {
                    dataset.backgroundColor = horizontal
                        ? makeHorizontalGradient(ctx, chartArea, color)
                        : makeVerticalGradient(ctx, chartArea, color, 0.92, 0.55);
                }
            }

            if (type === 'doughnut' || type === 'pie') {
                if (! Array.isArray(dataset.backgroundColor) || dataset.backgroundColor.length === 1) {
                    const count = dataset.data?.length ?? 0;
                    dataset.backgroundColor = Array.from({ length: count }, (_, i) => doughnutColors[i % doughnutColors.length]);
                }

                dataset.borderWidth = dataset.borderWidth ?? 3;
                dataset.borderColor = dataset.borderColor ?? (dark ? 'rgb(24, 24, 27)' : '#ffffff');
                dataset.hoverBorderWidth = dataset.hoverBorderWidth ?? 3;
                dataset.hoverOffset = dataset.hoverOffset ?? 10;
                dataset.borderRadius = dataset.borderRadius ?? 8;
                dataset.spacing = dataset.spacing ?? 3;
            }
        });
    },
};

Chart.register(saasChartThemePlugin);

function destroyChart(id) {
    if (charts.has(id)) {
        charts.get(id).destroy();
        charts.delete(id);
    }
}

function baseOptions(type = 'line', datasetCount = 1) {
    const dark = isDarkMode();
    const tickColor = dark ? 'rgb(161, 161, 170)' : 'rgb(113, 113, 122)';
    const gridColor = dark ? 'rgba(63, 63, 70, 0.45)' : 'rgba(228, 228, 231, 0.9)';
    const tooltipBg = dark ? 'rgba(24, 24, 27, 0.96)' : 'rgba(24, 24, 27, 0.92)';
    const legendColor = dark ? 'rgb(228, 228, 231)' : 'rgb(63, 63, 70)';

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
            padding: { top: 8, right: 8, bottom: 4, left: 4 },
        },
        interaction: {
            intersect: false,
            mode: type === 'bar' || type === 'line' ? 'index' : 'nearest',
        },
        animation: {
            duration: 800,
            easing: 'easeOutQuart',
        },
        plugins: {
            legend: {
                display: type === 'doughnut' || type === 'pie' || datasetCount > 1,
                position: 'bottom',
                rtl: true,
                align: 'center',
                labels: {
                    color: legendColor,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 8,
                    boxHeight: 8,
                    padding: 18,
                    font: {
                        family: 'Vazirmatn, sans-serif',
                        size: 12,
                        weight: '500',
                    },
                },
            },
            tooltip: {
                rtl: true,
                textDirection: 'rtl',
                backgroundColor: tooltipBg,
                titleColor: '#fafafa',
                bodyColor: '#e4e4e7',
                borderColor: dark ? 'rgba(63, 63, 70, 0.8)' : 'rgba(39, 39, 42, 0.15)',
                borderWidth: 1,
                cornerRadius: 10,
                padding: { top: 10, right: 14, bottom: 10, left: 14 },
                titleFont: {
                    family: 'Vazirmatn, sans-serif',
                    size: 13,
                    weight: '600',
                },
                bodyFont: {
                    family: 'Vazirmatn, sans-serif',
                    size: 12,
                    weight: '500',
                },
                displayColors: true,
                boxWidth: 10,
                boxHeight: 10,
                boxPadding: 6,
                caretSize: 6,
                caretPadding: 10,
            },
        },
    };

    if (type !== 'doughnut' && type !== 'pie') {
        options.scales = {
            x: {
                border: { display: false },
                grid: {
                    display: type === 'bar',
                    color: gridColor,
                    drawTicks: false,
                    lineWidth: 1,
                },
                ticks: {
                    color: tickColor,
                    font: {
                        family: 'Vazirmatn, sans-serif',
                        size: 11,
                        weight: '500',
                    },
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 8,
                    padding: 8,
                },
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: {
                    color: gridColor,
                    drawTicks: false,
                    lineWidth: 1,
                },
                ticks: {
                    color: tickColor,
                    font: {
                        family: 'Vazirmatn, sans-serif',
                        size: 11,
                        weight: '500',
                    },
                    padding: 10,
                    maxTicksLimit: 6,
                },
            },
        };
    }

    return options;
}

function initChart(canvas) {
    const id = canvas.id;

    if (! id) {
        return;
    }

    destroyChart(id);

    const config = JSON.parse(canvas.dataset.config || '{}');
    const type = canvas.dataset.type || 'line';
    const datasetCount = config.datasets?.length ?? 1;
    const options = deepMerge(baseOptions(type, datasetCount), config.options || {});

    charts.set(id, new Chart(canvas, {
        type,
        data: config,
        options,
    }));

    canvas.addEventListener('chart:click', (event) => {
        const detail = event.detail || {};

        if (detail.dimension && detail.value !== undefined) {
            window.Livewire?.dispatch('report-drilldown', detail);
        }
    });
}

function wireDrilldown(canvas, chart) {
    canvas.onclick = (evt) => {
        const points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);

        if (! points.length) {
            return;
        }

        const index = points[0].index;
        const dimension = canvas.dataset.drilldown;
        const values = JSON.parse(canvas.dataset.drilldownValues || '[]');
        const value = values[index];

        if (dimension && value !== undefined && window.Livewire) {
            const component = canvas.closest('[wire\\:id]');

            if (component) {
                window.Livewire.find(component.getAttribute('wire:id'))?.call('drilldown', dimension, String(value));
            }
        }
    };
}

export function initReportCharts() {
    document.querySelectorAll('[data-report-chart]').forEach((canvas) => {
        initChart(canvas);

        const chart = charts.get(canvas.id);

        if (chart && canvas.dataset.drilldown) {
            wireDrilldown(canvas, chart);
        }
    });
}

function refreshChartsForTheme() {
    charts.forEach((chart) => chart.update());
}

document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', () => {
        requestAnimationFrame(initReportCharts);
    });

    Livewire.hook('morph.added', () => {
        requestAnimationFrame(initReportCharts);
    });
});

document.addEventListener('livewire:navigated', initReportCharts);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReportCharts);
} else {
    initReportCharts();
}

new MutationObserver(() => {
    refreshChartsForTheme();
}).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});
