<?php

namespace App\Http\Controllers;

use App\Services\ItAssetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ItAssetImportController extends Controller
{
    public function create(Request $request): Response
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);
        return Inertia::render('ItAssets/Import', ['report' => session('it_asset_import_report')]);
    }

    public function preview(Request $request, ItAssetImportService $service): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);
        $request->validate(['file' => ['required','file','max:20480','mimes:csv,txt,xlsx']]);
        $path=$request->file('file')->store('it-asset-imports');
        $report=$service->analyse(Storage::path($path));
        session(['it_asset_import_path'=>$path,'it_asset_import_report'=>collect($report)->except('valid')->all()]);
        return back();
    }

    public function store(Request $request, ItAssetImportService $service): RedirectResponse
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);
        $path=session('it_asset_import_path'); abort_unless($path && Storage::exists($path), 422, 'Please preview the import file again.');
        $report=$service->import(Storage::path($path), $request->user()->id); Storage::delete($path); session()->forget(['it_asset_import_path','it_asset_import_report']);
        return redirect()->route('it-assets.index')->with('success', "Imported {$report['ready']} IT assets; {$report['rejected_count']} rows rejected.");
    }
}
