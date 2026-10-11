<?php

namespace App\Modules\Suppliers\Livewire;

use App\Livewire\Concerns\WithAppPagination;
use App\Livewire\Concerns\WithSorting;
use App\Livewire\Concerns\WithToasts;
use App\Models\Supplier;
use App\Modules\Suppliers\Services\SupplierService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

class SupplierTable extends Component
{
    use WithAppPagination;
    use WithSorting;
    use WithToasts;

    #[Url(except: '')]
    public string $search = '';

    /** active | inactive | all */
    #[Url(except: 'active')]
    public string $status = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', Supplier::class);
        $this->sortField = $this->sortField ?: 'supplier_name';
    }

    protected function sortableFields(): array
    {
        return ['supplier_id', 'supplier_name', 'contact_person', 'supplier_items_count', 'purchase_orders_count'];
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $supplierId, SupplierService $service): void
    {
        $supplier = Supplier::query()->findOrFail($supplierId);
        $this->authorize('toggleActive', $supplier);

        $service->setActive($supplier, ! $supplier->is_active, auth()->user());

        $this->toast($supplier->is_active
            ? "{$supplier->supplier_name} has been reactivated."
            : "{$supplier->supplier_name} has been deactivated. Purchase history is preserved.");
    }

    public function render(): View
    {
        $suppliers = Supplier::query()
            ->withCount([
                'supplierItems' => fn (Builder $query) => $query->where('is_active', true),
                'purchaseOrders',
            ])
            ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('supplier_name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy($this->resolvedSortField('supplier_name'), $this->resolvedSortDirection())
            ->orderBy('supplier_id')
            ->paginate(10);

        return view('suppliers::livewire.supplier-table', ['suppliers' => $suppliers]);
    }
}
