<?php

namespace App\Modules\Inventory\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithToasts;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Modules\Inventory\Requests\StockOutRequest;
use App\Modules\Inventory\Services\StockService;
use App\Services\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class StockOutForm extends Component
{
    use WithToasts;

    public string $item_id = '';

    public string $quantity = '';

    public string $reason = StockOutRequest::REASONS[0];

    public string $notes = '';

    public function mount(?int $itemId = null): void
    {
        $this->authorize('recordMovement', InventoryItem::class);

        if ($itemId !== null && InventoryItem::query()->active()->whereKey($itemId)->exists()) {
            $this->item_id = (string) $itemId;
        }
    }

    #[On('stock-updated')]
    public function refreshStock(): void
    {
        unset($this->selectedItem, $this->usableBatches);
    }

    public function updatedItemId(): void
    {
        $this->resetValidation();
        unset($this->selectedItem, $this->usableBatches);
    }

    #[Computed]
    public function selectedItem(): ?InventoryItem
    {
        return $this->item_id !== ''
            ? InventoryItem::query()->withStockLevels()->find((int) $this->item_id)
            : null;
    }

    /**
     * Usable batches in FEFO order (preview only; the service recalculates under lock).
     *
     * @return Collection<int, InventoryBatch>
     */
    #[Computed]
    public function usableBatches()
    {
        return InventoryBatch::query()
            ->where('item_id', (int) $this->item_id)
            ->usable()
            ->fefoOrder()
            ->get();
    }

    /**
     * Which batches the requested quantity would be taken from.
     *
     * @return array<int, array{batch: InventoryBatch, take: string}>
     */
    private function allocationPreview(): array
    {
        if ($this->item_id === '' || ! is_numeric($this->quantity) || ! Decimal::isPositive($this->quantity)) {
            return [];
        }

        $outstanding = Decimal::quantity($this->quantity);
        $preview = [];

        foreach ($this->usableBatches as $batch) {
            if (! Decimal::isPositive($outstanding)) {
                break;
            }

            $take = Decimal::quantity(Decimal::min($batch->remaining_quantity, $outstanding));
            $preview[] = ['batch' => $batch, 'take' => $take];
            $outstanding = Decimal::quantity(Decimal::sub($outstanding, $take));
        }

        return $preview;
    }

    public function save(StockService $stock): void
    {
        $this->authorize('recordMovement', InventoryItem::class);
        $this->quantity = str_replace(',', '', trim($this->quantity));

        $data = $this->validate(StockOutRequest::rulesFor(), [], StockOutRequest::attributeNames());
        $item = InventoryItem::query()->findOrFail((int) $data['item_id']);

        try {
            $transactions = $stock->stockOut($item, $data['quantity'], $data['reason'], $data['notes'] ?? null, auth()->user());
        } catch (BusinessRuleException $exception) {
            $this->addError('quantity', $exception->getMessage());
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->toast(sprintf(
            'Stock out recorded: %s %s of %s from %d batch(es) (FEFO).',
            Decimal::formatQuantity($data['quantity']),
            $item->unit,
            $item->item_name,
            $transactions->count(),
        ));
        $this->dispatch('stock-updated');

        $this->reset(['quantity', 'notes']);
        unset($this->selectedItem, $this->usableBatches);
    }

    public function render(): View
    {
        return view('inventory::livewire.stock-out-form', [
            'items' => InventoryItem::query()->active()->orderBy('item_name')->get(['item_id', 'item_name', 'unit']),
            'reasons' => StockOutRequest::REASONS,
            'preview' => $this->allocationPreview(),
        ]);
    }
}
