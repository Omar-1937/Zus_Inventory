<?php

namespace App\Modules\Inventory\Livewire;

use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Modules\Inventory\Requests\InventoryItemRequest;
use App\Modules\Inventory\Services\InventoryItemService;
use App\Services\Decimal;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ItemForm extends Component
{
    use WithToasts;

    public ?int $itemId = null;

    public string $category_id = '';

    public string $item_name = '';

    public string $description = '';

    public string $unit = '';

    public string $minimum_stock = '0';

    public bool $expiry_tracking = false;

    public function mount(?InventoryItem $item = null): void
    {
        if ($item?->exists) {
            $this->authorize('update', $item);
            $this->itemId = $item->item_id;
            $this->category_id = (string) $item->category_id;
            $this->item_name = $item->item_name;
            $this->description = (string) $item->description;
            $this->unit = $item->unit;
            $this->minimum_stock = Decimal::formatQuantity($item->minimum_stock);
            $this->expiry_tracking = $item->expiry_tracking;
        } else {
            $this->authorize('create', InventoryItem::class);
        }
    }

    public function updated(string $property): void
    {
        $rules = InventoryItemRequest::rulesFor($this->itemId);

        if (array_key_exists($property, $rules)) {
            $this->validateOnly($property, $rules, [], InventoryItemRequest::attributeNames());
        }
    }

    public function save(InventoryItemService $service): void
    {
        $this->minimum_stock = str_replace(',', '', trim($this->minimum_stock));

        $data = $this->validate(InventoryItemRequest::rulesFor($this->itemId), [], InventoryItemRequest::attributeNames());

        if ($this->itemId !== null) {
            $item = InventoryItem::query()->findOrFail($this->itemId);
            $this->authorize('update', $item);
            $item = $service->update($item, $data, auth()->user());
            $this->flashToast("{$item->item_name} has been updated.");
        } else {
            $this->authorize('create', InventoryItem::class);
            $item = $service->create($data, auth()->user());
            $this->flashToast("{$item->item_name} has been added to inventory.");
        }

        $this->redirectRoute('inventory.show', $item);
    }

    public function render(): View
    {
        return view('inventory::livewire.item-form', [
            'categories' => Category::query()->orderBy('category_name')->get(),
            'unitSuggestions' => ['L', 'ml', 'kg', 'g', 'pcs', 'bottle', 'pack', 'box', 'carton'],
        ]);
    }
}
