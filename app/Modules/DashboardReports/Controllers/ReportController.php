<?php

namespace App\Modules\DashboardReports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DashboardReports\Requests\ReportFilterRequest;
use App\Modules\DashboardReports\Services\ReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function show(string $report): View
    {
        return view('dashboard-reports::reports.show', [
            'report' => $report,
            'title' => ReportService::REPORTS[$report],
        ]);
    }

    public function export(ReportFilterRequest $request, string $report): StreamedResponse
    {
        return $this->reports->exportCsv($report, $request->filters());
    }
}
