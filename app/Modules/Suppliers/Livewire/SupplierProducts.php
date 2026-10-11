<?php

namespace App\Modules\Suppliers\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithAppPagination;
use App\Livewire\Concerns\WithToasts;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Modules\Suppliers\Requests\SupplierItemRequest;
use App\Modules\Suppliers\Services\SupplierPricingService;
use App\Services\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Supplier products & prices. Used on the supplier details page (fixed
 * supplier) and on the global "Supplier Products & Prices" page.
 */
class SupplierProducts extends Component
{
    use WithAppPagination;
    use WithToasts;

    /** When set, the component only manages this supplier's products. */
    #[Locked]
    public ?int $fixedSupplierId = null;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'supplier', except: '')]
    public string $supplierFilter = '';

    #[Url(as: 'item', except: '')]
    public string $itemFilter = '';

    public bool $showAssign = false;

    public string $supplier_id = '';

    public string $item_id = '';

    public string $unit_price = '';

    public ?int $editingId = null;

    public string $editPrice = '';

    public function mount(?Supplier $supplier = null): void
    {
        $this->authorize('viewAny', Supplier::class);
        $this->fixedSupplierId = $supplier?->exists ? $supplier->supplier_id : null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'supplierFilter', 'itemFilter'], true)) {
            $this->resetPage();
        }
    }

    public function openAssign(): void
    {
        $this->authorize('managePrices', Supplier::class);
        $this->resetValidation();
        $this->reset(['item_id', 'unit_price']);
        $this->supplier_id = (string) ($this->fixedSupplierId ?? ($this->supplierFilter !== '' ? $this->supplierFilter : ''));
        $this->item_id = $this->itemFilter;
        $this->showAssign = true;
    }

    public function assign(SupplierPricingService $pricing): void
    {
        $this->authorize('managePrices', Supplier::class);

        if ($this->fixedSupplierId !== null) {
            $this->supplier_id = (string) $this->fixedSupplierId;
        }

        $data = $this->validate(
            SupplierItemRequest::rulesFor($this->supplier_id !== '' ? (int) $this->supplier_id : null),
            SupplierItemRequest::messageList(),
            SupplierItemRequest::attributeNames(),
        );

        $supplier = Supplier::query()->findOrFail((int) $data['supplier_id']);
        $item = InventoryItem::query()->findOrFail((int) $data['item_id']);

        try {
            $pricing->assignItem($supplier, $item, $data['unit_price'], auth()->user());
        } catch (BusinessRuleException $exception) {
            $this->addError('item_id', $exception->getMessage());

            return;
        }

        $this->showAssign = false;
        $this->toast("{$item->item_name} assigned to {$supplier->supplier_name} at ".Decimal::formatMoney($data['unit_price']).'.');
    }

    public function startEdit(int $supplierItemId): void
    {
        $this->authorize('managePrices', Supplier::class);
        $supplierItem = SupplierItem::query()->findOrFail($supplierItemId);

        $this->resetValidation();
        $this->editingId = $supplierItem->supplier_item_id;
        $this->editPrice = Decimal::money($supplierItem->unit_price);
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editPrice']);
        $this->resetValidation();
    }

    public function savePrice(SupplierPricingService $pricing): void
    {
        $this->authorize('managePrices', Supplier::class);
        $this->validate(['editPrice' => SupplierItemRequest::priceRules()], [], ['editPrice' => 'unit price']);

        $supplierItem = SupplierItem::query()->with(['supplier', 'item'])->findOrFail($this->editingId);

        try {
            $pricing->updatePrice($supplierItem, $this->editPrice, auth()->user());
        } catch (BusinessRuleException $exception) {
            $this->addError('editPrice', $exception->getMessage());

            return;
        }

        $this->toast("Price of {$supplierItem->item->item_name} from {$supplierItem->supplier->supplier_name} updated to ".Decimal::formatMoney($this->editPrice).'. Existing purchase orders keep their original price.');
        $this->cancelEdit();
    }

    public function toggle(int $supplierItemId, SupplierPricingService $pricing): void
    {
        $this->authorize('managePrices', Supplier::class);
        $supplierItem = SupplierItem::query()->with('item')->findOrFail($supplierItemId);

        $pricing->setActive($supplierItem, ! $supplierItem->is_active, auth()->user());

        $this->toast($supplierItem->is_active
            ? "{$supplierItem->item->item_name} is available from this supplier again."
            : "{$supplierItem->item->item_name} is no longer offered by this supplier.");
    }

    public function remove(int $supplierItemId, SupplierPricingService $pricing): void
    {
        $this->authorize('managePrices', Supplier::class);
        $supplierItem = SupplierItem::query()->with('item')->findOrFail($supplierItemId);

        $deleted = $pricing->remove($supplierItem, auth()->user());

        $this->toast($deleted
            ? "{$supplierItem->item->item_name} removed from this supplier."
            : "{$supplierItem->item->item_name} has purchase history, so it was deactivated instead of deleted.", $deleted ? 'success' : 'info');
    }

    public function render(): View
    {
        $supplierItems = SupplierItem::query()
            ->with(['supplier', 'item.category'])
            ->join('suppliers', 'suppliers.supplier_id', '=', 'supplier_items.supplier_id')
            ->join('inventory_items', 'inventory_items.item_id', '=', 'supplier_items.item_id')
            ->select('supplier_items.*')
            ->when($this->fixedSupplierId !== null, fn (Builder $query) => $query->where('supplier_items.supplier_id', $this->fixedSupplierId))
            ->when($this->fixedSupplierId === null && $this->supplierFilter !== '', fn (Builder $query) => $query->where('supplier_items.supplier_id', (int) $this->supplierFilter))
            ->when($this->itemFilter !== '', fn (Builder $query) => $query->where('supplier_items.item_id', (int) $this->itemFilter))
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(fn (Builder $query) => $query->where('inventory_items.item_name', 'like', "%{$search}%")
                    ->orWhere('suppliers.supplier_name', 'like', "%{$search}%"));
            })
            ->orderBy('inventory_items.item_name')
            ->orderBy('supplier_items.unit_price')
            ->paginate(15);

        $assignSupplierId = $this->fixedSupplierId ?? ($this->supplier_id !== '' ? (int) $this->supplier_id : null);

        return view('suppliers::livewire.supplier-products', [
            'supplierItems' => $supplierItems,
            'suppliers' => Supplier::query()->orderBy('supplier_name')->get(['supplier_id', 'supplier_name', 'is_active']),
            'items' => InventoryItem::query()->active()->orderBy('item_name')->get(['item_id', 'item_name', 'unit']),
            'assignableItems' => InventoryItem::query()
                ->active()
                ->when($assignSupplierId, fn (Builder $query) => $query->whereDoesntHave('supplierItems', fn (Builder $query) => $query->where('supplier_id', $assignSupplierId)))
                ->orderBy('item_name')
                ->get(['item_id', 'item_name', 'unit']),
            'canManage' => auth()->user()->can('managePrices', Supplier::class),
        ]);
    }
}
