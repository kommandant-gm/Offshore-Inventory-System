<?php

namespace App\Http\Controllers;

use App\Services\BranchContext;
use App\Services\CidbInventoryReport;
use App\Services\CidbReportWorkbook;
use App\Services\ConsumableInventoryReport;
use App\Services\ConsumableReportWorkbook;
use App\Services\LabuanConsumableInventoryReport;
use App\Services\LabuanConsumableReportWorkbook;
use App\Services\LabuanPaintInventoryReport;
use App\Services\LabuanPaintReportWorkbook;
use App\Services\PaintInventoryReport;
use App\Services\PaintReportWorkbook;
use App\Services\PpeInventoryReport;
use App\Services\PpeReportWorkbook;
use App\Services\RentalSummaryReport;
use App\Services\RentalSummaryWorkbook;
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

    public function bintuluConsumable(Request $request): Response
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = $request->boolean('preview') ? app(ConsumableInventoryReport::class)->generate($branch, $filters['month']) : null;

        return Inertia::render('MiriReports/BintuluConsumable', ['filters' => $filters, 'report' => $report, 'columns' => ConsumableInventoryReport::COLUMNS]);
    }

    public function exportConsumable(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = app(ConsumableInventoryReport::class)->generate($branch, $filters['month']);
        abort_if($report['unavailable'] !== null, 422, $report['unavailable'] ?? 'Report unavailable.');
        $path = app(ConsumableReportWorkbook::class)->create($report, $filters);

        return response()->download($path, "bintulu-yard-consumable-inventory-report-{$filters['month']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function bintuluPpe(Request $request): Response
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = $request->boolean('preview') ? app(PpeInventoryReport::class)->generate($branch, $filters['month']) : null;

        return Inertia::render('MiriReports/BintuluPpe', ['filters' => $filters, 'report' => $report, 'columns' => PpeInventoryReport::COLUMNS]);
    }

    public function exportPpe(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request);
        $report = app(PpeInventoryReport::class)->generate($branch, $filters['month']);
        abort_if($report['unavailable'] !== null, 422, $report['unavailable'] ?? 'Report unavailable.');
        $path = app(PpeReportWorkbook::class)->create($report, $filters);

        return response()->download($path, "bintulu-yard-ppe-inventory-report-{$filters['month']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function labuanConsumable(Request $request): Response
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request, 'LBN');
        $report = $request->boolean('preview') ? app(LabuanConsumableInventoryReport::class)->generate($branch, $filters['month']) : null;

        return Inertia::render('MiriReports/LabuanConsumable', ['filters' => $filters, 'report' => $report, 'columns' => LabuanConsumableInventoryReport::COLUMNS]);
    }

    public function exportLabuanConsumable(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request, 'LBN');
        $report = app(LabuanConsumableInventoryReport::class)->generate($branch, $filters['month']);
        abort_if($report['unavailable'] !== null, 422, $report['unavailable'] ?? 'Report unavailable.');
        $path = app(LabuanConsumableReportWorkbook::class)->create($report, $filters);

        return response()->download($path, "labuan-warehouse-general-store-consumable-inventory-report-{$filters['month']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function labuanPaint(Request $request): Response
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request, 'LBN');
        $report = $request->boolean('preview') ? app(LabuanPaintInventoryReport::class)->generate($branch, $filters) : null;

        return Inertia::render('MiriReports/LabuanPaint', ['filters' => $filters, 'report' => $report, 'columns' => LabuanPaintInventoryReport::COLUMNS]);
    }

    public function exportLabuanPaint(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->filters($request, 'LBN');
        $report = app(LabuanPaintInventoryReport::class)->generate($branch, $filters);
        abort_if($report['unavailable'] !== null, 422, $report['unavailable'] ?? 'Report unavailable.');
        $path = app(LabuanPaintReportWorkbook::class)->create($report, $filters);

        return response()->download($path, "labuan-warehouse-paint-inventory-report-{$filters['month']}.xlsx", [
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

    public function rental(Request $request): Response
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->rentalFilters($request);
        $service = app(RentalSummaryReport::class);

        return Inertia::render('MiriReports/RentalSummary', [
            'filters' => $filters, 'columns' => RentalSummaryReport::COLUMNS, 'options' => $service->options($branch),
            'report' => $request->boolean('preview') ? $service->generate($branch, $filters) : null,
        ]);
    }

    public function exportRental(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $branch = $this->authorizeReport($request);
        $filters = $this->rentalFilters($request);
        $report = app(RentalSummaryReport::class)->generate($branch, $filters);
        abort_if($report['unavailable'] !== null, 422, $report['unavailable'] ?? 'Report unavailable.');
        $path = app(RentalSummaryWorkbook::class)->create($report, $filters);

        return response()->download($path, "equipment-rental-list-summary-{$filters['month']}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    private function rentalFilters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['sometimes', 'required', 'date_format:Y-m', 'before_or_equal:'.now('Asia/Kuala_Lumpur')->format('Y-m')],
            'project' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'],
        ]);

        return ['month' => $validated['month'] ?? now('Asia/Kuala_Lumpur')->format('Y-m'), 'project' => $validated['project'] ?? '', 'location' => $validated['location'] ?? ''];
    }

    private function filters(Request $request, string $location = 'BTU'): array
    {
        $validated = $request->validate([
            'month' => ['sometimes', 'required', 'date_format:Y-m', 'before_or_equal:'.now('Asia/Kuala_Lumpur')->format('Y-m')],
            'location' => ['sometimes', 'required', 'in:'.$location],
            'brand' => ['sometimes', 'required', 'in:all,IP Paint,Hempel Paint'],
        ]);

        return [...['month' => now('Asia/Kuala_Lumpur')->format('Y-m'), 'location' => $location, 'brand' => 'all'], ...$validated];
    }
}
