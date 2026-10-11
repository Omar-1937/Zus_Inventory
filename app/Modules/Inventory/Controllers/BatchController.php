<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', InventoryBatch::class);

        return view('inventory::batches.index');
    }
}
