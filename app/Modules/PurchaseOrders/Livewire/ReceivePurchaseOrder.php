<?php

namespace App\Modules\PurchaseOrders\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithToasts;
use App\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Requests\ReceivePurchaseOrderRequest;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use App\Services\Decimal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReceivePurchaseOrder extends Component
{
    use WithToasts;

    #[Locked]
    public int $purchaseOrderId;

    public string $received_date = '';

    /** @var array<int, array{batch_number: string, quantity: string, expiration_date: string}> keyed by po_item_id */
    public array $lines = [];

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->authorize('receive', $purchaseOrder);

        $this->purchaseOrderId = $purchaseOrder->purchase_order_id;
        $this->received_date = today()->toDateString();

        foreach ($purchaseOrder->items()->with('supplierItem.item')->get() as $poItem) {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $poItem->supplierItem->item->item_name), 0, 3)) ?: 'ITM';

            $this->lines[$poItem->po_item_id] = [
                'batch_number' => sprintf('PO%05d-%s', $purchaseOrder->purchase_order_id, $prefix),
                'quantity' => Decimal::formatQuantity($poItem->quantity_ordered),
                'expiration_date' => '',
            ];
        }
    }

    public function receive(PurchaseOrderService $orders): void
    {
        $order = PurchaseOrder::query()->with('items.supplierItem.item')->findOrFail($this->purchaseOrderId);
        $this->authorize('receive', $order);

        foreach ($this->lines as $poItemId => $line) {
            $this->lines[$poItemId]['quantity'] = str_replace(',', '', trim((string) $line['quantity']));
            $this->lines[$poItemId]['batch_number'] = trim((string) $line['batch_number']);
        }

        $data = $this->validate(ReceivePurchaseOrderRequest::rulesFor(), [], ReceivePurchaseOrderRequest::attributeNames());

        $hasErrors = false;

        foreach ($order->items as $poItem) {
            $line = $data['lines'][$poItem->po_item_id] ?? null;
            $item = $poItem->supplierItem->item;

            if ($line === null) {
                $this->addError("lines.{$poItem->po_item_id}.quantity", 'Every ordered item must be received.');
                $hasErrors = true;

                continue;
            }

            if ($item->expiry_tracking && blank($line['expiration_date'] ?? null)) {
                $this->addError("lines.{$poItem->po_item_id}.expiration_date", "An expiration date is required for {$item->item_name}.");
                $hasErrors = true;
            }

            if (Decimal::compare($line['quantity'], $poItem->quantity_ordered) !== 0) {
                $this->addError("lines.{$poItem->po_item_id}.quantity", 'Full-order receiving: must equal the ordered quantity ('.Decimal::formatQuantity($poItem->quantity_ordered)." {$item->unit}).");
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            return;
        }

        try {
            $order = $orders->receive($order, $data['lines'], auth()->user(), $data['received_date']);
        } catch (BusinessRuleException $exception) {
            $this->toast($exception->getMessage(), 'error');
            $this->addError('receive', $exception->getMessage());

            return;
        }

        $this->flashToast("{$order->reference()} received. {$order->items->count()} batch(es) added to inventory.");
        $this->redirectRoute('purchase-orders.show', $order);
    }

    public function render(): View
    {
        return view('purchase-orders::livewire.receive-purchase-order', [
            'order' => PurchaseOrder::query()->with(['supplier', 'items.supplierItem.item'])->findOrFail($this->purchaseOrderId),
        ]);
    }
}
