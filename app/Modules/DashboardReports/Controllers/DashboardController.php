<?php

namespace App\Modules\DashboardReports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard-reports::dashboard');
    }
}
