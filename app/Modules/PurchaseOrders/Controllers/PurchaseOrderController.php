<?php

namespace App\Modules\PurchaseOrders\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Requests\PurchaseOrderStatusRequest;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orders) {}

    public function index(): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        return view('purchase-orders::index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        return view('purchase-orders::create', [
            'itemId' => $request->integer('item_id') ?: null,
            'supplierId' => $request->integer('supplier_id') ?: null,
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'creator', 'approver', 'receiver', 'items.supplierItem.item', 'items.batches']);

        return view('purchase-orders::show', [
            'order' => $purchaseOrder,
            'activity' => AuditLog::query()
                ->with('user')
                ->where('entity_name', 'PurchaseOrder')
                ->where('record_id', $purchaseOrder->purchase_order_id)
                ->oldest('created_at')
                ->oldest('log_id')
                ->get(),
        ]);
    }

    public function approveForm(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        Gate::authorize('approve', $purchaseOrder);

        if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('toast', ['type' => 'warning', 'message' => "{$purchaseOrder->reference()} is {$purchaseOrder->status->value}; only PENDING orders can be approved."]);
        }

        $purchaseOrder->load(['supplier', 'creator', 'items.supplierItem.item']);

        return view('purchase-orders::approve', ['order' => $purchaseOrder]);
    }

    public function approve(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->runTransition(
            fn () => $this->orders->approve($purchaseOrder, $request->user()),
            $purchaseOrder,
            "{$purchaseOrder->reference()} approved. Mark it as ORDERED once it has been sent to the supplier.",
        );
    }

    public function markOrdered(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->runTransition(
            fn () => $this->orders->markOrdered($purchaseOrder, $request->user()),
            $purchaseOrder,
            "{$purchaseOrder->reference()} marked as ORDERED. It can now be received when the goods arrive.",
        );
    }

    public function cancel(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        return $this->runTransition(
            fn () => $this->orders->cancel($purchaseOrder, $request->user(), $request->validated('reason')),
            $purchaseOrder,
            "{$purchaseOrder->reference()} has been cancelled.",
        );
    }

    public function receiveForm(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        Gate::authorize('receive', $purchaseOrder);

        if ($purchaseOrder->status !== PurchaseOrderStatus::Ordered) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('toast', ['type' => 'warning', 'message' => $purchaseOrder->status === PurchaseOrderStatus::Received
                    ? "{$purchaseOrder->reference()} has already been received."
                    : "Only ORDERED purchase orders can be received. {$purchaseOrder->reference()} is {$purchaseOrder->status->value}."]);
        }

        return view('purchase-orders::receive', ['order' => $purchaseOrder]);
    }

    private function runTransition(callable $action, PurchaseOrder $purchaseOrder, string $successMessage): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessRuleException $exception) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('toast', ['type' => 'success', 'message' => $successMessage]);
    }
}
