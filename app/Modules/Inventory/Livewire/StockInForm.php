<?php

namespace App\Modules\Inventory\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithToasts;
use App\Models\InventoryItem;
use App\Modules\Inventory\Requests\StockInRequest;
use App\Modules\Inventory\Services\StockService;
use App\Services\Decimal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class StockInForm extends Component
{
    use WithToasts;

    public string $item_id = '';

    public string $batch_number = '';

    public string $quantity = '';

    public string $received_date = '';

    public string $expiration_date = '';

    public string $notes = '';

    public function mount(?int $itemId = null): void
    {
        $this->authorize('recordMovement', InventoryItem::class);
        $this->received_date = today()->toDateString();

        if ($itemId !== null && InventoryItem::query()->active()->whereKey($itemId)->exists()) {
            $this->item_id = (string) $itemId;
            $this->batch_number = $this->suggestBatchNumber();
        }
    }

    public function updatedItemId(): void
    {
        $this->batch_number = $this->item_id !== '' ? $this->suggestBatchNumber() : '';
        $this->resetValidation();
    }

    #[Computed]
    public function selectedItem(): ?InventoryItem
    {
        return $this->item_id !== ''
            ? InventoryItem::query()->withStockLevels()->find((int) $this->item_id)
            : null;
    }

    public function save(StockService $stock): void
    {
        $this->authorize('recordMovement', InventoryItem::class);
        $this->quantity = str_replace(',', '', trim($this->quantity));

        $data = $this->validate(StockInRequest::rulesFor(), StockInRequest::messageList(), StockInRequest::attributeNames());

        $item = InventoryItem::query()->findOrFail((int) $data['item_id']);

        if ($item->expiry_tracking && blank($data['expiration_date'] ?? null)) {
            $this->addError('expiration_date', "{$item->item_name} tracks expiry, so an expiration date is required.");

            return;
        }

        try {
            $batch = $stock->stockIn($item, $data, auth()->user());
        } catch (BusinessRuleException $exception) {
            $this->addError('batch_number', $exception->getMessage());
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->toast(sprintf('Stock in recorded: %s %s of %s (batch %s).', Decimal::formatQuantity($batch->initial_quantity), $item->unit, $item->item_name, $batch->batch_number));
        $this->dispatch('stock-updated');

        $this->reset(['quantity', 'expiration_date', 'notes']);
        $this->batch_number = $this->suggestBatchNumber();
        unset($this->selectedItem);
    }

    private function suggestBatchNumber(): string
    {
        $item = InventoryItem::query()->find((int) $this->item_id);

        if ($item === null) {
            return '';
        }

        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $item->item_name), 0, 3)) ?: 'BAT';
        $base = $prefix.'-'.today()->format('Ymd');
        $sequence = $item->batches()->where('batch_number', 'like', $base.'%')->count() + 1;

        return $base.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    public function render(): View
    {
        return view('inventory::livewire.stock-in-form', [
            'items' => InventoryItem::query()->active()->orderBy('item_name')->get(['item_id', 'item_name', 'unit']),
        ]);
    }
}
