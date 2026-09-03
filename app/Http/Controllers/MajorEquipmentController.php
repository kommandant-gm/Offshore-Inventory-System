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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:255'],
            'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:50'],
            'issue_out_location' => ['nullable', 'string', 'max:255'], 'missing_details' => ['nullable', 'in:missing'],
        ]);
        $query = MajorEquipment::query()->withCount('certificates')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    foreach (['tag_no', 'serial_no', 'description', 'model_brand', 'current_location', 'issue_out_location', 'issue_out_cog_no', 'received_backload_cog_no'] as $column) $query->orWhere($column, 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, $value) => $query->where('category', $value))
            ->when($filters['section_1'] ?? null, fn ($query, $value) => $query->where('section_1', $value))
            ->when($filters['section_2'] ?? null, fn ($query, $value) => $query->where('section_2', $value))
            ->when($filters['location'] ?? null, fn ($query, $value) => $query->where('current_location', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['issue_out_location'] ?? null, fn ($query, $value) => $query->where('issue_out_location', $value))
            ->when(($filters['missing_details'] ?? null) === 'missing', fn ($query) => $query->where(fn ($query) => $query->whereNull('tag_no')->orWhere('tag_no', '')->orWhereNull('current_location')->orWhere('current_location', '')))
            ->orderBy('section_1')->orderBy('section_2')->orderBy('description')->orderBy('tag_no');
        $optionValues = fn (string $column) => MajorEquipment::query()->whereNotNull($column)->where($column, '<>', '')->distinct()->orderBy($column)->pluck($column)->values();
        $allEquipment = MajorEquipment::query();
        return Inertia::render('MajorEquipment/Index', [
            'equipment' => $query->paginate(25)->withQueryString(),
            'summary' => ['total' => (clone $allEquipment)->count(), 'in_use' => (clone $allEquipment)->where('status', 'In Use')->count(), 'standby' => (clone $allEquipment)->where('status', 'Standby')->count(), 'under_repair' => (clone $allEquipment)->where('status', 'Under Repair')->count(), 'missing_details' => (clone $allEquipment)->where(fn ($query) => $query->whereNull('tag_no')->orWhere('tag_no', '')->orWhereNull('current_location')->orWhere('current_location', ''))->count()],
            'filters' => ['search' => $filters['search'] ?? '', 'category' => $filters['category'] ?? '', 'section_1' => $filters['section_1'] ?? '', 'section_2' => $filters['section_2'] ?? '', 'location' => $filters['location'] ?? '', 'status' => $filters['status'] ?? '', 'issue_out_location' => $filters['issue_out_location'] ?? '', 'missing_details' => $filters['missing_details'] ?? ''],
            'categoryOptions' => MiriInventoryCategory::query()->where('active', true)->orderBy('name')->pluck('name')->values(),
            'section1Options' => $optionValues('section_1'), 'section2Options' => $optionValues('section_2'), 'locationOptions' => $optionValues('current_location'), 'issueOutLocationOptions' => $optionValues('issue_out_location'),
            'statusOptions' => ['In Use', 'Standby', 'Under Repair', 'Damaged'], 'canEdit' => $request->user()->canEdit('assets'),
        ]);
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
