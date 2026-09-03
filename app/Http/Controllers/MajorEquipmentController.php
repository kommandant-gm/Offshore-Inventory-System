<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMajorEquipmentImportRequest;
use App\Models\MajorEquipment;
use App\Services\MajorEquipmentImportService;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MajorEquipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);
        $query = MajorEquipment::query()->withCount('certificates')->orderBy('section_1')->orderBy('section_2')->orderBy('description')->orderBy('tag_no');
        if ($request->filled('search')) $query->where(fn ($q) => $q->where('tag_no', 'like', '%'.$request->string('search').'%')->orWhere('serial_no', 'like', '%'.$request->string('search').'%')->orWhere('description', 'like', '%'.$request->string('search').'%')->orWhere('current_location', 'like', '%'.$request->string('search').'%'));
        return Inertia::render('MajorEquipment/Index', ['equipment' => $query->paginate(25)->withQueryString(), 'search' => $request->string('search')->toString(), 'statuses' => ['In Use', 'Standby', 'Under Repair', 'Damaged']]);
    }

    public function show(MajorEquipment $equipment): Response
    {
        $this->ensureMiri(request());
        abort_unless(request()->user()?->canRead('assets'), 403);
        $equipment->load('certificates');
        return Inertia::render('MajorEquipment/Show', ['equipment' => $equipment]);
    }

    public function import(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless(request()->user()?->canEdit('assets'), 403);
        return Inertia::render('MajorEquipment/Import');
    }

    public function storeImport(StoreMajorEquipmentImportRequest $request, MajorEquipmentImportService $service): RedirectResponse
    {
        $this->ensureMiri($request);
        $summary = $service->import($request->file('file'), $request->user()->id);
        return redirect()->route('major-equipment.index')->with('success', "Major Equipment import complete. {$summary['created']} records created and {$summary['certificates_created']} certificates captured.");
    }

    private function ensureMiri(Request $request): void
    {
        abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404);
    }
}
