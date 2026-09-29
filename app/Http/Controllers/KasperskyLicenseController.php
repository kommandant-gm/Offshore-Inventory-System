<?php

namespace App\Http\Controllers;

use App\Models\KasperskyImport;
use App\Services\AuditLogger;
use App\Services\KasperskyImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class KasperskyLicenseController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);

        return Inertia::render('ItLicenses/Kaspersky', [
            'overview' => KasperskyImport::overview(),
            'history' => KasperskyImport::query()->latest('id')->limit(12)->get(['id', 'filename', 'created_at']),
        ]);
    }

    public function store(Request $request, KasperskyImportService $service, AuditLogger $logger)
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx']]);
        $file = $request->file('file');
        $path = $file->storeAs('kaspersky-imports', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()));
        try {
            $rows = $service->parse(Storage::path($path));
            DB::transaction(function () use ($rows, $file, $request, $logger) {
                KasperskyImport::create(['filename' => $file->getClientOriginalName(), 'rows' => $rows, 'user_id' => $request->user()->id]);
                $logger->record(module: 'it_assets', event: 'kaspersky_imported', summary: 'Updated Kaspersky snapshot: '.count($rows).' licences.', user: $request->user(), request: $request);
            });
        } finally {
            Storage::delete($path);
        }

        return to_route('kaspersky-licenses.index')->with('success', 'Kaspersky licences updated: '.count($rows).' licences.');
    }
}
