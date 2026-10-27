<?php

namespace App\Http\Controllers;

use App\Services\{BranchContext, ScaffoldingMaterialsReport, ScaffoldingReportWorkbook};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ScaffoldingReportController extends Controller
{
    public function show(Request $request, ScaffoldingMaterialsReport $service)
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        abort_unless($request->user()?->canRead('assets'), 403);
        $filters = $request->validate(['as_of' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Kuala_Lumpur')->format('Y-m-d')],
            'company' => ['nullable', 'in:DESB,FTSB,unassigned'], 'format' => ['nullable', 'in:xlsx,pdf']]);
        $filters = ['as_of' => $filters['as_of'] ?? now('Asia/Kuala_Lumpur')->format('Y-m-d'), 'company' => $filters['company'] ?? '', 'format' => $filters['format'] ?? null];
        $report = $service->generate($branch->id, $filters);
        if ($filters['format'] === 'xlsx') {
            $path = app(ScaffoldingReportWorkbook::class)->create($report);
            return response()->download($path, 'scaffolding-materials-'.$filters['as_of'].'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
        }
        if ($filters['format'] === 'pdf') {
            // Split wide matrices into readable panels, repeating all location rows and headings.
            $panels = array_chunk($report['columns'], 10);
            return Pdf::loadView('miri-reports.scaffolding', ['report' => $report, 'panels' => $panels ?: [[]]])
                ->setPaper('a3', 'landscape')->download('scaffolding-materials-'.$filters['as_of'].'.pdf');
        }
        return Inertia::render('MiriReports/Scaffolding', ['filters' => $filters, 'report' => $report]);
    }
}
