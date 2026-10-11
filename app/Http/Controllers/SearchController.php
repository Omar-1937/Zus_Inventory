<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global search from the top navbar across items, suppliers and purchase orders.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $results = ['items' => collect(), 'suppliers' => collect(), 'orders' => collect()];

        if (mb_strlen($term) >= 2 || ctype_digit($term)) {
            $results['items'] = InventoryItem::query()
                ->withStockLevels()
                ->with('category')
                ->where(fn ($query) => $query->where('item_name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"))
                ->orderBy('item_name')
                ->limit(10)
                ->get();

            $results['suppliers'] = Supplier::query()
                ->where(fn ($query) => $query->where('supplier_name', 'like', "%{$term}%")->orWhere('contact_person', 'like', "%{$term}%"))
                ->orderBy('supplier_name')
                ->limit(10)
                ->get();

            $orderId = (int) preg_replace('/\D/', '', $term);
            $results['orders'] = PurchaseOrder::query()
                ->with('supplier')
                ->where(function ($query) use ($term, $orderId): void {
                    $query->whereHas('supplier', fn ($query) => $query->where('supplier_name', 'like', "%{$term}%"));

                    if ($orderId > 0) {
                        $query->orWhere('purchase_order_id', $orderId);
                    }
                })
                ->latest('purchase_order_id')
                ->limit(10)
                ->get();
        }

        return view('search', ['term' => $term, 'results' => $results]);
    }
}
