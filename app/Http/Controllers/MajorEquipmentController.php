<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMajorEquipmentImportRequest;
use App\Http\Requests\SaveMajorEquipmentRequest;
use App\Models\MajorEquipment;
use App\Models\MiriInventoryCategory;
use App\Services\MajorEquipmentImportService;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MajorEquipmentController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);

        $query = MajorEquipment::query();
        return Inertia::render('MajorEquipment/Dashboard', [
            'summary' => [
                'total' => (clone $query)->count(),
                'in_use' => (clone $query)->where('status', 'In Use')->count(),
                'standby' => (clone $query)->where('status', 'Standby')->count(),
                'under_repair' => (clone $query)->where('status', 'Under Repair')->count(),
                'damaged' => (clone $query)->where('status', 'Damaged')->count(),
            ],
            'categories' => MajorEquipment::query()
                ->select('category')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get(),
            'locations' => MajorEquipment::query()
                ->selectRaw("COALESCE(current_location, 'Unassigned') as label")
                ->selectRaw('COUNT(*) as total')
                ->groupBy('current_location')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'recent' => MajorEquipment::query()->latest('updated_at')->limit(8)->get([
                'id', 'description', 'tag_no', 'section_1', 'section_2', 'status', 'current_location',
            ]),
        ]);
    }

    public function index(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);
        $query = MajorEquipment::query()->withCount('certificates')->orderBy('section_1')->orderBy('section_2')->orderBy('description')->orderBy('tag_no');
        if ($request->filled('search')) $query->where(fn ($q) => $q->where('tag_no', 'like', '%'.$request->string('search').'%')->orWhere('serial_no', 'like', '%'.$request->string('search').'%')->orWhere('description', 'like', '%'.$request->string('search').'%')->orWhere('current_location', 'like', '%'.$request->string('search').'%'));
        return Inertia::render('MajorEquipment/Index', ['equipment' => $query->paginate(25)->withQueryString(), 'search' => $request->string('search')->toString(), 'statuses' => ['In Use', 'Standby', 'Under Repair', 'Damaged'], 'canEdit' => $request->user()->canEdit('assets')]);
    }

    public function create(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canEdit('assets'), 403);
        return Inertia::render('MajorEquipment/Form', ['equipment' => null, 'categories' => $this->categories(), 'certificateTypes' => $this->certificateTypes()]);
    }

    public function store(SaveMajorEquipmentRequest $request): RedirectResponse
    {
        $this->ensureMiri($request);
        $data = $request->validated();
        $certificates = $data['certificates'] ?? [];
        unset($data['certificates']);
        $data['branch_id'] = app(BranchContext::class)->id($request->user());
        $equipment = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $certificates) {
            $equipment = MajorEquipment::create($data);
            foreach ($certificates as $certificate) $equipment->certificates()->create(['branch_id' => $equipment->branch_id, ...$certificate]);
            return $equipment;
        });
        return redirect()->route('major-equipment.show', $equipment)->with('success', 'Miri equipment registered.');
    }

    public function edit(Request $request, MajorEquipment $equipment): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canEdit('assets'), 403);
        $equipment->load('certificates');
        return Inertia::render('MajorEquipment/Form', ['equipment' => $equipment, 'categories' => $this->categories(), 'certificateTypes' => $this->certificateTypes()]);
    }

    public function update(SaveMajorEquipmentRequest $request, MajorEquipment $equipment): RedirectResponse
    {
        $this->ensureMiri($request);
        $data = $request->validated();
        $certificates = $data['certificates'] ?? [];
        unset($data['certificates']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($equipment, $data, $certificates): void {
            $equipment->update($data);
            $equipment->certificates()->delete();
            foreach ($certificates as $certificate) $equipment->certificates()->create(['branch_id' => $equipment->branch_id, ...$certificate]);
        });
        return redirect()->route('major-equipment.show', $equipment)->with('success', 'Miri equipment updated.');
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

    private function categories(): array
    {
        return MiriInventoryCategory::query()->where('active', true)->orderBy('name')->pluck('name')->values()->all();
    }

    private function certificateTypes(): array
    {
        return ['SERVICE RELIEF VALVE', 'PRESSURE GAUGE', 'SKID MPI', 'WATER MANIFOLD H.T.', 'RELAY', 'UT', 'HT', 'WINCH LOAD TEST', 'WIRE ROPE INSPECTION', 'HOOK', 'CIDB', 'LIFTING', 'LIFTED EQUIPMENT'];
    }
}
