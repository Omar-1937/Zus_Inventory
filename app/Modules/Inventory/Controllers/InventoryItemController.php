<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', InventoryItem::class);

        return view('inventory::items.index');
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryItem::class);

        return view('inventory::items.form', ['item' => null]);
    }

    public function show(InventoryItem $item): View
    {
        Gate::authorize('view', $item);

        $item = InventoryItem::query()->withStockLevels()->with('category')->findOrFail($item->item_id);

        return view('inventory::items.show', [
            'item' => $item,
            'batches' => $item->batches()->orderByRaw('remaining_quantity <= 0')->fefoOrder()->get(),
            'transactions' => $item->stockTransactions()->with(['batch', 'user'])->latest('created_at')->latest('transaction_id')->limit(15)->get(),
            'supplierItems' => $item->supplierItems()->with('supplier')->orderBy('unit_price')->get(),
            'alerts' => $item->notifications()->active()->latest('created_at')->get(),
        ]);
    }

    public function edit(InventoryItem $item): View
    {
        Gate::authorize('update', $item);

        return view('inventory::items.form', ['item' => $item]);
    }
}
