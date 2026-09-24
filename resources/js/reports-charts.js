import { Chart, Tooltip, registerables } from 'chart.js';

Chart.register(...registerables);

Tooltip.positioners.cursor = function cursorPositioner(_items, eventPosition) {
    return eventPosition?.x == null || eventPosition?.y == null
        ? false
        : { x: eventPosition.x, y: eventPosition.y };
};

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

function isSolidCssColor(value) {
    return typeof value === 'string' && value.length > 0;
}

function colorAtIndex(value, index) {
    if (Array.isArray(value)) {
        return isSolidCssColor(value[index]) ? value[index] : null;
    }

    return isSolidCssColor(value) ? value : null;
}

function resolveTooltipColor(ctx) {
    const dataset = ctx.dataset || {};
    const dataIndex = ctx.dataIndex ?? 0;
    const sliceColor = colorAtIndex(dataset.backgroundColor, dataIndex);
    const elementFill = colorAtIndex(ctx.element?.options?.backgroundColor, dataIndex);

    if (Array.isArray(dataset.backgroundColor)) {
        return sliceColor || elementFill || palette.indigo;
    }

    return colorAtIndex(dataset.borderColor, dataIndex)
        || sliceColor
        || palette.indigo;
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

                if (typeof color === 'string') {
                    dataset.borderColor = dataset.borderColor || color;
                    dataset.hoverBackgroundColor = dataset.hoverBackgroundColor || withAlpha(color, 1);
                }

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

const saasCanvasLtrPlugin = {
    id: 'saasCanvasLtr',
    afterInit(chart) {
        forceCanvasLtr(chart.canvas);
    },
    afterDraw(chart) {
        forceCanvasLtr(chart.canvas);
    },
};

Chart.register(saasCanvasLtrPlugin);

function forceCanvasLtr(canvas) {
    if (! canvas) {
        return;
    }

    canvas.setAttribute('dir', 'ltr');
    canvas.style.setProperty('direction', 'ltr');
}

function preventCanvasBrowserChrome(canvas) {
    if (! canvas || canvas.dataset.chartGesturesBound === '1') {
        return;
    }

    canvas.dataset.chartGesturesBound = '1';
    canvas.setAttribute('draggable', 'false');
    canvas.addEventListener('dragstart', (event) => event.preventDefault());
    canvas.addEventListener('dblclick', (event) => event.preventDefault());
}

function isRepeatedClick(event) {
    const native = event?.native ?? event;

    return (native?.detail ?? 1) > 1;
}

function destroyChart(id) {
    if (charts.has(id)) {
        charts.get(id).destroy();
        charts.delete(id);
    }
}

function pruneDetachedCharts() {
    charts.forEach((chart, id) => {
        if (! chart.canvas || ! document.body.contains(chart.canvas)) {
            destroyChart(id);
        }
    });
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
        transitions: {
            active: {
                animation: {
                    duration: 120,
                },
            },
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
                caretPadding: 8,
                animation: {
                    duration: 0,
                },
                animations: {
                    numbers: {
                        type: 'number',
                        properties: ['x', 'y', 'width', 'height', 'caretX', 'caretY'],
                        duration: 0,
                    },
                    opacity: {
                        easing: 'linear',
                        duration: 0,
                    },
                },
                callbacks: {
                    labelColor(ctx) {
                        const solid = resolveTooltipColor(ctx);

                        return {
                            borderColor: solid,
                            backgroundColor: solid,
                            borderWidth: 0,
                            borderRadius: 3,
                        };
                    },
                },
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

function applyHorizontalBarHover(type, options) {
    if (type !== 'bar' || options.indexAxis !== 'y') {
        return;
    }

    options.interaction = {
        ...(options.interaction || {}),
        mode: 'nearest',
        axis: 'y',
        intersect: false,
    };

    options.plugins = options.plugins || {};
    options.plugins.tooltip = {
        ...(options.plugins.tooltip || {}),
        position: 'cursor',
        yAlign: options.plugins.tooltip?.yAlign || 'center',
    };
}

function applyTooltipTitles(options, tooltipTitles) {
    if (! tooltipTitles?.length) {
        return;
    }

    options.plugins = options.plugins || {};
    options.plugins.tooltip = options.plugins.tooltip || {};
    options.plugins.tooltip.callbacks = {
        ...(options.plugins.tooltip.callbacks || {}),
        title(items) {
            const index = items[0]?.dataIndex;

            if (index == null) {
                return '';
            }

            return tooltipTitles[index] ?? items[0]?.label ?? '';
        },
    };
}

function applyTooltipBodies(options, tooltipBodies) {
    if (! tooltipBodies?.length) {
        return;
    }

    options.plugins = options.plugins || {};
    options.plugins.tooltip = options.plugins.tooltip || {};
    options.plugins.tooltip.callbacks = {
        ...(options.plugins.tooltip.callbacks || {}),
        label(ctx) {
            const custom = tooltipBodies[ctx.dataIndex];

            if (typeof custom === 'string' && custom !== '') {
                return custom;
            }

            if (ctx.parsed?.y == null) {
                return 'تحلیلی انجام نشد';
            }

            const datasetLabel = ctx.dataset?.label ? `${ctx.dataset.label}: ` : '';

            return `${datasetLabel}${ctx.parsed.y}`;
        },
    };
}

function initChart(canvas) {
    const id = canvas.id;

    if (! id) {
        return;
    }

    destroyChart(id);

    try {
        forceCanvasLtr(canvas);
        preventCanvasBrowserChrome(canvas);

        const config = JSON.parse(canvas.dataset.config || '{}');
        const type = canvas.dataset.type || 'line';
        const tooltipTitles = Array.isArray(config.tooltipTitles) ? config.tooltipTitles : null;
        const tooltipBodies = Array.isArray(config.tooltipBodies) ? config.tooltipBodies : null;
        delete config.tooltipTitles;
        delete config.tooltipBodies;
        const datasetCount = config.datasets?.length ?? 1;
        const options = deepMerge(baseOptions(type, datasetCount), config.options || {});

        applyTooltipTitles(options, tooltipTitles);
        applyTooltipBodies(options, tooltipBodies);
        applyHorizontalBarHover(type, options);
        attachDrilldown(canvas, options);

        const chart = new Chart(canvas, {
            type,
            data: config,
            options,
        });

        charts.set(id, chart);

        if (canvas.closest('[data-drilldown-selected]')) {
            const selectedIndex = selectedDrilldownIndex(canvas);
            const values = JSON.parse(canvas.dataset.drilldownValues || '[]');
            canvas.dataset.selectedPoint = selectedIndex === null ? '' : String(values[selectedIndex]);
            pinSelectedPoint(chart, selectedIndex);
        }

        requestAnimationFrame(() => {
            charts.get(id)?.resize();
        });
    } catch (error) {
        console.error(`Failed to initialize chart "${id}"`, error);
    }
}

function selectedDrilldownIndex(canvas) {
    const values = JSON.parse(canvas.dataset.drilldownValues || '[]');
    const selected = canvas.closest('[data-drilldown-selected]')?.getAttribute('data-drilldown-selected')
        || canvas.dataset.selectedPoint
        || '';

    if (selected === '') {
        return null;
    }

    const index = values.findIndex((value) => String(value) === String(selected));

    return index >= 0 ? index : null;
}

function pinSelectedPoint(chart, selectedIndex) {
    if (! chart?.data?.datasets) {
        return;
    }

    chart.data.datasets.forEach((dataset) => {
        const count = dataset.data?.length ?? 0;

        dataset.pointRadius = Array.from({ length: count }, (_, index) => (
            index === selectedIndex ? 6 : 0
        ));
        dataset.pointHoverRadius = Array.from({ length: count }, (_, index) => (
            index === selectedIndex ? 8 : 6
        ));
    });

    chart.update('none');
}

export function unpinChartPoint(canvasId) {
    const canvas = document.getElementById(canvasId);
    const chart = charts.get(canvasId);

    if (canvas) {
        canvas.dataset.selectedPoint = '';
    }

    if (chart) {
        pinSelectedPoint(chart, null);
    }
}

function attachDrilldown(canvas, options) {
    if (! canvas.dataset.drilldown) {
        return;
    }

    options.onHover = (event, elements) => {
        const target = event?.native?.target;

        if (target) {
            target.style.cursor = elements.length ? 'pointer' : 'default';
        }
    };

    options.onClick = (event, elements, chart) => {
        if (isRepeatedClick(event) || ! elements.length) {
            event?.native?.preventDefault?.();

            return;
        }

        const index = elements[0].index;
        const dimension = canvas.dataset.drilldown;
        const values = JSON.parse(canvas.dataset.drilldownValues || '[]');
        const value = values[index];

        if (dimension === undefined || value === undefined) {
            return;
        }

        const instantCard = canvas.closest('[data-quality-trend-card]');
        const next = canvas.dataset.selectedPoint === String(value) ? '' : String(value);

        if (canvas.closest('[data-drilldown-selected]') || instantCard) {
            canvas.dataset.selectedPoint = next;
            pinSelectedPoint(chart, next === '' ? null : index);
        }

        if (instantCard) {
            instantCard.dispatchEvent(new CustomEvent('quality-trend-select', {
                detail: { period: next },
                bubbles: true,
            }));

            return;
        }

        if (! window.Livewire) {
            return;
        }

        const component = canvas.closest('[wire\\:id]');

        if (component) {
            window.Livewire.find(component.getAttribute('wire:id'))?.call('drilldown', dimension, String(value));
        }
    };
}

export function initReportCharts() {
    pruneDetachedCharts();

    document.querySelectorAll('[data-report-chart]').forEach((canvas) => {
        const existing = charts.get(canvas.id);

        if (existing && existing.canvas === canvas) {
            return;
        }

        initChart(canvas);
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

document.addEventListener('alpine:init', () => {
    window.Alpine.data('qualityTrendCard', (insights, profileBase) => ({
        insights: insights || {},
        profileBase: profileBase || '',
        selected: '',
        get insight() {
            return this.selected ? (this.insights[this.selected] ?? null) : null;
        },
        select(period) {
            this.selected = period || '';
        },
        close() {
            this.selected = '';
            const canvas = this.$el.querySelector('[data-report-chart]');
            unpinChartPoint(canvas?.id);
        },
        scoreClass(score) {
            const value = Number(score || 0);

            if (value >= 85) {
                return 'text-emerald-600 dark:text-emerald-400';
            }
            if (value >= 70) {
                return 'text-indigo-600 dark:text-indigo-400';
            }
            if (value >= 50) {
                return 'text-amber-600 dark:text-amber-400';
            }
            if (value > 0) {
                return 'text-red-600 dark:text-red-400';
            }

            return 'text-zinc-400';
        },
        deltaClass(delta) {
            if (delta > 0) {
                return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
            }
            if (delta < 0) {
                return 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300';
            }

            return 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400';
        },
        signedDelta(delta) {
            if (delta === null || delta === undefined) {
                return null;
            }

            return delta > 0 ? `+${delta}` : String(delta);
        },
        agentUrl(id) {
            return `${this.profileBase}/${id}`;
        },
        agentsTitle() {
            const direction = this.insight?.direction;

            if (direction === 'up') {
                return 'کارشناسانی که باعث افزایش روند شدند';
            }
            if (direction === 'down') {
                return 'کارشناسانی که باعث کاهش روند شدند';
            }

            return 'کارشناسان این روز';
        },
    }));
});
