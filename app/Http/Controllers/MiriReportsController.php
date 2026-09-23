<?php

namespace App\Http\Controllers;

use App\Services\BranchContext;
use App\Services\CidbInventoryReport;
use App\Services\CidbReportWorkbook;
use App\Services\PaintInventoryReport;
use App\Services\PaintReportWorkbook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MiriReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeReport($request);

        return Inertia::render('MiriReports/Index');
    }

    public function bintuluPaint(Request $request): Response
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = $request->boolean('preview') ? app(PaintInventoryReport::class)->generate($branch, $filters) : null;

        return Inertia::render('MiriReports/BintuluPaint', ['filters' => $filters, 'report' => $report, 'columns' => PaintInventoryReport::COLUMNS]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = app(PaintInventoryReport::class)->generate($branch, $filters);
        abort_if($report['unavailable'] !== null, 422, $report['unavailable'] ?? 'Report unavailable.');
        $path = app(PaintReportWorkbook::class)->create($report, $filters);

        return response()->download($path, "bintulu-yard-paint-inventory-report-{$filters['month']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function bintuluCidb(Request $request): Response
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = $request->boolean('preview') ? app(CidbInventoryReport::class)->generate($branch, $filters['month']) : null;

        return Inertia::render('MiriReports/BintuluCidb', ['filters' => $filters, 'report' => $report, 'columns' => CidbInventoryReport::COLUMNS]);
    }

    public function exportCidb(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = app(CidbInventoryReport::class)->generate($branch, $filters['month']);
        abort_if($report['unavailable'] !== null, 422, $report['unavailable'] ?? 'Report unavailable.');
        $path = app(CidbReportWorkbook::class)->create($report, $filters);

        return response()->download($path, "bintulu-yard-cidb-training-item-inventory-report-{$filters['month']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    private function authorizeReport(Request $request): int
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        abort_unless($request->user()?->canRead('assets'), 403);

        return $branch->id;
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['sometimes', 'required', 'date_format:Y-m', 'before_or_equal:'.now('Asia/Kuala_Lumpur')->format('Y-m')],
            'location' => ['sometimes', 'required', 'in:BTU'],
            'brand' => ['sometimes', 'required', 'in:all,IP Paint,Hempel Paint'],
        ]);

        return [...['month' => now('Asia/Kuala_Lumpur')->format('Y-m'), 'location' => 'BTU', 'brand' => 'all'], ...$validated];
    }
}
