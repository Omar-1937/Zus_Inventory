<?php

namespace App\Modules\Suppliers\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Supplier::class);

        return view('suppliers::index');
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('suppliers::form', ['supplier' => null]);
    }

    public function show(Supplier $supplier): View
    {
        Gate::authorize('view', $supplier);

        $orders = $supplier->purchaseOrders()->with('creator')->withCount('items')->latest('order_date')->latest('purchase_order_id');

        return view('suppliers::show', [
            'supplier' => $supplier,
            'purchaseOrders' => $orders->paginate(10, ['*'], 'orders_page'),
            'stats' => [
                'orders' => $supplier->purchaseOrders()->count(),
                'received_value' => $supplier->purchaseOrders()->where('status', PurchaseOrderStatus::Received)->sum('total_amount'),
                'open_orders' => $supplier->purchaseOrders()->whereIn('status', [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Ordered])->count(),
                'products' => $supplier->supplierItems()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('suppliers::form', ['supplier' => $supplier]);
    }
}
