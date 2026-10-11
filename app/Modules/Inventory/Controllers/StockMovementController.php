<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('recordMovement', InventoryItem::class);

        return view('inventory::stock.index', [
            'tab' => $request->query('tab') === 'out' ? 'out' : 'in',
            'itemId' => $request->integer('item_id') ?: null,
        ]);
    }
}
