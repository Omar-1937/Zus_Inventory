<?php

namespace App\Modules\PurchaseOrders\Livewire;

use App\Enums\PurchaseOrderStatus;
use App\Livewire\Concerns\WithAppPagination;
use App\Livewire\Concerns\WithSorting;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

class PurchaseOrderTable extends Component
{
    use WithAppPagination;
    use WithSorting;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'supplier', except: '')]
    public string $supplierId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $this->sortField = $this->sortField ?: 'purchase_order_id';
        $this->sortDirection = $this->sortField === 'purchase_order_id' && ! request()->has('dir') ? 'desc' : $this->sortDirection;
    }

    protected function sortableFields(): array
    {
        return ['purchase_order_id', 'order_date', 'total_amount', 'status'];
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'supplierId', 'from', 'to']);
        $this->resetPage();
    }

    public function render(): View
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'creator'])
            ->withCount('items')
            ->when($this->status !== '' && PurchaseOrderStatus::tryFrom($this->status), fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->supplierId !== '', fn (Builder $query) => $query->where('supplier_id', (int) $this->supplierId))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('order_date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('order_date', '<=', $this->to))
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);
                $id = (int) preg_replace('/\D/', '', $search);
                $query->where(function (Builder $query) use ($search, $id): void {
                    $query->whereHas('supplier', fn (Builder $query) => $query->where('supplier_name', 'like', "%{$search}%"))
                        ->orWhereHas('items.supplierItem.item', fn (Builder $query) => $query->where('item_name', 'like', "%{$search}%"));

                    if ($id > 0) {
                        $query->orWhere('purchase_order_id', $id);
                    }
                });
            })
            ->orderBy($this->resolvedSortField('purchase_order_id'), $this->resolvedSortDirection())
            ->orderByDesc('purchase_order_id')
            ->paginate(15);

        return view('purchase-orders::livewire.purchase-order-table', [
            'orders' => $orders,
            'suppliers' => Supplier::query()->orderBy('supplier_name')->get(['supplier_id', 'supplier_name']),
            'statuses' => PurchaseOrderStatus::cases(),
        ]);
    }
}
