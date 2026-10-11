<?php

namespace App\Modules\Suppliers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierProductController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Supplier::class);

        return view('suppliers::products');
    }
}
