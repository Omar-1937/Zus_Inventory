<?php

namespace App\Modules\Inventory\Livewire;

use App\Enums\StockStatus;
use App\Livewire\Concerns\WithAppPagination;
use App\Livewire\Concerns\WithSorting;
use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Modules\Inventory\Services\InventoryItemService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

class InventoryTable extends Component
{
    use WithAppPagination;
    use WithSorting;
    use WithToasts;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'category', except: '')]
    public string $categoryId = '';

    /** '' (all active), IN_STOCK, LOW_STOCK, OUT_OF_STOCK, INACTIVE, ALL */
    #[Url(except: '')]
    public string $status = '';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->sortField = $this->sortField ?: 'item_name';
    }

    protected function sortableFields(): array
    {
        return ['item_id', 'item_name', 'category_name', 'physical_quantity', 'usable_quantity', 'minimum_stock'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryId', 'status']);
        $this->resetPage();
    }

    public function toggleActive(int $itemId, InventoryItemService $service): void
    {
        $item = InventoryItem::query()->findOrFail($itemId);
        $this->authorize('toggleActive', $item);

        $service->setActive($item, ! $item->is_active, auth()->user());

        $this->toast($item->is_active
            ? "{$item->item_name} has been reactivated."
            : "{$item->item_name} has been deactivated. Its history is preserved.");
    }

    public function render(): View
    {
        $query = InventoryItem::query()
            ->withStockLevels()
            ->addSelect(['category_name' => Category::query()->select('category_name')->whereColumn('categories.category_id', 'inventory_items.category_id')])
            ->with('category')
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('inventory_items.item_name', 'like', "%{$search}%")
                        ->orWhere('inventory_items.description', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhere('inventory_items.item_id', (int) $search);
                    }
                });
            })
            ->when($this->categoryId !== '', fn (Builder $query) => $query->where('inventory_items.category_id', (int) $this->categoryId));

        match ($this->status) {
            'INACTIVE' => $query->where('inventory_items.is_active', false),
            'ALL' => null,
            StockStatus::LowStock->value => $query->active()->lowStock(),
            StockStatus::OutOfStock->value => $query->active()->outOfStock(),
            StockStatus::InStock->value => $query->active()
                ->whereNotIn('inventory_items.item_id', InventoryItem::query()->lowStock()->select('item_id'))
                ->whereNotIn('inventory_items.item_id', InventoryItem::query()->outOfStock()->select('item_id')),
            default => $query->active(),
        };

        $sortField = $this->resolvedSortField('item_name');
        $query->orderBy(in_array($sortField, ['item_id', 'item_name', 'minimum_stock'], true) ? "inventory_items.{$sortField}" : $sortField, $this->resolvedSortDirection())
            ->orderBy('inventory_items.item_id');

        return view('inventory::livewire.inventory-table', [
            'items' => $query->paginate(in_array($this->perPage, [10, 25, 50], true) ? $this->perPage : 10),
            'categories' => Category::query()->orderBy('category_name')->get(),
        ]);
    }
}
