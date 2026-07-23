<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\ItLicenseImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ItLicenseImportController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        return Inertia::render('ItLicenses/Import', [
            'report' => session('it_license_import_report'),
        ]);
    }

    public function preview(Request $request, ItLicenseImportService $service): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx'],
        ]);

        if ($previousPath = session('it_license_import_path')) {
            Storage::delete($previousPath);
        }

        $path = $request->file('file')->store('it-license-imports');
        $report = $service->analyse(Storage::path($path));

        session([
            'it_license_import_path' => $path,
            'it_license_import_report' => collect($report)->except('valid')->all(),
        ]);

        return back();
    }

    public function store(
        Request $request,
        ItLicenseImportService $service,
        AuditLogger $auditLogger
    ): RedirectResponse {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $path = session('it_license_import_path');
        abort_unless($path && Storage::exists($path), 422, 'Please preview the import file again.');

        $report = $service->import(Storage::path($path));
        Storage::delete($path);
        session()->forget(['it_license_import_path', 'it_license_import_report']);

        $auditLogger->record(
            module: 'it_assets',
            event: 'it_licenses_imported',
            summary: "Imported {$report['ready']} IT licences from a spreadsheet.",
            user: $request->user(),
            request: $request,
        );

        return redirect()
            ->route('it-licenses.index')
            ->with('success', "Imported {$report['ready']} IT licences; {$report['rejected_count']} rows rejected.");
    }
}
