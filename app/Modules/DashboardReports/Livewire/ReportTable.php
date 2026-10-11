<?php

namespace App\Modules\DashboardReports\Livewire;

use App\Livewire\Concerns\WithAppPagination;
use App\Models\Category;
use App\Models\Supplier;
use App\Modules\DashboardReports\Requests\ReportFilterRequest;
use App\Modules\DashboardReports\Services\ReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Interactive filters for the five reports. The CSV export link carries the
 * same filters to ReportController::export(), which runs the same query.
 */
class ReportTable extends Component
{
    use WithAppPagination;

    #[Locked]
    public string $report;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(as: 'category', except: '')]
    public string $categoryId = '';

    #[Url(as: 'supplier', except: '')]
    public string $supplierId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(string $report): void
    {
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404);
        $this->report = $report;
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'type', 'categoryId', 'supplierId', 'from', 'to']);
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    protected function filters(): array
    {
        $filters = [
            'search' => trim($this->search),
            'status' => $this->status,
            'type' => $this->type,
            'category_id' => $this->categoryId,
            'supplier_id' => $this->supplierId,
            'from' => $this->from,
            'to' => $this->to,
        ];

        return array_filter($filters, fn (string $value): bool => $value !== '');
    }

    public function render(): View
    {
        $reports = app(ReportService::class);
        $filters = $this->filters();
        $validator = validator($filters, ReportFilterRequest::rulesFor());

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
            $filters = array_diff_key($filters, $validator->errors()->toArray());
        } else {
            $this->resetErrorBag();
        }

        $query = $reports->query($this->report, $filters);

        $summary = match ($this->report) {
            'purchase-orders' => [
                'orders' => (clone $query)->toBase()->getCountForPagination(),
                'value' => (clone $query)->reorder()->sum('total_amount'),
            ],
            default => null,
        };

        return view('dashboard-reports::livewire.report-table', [
            'rows' => $query->paginate(20),
            'summary' => $summary,
            'statusOptions' => ReportService::statusOptions($this->report),
            'categories' => in_array($this->report, ['current-stock', 'low-stock', 'expiry'], true) ? Category::query()->orderBy('category_name')->get() : collect(),
            'suppliers' => $this->report === 'purchase-orders' ? Supplier::query()->orderBy('supplier_name')->get(['supplier_id', 'supplier_name']) : collect(),
            'items' => collect(),
            'exportUrl' => route('reports.export', ['report' => $this->report] + $filters),
            'hasDateRange' => in_array($this->report, ['expiry', 'stock-movements', 'purchase-orders'], true),
        ]);
    }
}
