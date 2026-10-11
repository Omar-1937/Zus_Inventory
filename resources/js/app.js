import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;

Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#64748b';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;

/*
 * Alpine is bundled and started by Livewire; we register our components on alpine:init.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Toast notifications. Livewire components call
     *   $this->dispatch('toast', type: 'success', message: '...')
     * and redirects flash session('toast').
     */
    window.Alpine.data('toaster', (initial = []) => ({
        toasts: [],
        init() {
            initial.forEach((toast) => this.add(toast));
        },
        add(detail) {
            const payload = Array.isArray(detail) ? detail[0] : detail;
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: payload.type ?? 'success', message: payload.message ?? '' });
            setTimeout(() => this.remove(id), payload.type === 'error' ? 7000 : 4500);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }));

    /**
     * Chart.js wrapper. `config` is a full Chart.js config object rendered by
     * the server. When `eventName` is given, a window event with that name
     * and a { data } payload replaces the chart data (used by dashboard filters).
     */
    window.Alpine.data('chart', (config, eventName = null) => ({
        chart: null,
        init() {
            this.chart = new Chart(this.$refs.canvas, config);

            if (eventName) {
                window.addEventListener(eventName, (event) => {
                    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
                    this.chart.data.labels = detail.data.labels;
                    detail.data.datasets.forEach((dataset, index) => {
                        if (this.chart.data.datasets[index]) {
                            this.chart.data.datasets[index].data = dataset.data;
                        }
                    });
                    this.chart.update();
                });
            }
        },
        destroy() {
            this.chart?.destroy();
        },
    }));
});
