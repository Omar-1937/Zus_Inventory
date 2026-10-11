<?php

namespace App\Modules\PurchaseOrders\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithToasts;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Modules\PurchaseOrders\Requests\StorePurchaseOrderRequest;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use App\Services\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The form only ever sends supplier_item_id + quantity. Unit prices, subtotals
 * and the total shown here are a server-side preview; the saved values are
 * recalculated from supplier_items by PurchaseOrderService::create().
 */
class CreatePurchaseOrder extends Component
{
    use WithToasts;

    /** Item the user came from (e.g. a low-stock alert). */
    #[Locked]
    public ?int $requestedItemId = null;

    public string $supplier_id = '';

    /** @var array<int, array{supplier_item_id: string, quantity: string}> */
    public array $lines = [];

    public string $notes = '';

    public function mount(?int $itemId = null, ?int $supplierId = null): void
    {
        $this->authorize('create', PurchaseOrder::class);

        if ($itemId !== null && InventoryItem::query()->active()->whereKey($itemId)->exists()) {
            $this->requestedItemId = $itemId;
        }

        if ($supplierId !== null && Supplier::query()->active()->whereKey($supplierId)->exists()) {
            $this->supplier_id = (string) $supplierId;
            $this->resetLines();
        }
    }

    #[Computed]
    public function requestedItem(): ?InventoryItem
    {
        return $this->requestedItemId
            ? InventoryItem::query()->withStockLevels()->find($this->requestedItemId)
            : null;
    }

    /**
     * Active suppliers. When an item was requested, suppliers that provide it
     * are listed first together with their price for that item.
     *
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::query()
            ->active()
            ->when($this->requestedItemId, fn (Builder $query) => $query->with(['supplierItems' => fn ($query) => $query->active()->where('item_id', $this->requestedItemId)]))
            ->orderBy('supplier_name')
            ->get()
            ->sortByDesc(fn (Supplier $supplier): int => $this->requestedItemId && $supplier->relationLoaded('supplierItems') && $supplier->supplierItems->isNotEmpty() ? 1 : 0)
            ->values();
    }

    /**
     * Products the selected supplier offers (only active items).
     *
     * @return Collection<int, SupplierItem>
     */
    #[Computed]
    public function supplierItems(): Collection
    {
        if ($this->supplier_id === '') {
            return new Collection;
        }

        return SupplierItem::query()
            ->with('item')
            ->where('supplier_id', (int) $this->supplier_id)
            ->active()
            ->whereHas('item', fn (Builder $query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (SupplierItem $supplierItem): string => $supplierItem->item->item_name)
            ->values();
    }

    public function updatedSupplierId(): void
    {
        $this->resetValidation();
        unset($this->supplierItems);
        $this->resetLines();
    }

    public function addLine(): void
    {
        $this->lines[] = ['supplier_item_id' => '', 'quantity' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    /**
     * @return array{lines: array<int, array{supplier_item_id: int, unit_price: string, quantity: string, subtotal: string}>, total: string}
     */
    private function quote(PurchaseOrderService $orders): array
    {
        $supplier = $this->supplier_id !== '' ? Supplier::query()->find((int) $this->supplier_id) : null;

        if ($supplier === null) {
            return ['lines' => [], 'total' => '0.00'];
        }

        return $orders->quote($supplier, array_values(array_filter($this->lines, fn (array $line): bool => $line['supplier_item_id'] !== '')));
    }

    public function save(PurchaseOrderService $orders): void
    {
        $this->authorize('create', PurchaseOrder::class);

        $this->lines = array_values(array_filter($this->lines, fn (array $line): bool => $line['supplier_item_id'] !== '' || $line['quantity'] !== ''));

        foreach ($this->lines as $index => $line) {
            $this->lines[$index]['quantity'] = str_replace(',', '', trim((string) $line['quantity']));
        }

        $data = $this->validate(
            StorePurchaseOrderRequest::rulesFor($this->supplier_id !== '' ? (int) $this->supplier_id : null),
            StorePurchaseOrderRequest::messageList(),
            StorePurchaseOrderRequest::attributeNames(),
        );

        if ($this->lines === []) {
            $this->addLine();
        }

        try {
            $order = $orders->create(
                Supplier::query()->findOrFail((int) $data['supplier_id']),
                array_map(fn (array $line): array => [
                    'supplier_item_id' => (int) $line['supplier_item_id'],
                    'quantity' => $line['quantity'],
                ], $data['lines']),
                $data['notes'] ?? null,
                auth()->user(),
            );
        } catch (BusinessRuleException $exception) {
            $this->addError('lines', $exception->getMessage());
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->flashToast("{$order->reference()} created for ".Decimal::formatMoney($order->total_amount).'. Status: PENDING approval.');
        $this->redirectRoute('purchase-orders.show', $order);
    }

    private function resetLines(): void
    {
        $this->lines = [['supplier_item_id' => '', 'quantity' => '']];

        if ($this->requestedItemId === null || $this->supplier_id === '') {
            return;
        }

        $match = SupplierItem::query()
            ->where('supplier_id', (int) $this->supplier_id)
            ->where('item_id', $this->requestedItemId)
            ->active()
            ->first();

        if ($match !== null) {
            $this->lines = [['supplier_item_id' => (string) $match->supplier_item_id, 'quantity' => '']];
        }
    }

    public function render(): View
    {
        $quote = $this->quote(app(PurchaseOrderService::class));
        $quotedLines = collect($quote['lines'])->keyBy('supplier_item_id');

        $suggestedQuantity = null;

        if ($this->requestedItem && $this->requestedItem->isLowStock()) {
            $suggestedQuantity = Decimal::sub($this->requestedItem->minimum_stock, $this->requestedItem->usableQuantity());
        }

        return view('purchase-orders::livewire.create-purchase-order', [
            'quote' => $quote,
            'quotedLines' => $quotedLines,
            'suggestedQuantity' => $suggestedQuantity,
            'requestedItemSupplied' => $this->requestedItemId !== null
                && $this->supplierItems->contains(fn (SupplierItem $supplierItem): bool => $supplierItem->item_id === $this->requestedItemId),
        ]);
    }
}
