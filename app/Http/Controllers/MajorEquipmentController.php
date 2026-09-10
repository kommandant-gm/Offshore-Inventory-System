<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMajorEquipmentImportRequest;
use App\Http\Requests\SaveMajorEquipmentRequest;
use App\Models\MajorEquipment;
use App\Models\MajorEquipmentCertificate;
use App\Models\MiriRentalItem;
use App\Models\MiriInventoryCategory;
use App\Services\MiriEquipmentCsvService;
use App\Services\BranchContext;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class MajorEquipmentController extends Controller
{
    public function movement(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);

        $today = today();
        $equipment = MajorEquipment::query()->get([
            'id', 'description', 'tag_no', 'category', 'status', 'current_location',
            'issue_out_location', 'issue_out_cog_no', 'issue_out_cog_date',
            'received_backload_cog_no', 'received_backload_cog_date', 'remarks',
        ]);
        $rentals = MiriRentalItem::query()->get([
            'id', 'description', 'serial_tag_equipment_no', 'category', 'status', 'current_location',
            'rental_due_date', 'issue_out_cog_no', 'issue_out_cog_date',
            'received_backload_from_location', 'received_backload_cog_no', 'received_backload_cog_date', 'remarks',
        ]);

        $records = $equipment->map(fn (MajorEquipment $item) => [
            'id' => $item->id, 'source_id' => $item->id, 'type' => 'Major equipment',
            'name' => $item->description ?: 'Unnamed equipment', 'identifier' => $item->tag_no,
            'category' => $item->category, 'status' => $item->status ?: 'Not stated',
            'current_location' => $item->current_location, 'from_location' => $item->issue_out_location ? 'Miri store / base' : null,
            'to_location' => $item->issue_out_location, 'issue_date' => $item->issue_out_cog_date?->format('Y-m-d'),
            'issue_reference' => $item->issue_out_cog_no, 'backload_from' => null,
            'received_date' => $item->received_backload_cog_date?->format('Y-m-d'), 'received_reference' => $item->received_backload_cog_no,
            'due_date' => null, 'remarks' => $item->remarks,
        ])->concat($rentals->map(fn (MiriRentalItem $item) => [
            'id' => 1000000 + $item->id, 'source_id' => $item->id, 'type' => 'Rental',
            'name' => $item->description ?: 'Unnamed rental', 'identifier' => $item->serial_tag_equipment_no,
            'category' => $item->category, 'status' => $item->status ?: 'Not stated',
            'current_location' => $item->current_location, 'from_location' => $item->issue_out_cog_date ? 'Miri store / base' : null,
            'to_location' => $item->current_location, 'issue_date' => $item->issue_out_cog_date?->format('Y-m-d'),
            'issue_reference' => $item->issue_out_cog_no, 'backload_from' => $item->received_backload_from_location,
            'received_date' => $item->received_backload_cog_date?->format('Y-m-d'), 'received_reference' => $item->received_backload_cog_no,
            'due_date' => $item->rental_due_date?->format('Y-m-d'), 'remarks' => $item->remarks,
        ]))->values();

        $locations = $records->filter(fn (array $item) => filled($item['current_location']))
            ->groupBy('current_location')->map(fn ($items, $location) => ['label' => $location, 'total' => $items->count()])
            ->sortByDesc('total')->values()->take(10);

        return Inertia::render('MajorEquipment/Movement', [
            'summary' => [
                'total' => $records->count(), 'located' => $records->whereNotNull('current_location')->filter(fn ($item) => filled($item['current_location']))->count(),
                'issued' => $records->filter(fn ($item) => filled($item['issue_date']))->count(), 'backloaded' => $records->filter(fn ($item) => filled($item['received_date']))->count(),
                'due_soon' => $records->filter(fn ($item) => filled($item['due_date']) && $item['due_date'] >= $today->format('Y-m-d') && $item['due_date'] <= $today->copy()->addDays(30)->format('Y-m-d'))->count(),
                'overdue' => $records->filter(fn ($item) => filled($item['due_date']) && $item['due_date'] < $today->format('Y-m-d'))->count(),
            ],
            'locations' => $locations, 'records' => $records->sortByDesc(fn ($item) => $item['issue_date'] ?: $item['received_date'] ?: '')->values(),
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);

        $query = MajorEquipment::query();
        $today = today();
        $certificateQuery = MajorEquipmentCertificate::query();
        $rentalDashboard = $this->rentalDashboard($today);
        return Inertia::render('MajorEquipment/Dashboard', [
            'activeDashboard' => $request->string('view')->toString() === 'rentals' ? 'rentals' : 'major',
            'summary' => [
                'total' => (clone $query)->count(),
                'in_use' => (clone $query)->where('status', 'In Use')->count(),
                'standby' => (clone $query)->where('status', 'Standby')->count(),
                'under_repair' => (clone $query)->where('status', 'Under Repair')->count(),
                'damaged' => (clone $query)->where('status', 'Damaged')->count(),
            ],
            'expiry' => [
                'expired' => (clone $certificateQuery)->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today)->count(),
                'due_30_days' => (clone $certificateQuery)->whereBetween('expiry_date', [$today, $today->copy()->addDays(30)])->count(),
                'due_90_days' => (clone $certificateQuery)->whereBetween('expiry_date', [$today->copy()->addDays(31), $today->copy()->addDays(90)])->count(),
                'valid' => (clone $certificateQuery)->whereDate('expiry_date', '>', $today->copy()->addDays(90))->count(),
                'not_recorded' => (clone $certificateQuery)->whereNull('expiry_date')->count(),
            ],
            'expiring' => (clone $certificateQuery)->with('equipment:id,description,tag_no,status')
                ->whereNotNull('expiry_date')
                ->orderBy('expiry_date')
                ->limit(6)
                ->get(['id', 'miri_inventory_item_id', 'certificate_type', 'expiry_date'])
                ->map(fn (MajorEquipmentCertificate $certificate) => [
                    'id' => $certificate->id,
                    'certificate_type' => $certificate->certificate_type,
                    'expiry_date' => $certificate->expiry_date?->format('Y-m-d'),
                    'days_remaining' => $today->diffInDays($certificate->expiry_date, false),
                    'equipment' => $certificate->equipment ? [
                        'id' => $certificate->equipment->id,
                        'tag_no' => $certificate->equipment->tag_no,
                        'description' => $certificate->equipment->description,
                    ] : null,
                ]),
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
            'rentalDashboard' => $rentalDashboard,
        ]);
    }

    private function rentalDashboard($today): array
    {
        $rentals = MiriRentalItem::query()->latest('updated_at')->get([
            'id', 'description', 'serial_tag_equipment_no', 'category', 'supplier', 'project_contract',
            'current_location', 'rental_due_date', 'issue_out_cog_no', 'issue_out_cog_date',
            'received_backload_from_location', 'received_backload_cog_date', 'status', 'remarks', 'updated_at',
        ]);
        $statusLabels = ['On Hire', 'Issued', 'Received Backload', 'Off Hire', 'Returned to Supplier', 'Overdue'];
        $statusCounts = $rentals->countBy(fn (MiriRentalItem $item) => $item->status ?: 'Not stated');
        $monthStart = $today->copy()->startOfMonth();

        return [
            'summary' => [
                'total' => $rentals->count(), 'on_hire' => (int) ($statusCounts['On Hire'] ?? 0),
                'issued' => (int) ($statusCounts['Issued'] ?? 0), 'backload' => (int) ($statusCounts['Received Backload'] ?? 0),
                'overdue' => $rentals->filter(fn (MiriRentalItem $item) => ($item->status === 'Overdue' || $item->rental_due_date?->lt($today)) && $item->status !== 'Returned to Supplier')->count(),
                'due_30_days' => $rentals->filter(fn (MiriRentalItem $item) => $item->rental_due_date?->betweenIncluded($today, $today->copy()->addDays(30)))->count(),
            ],
            'status' => collect($statusLabels)->map(fn (string $label) => ['label' => $label, 'value' => (int) ($statusCounts[$label] ?? 0)])->values(),
            'locations' => $rentals->countBy(fn (MiriRentalItem $item) => trim((string) $item->current_location) ?: 'Not specified')->sortDesc()->take(8)->map(fn (int $total, string $label) => ['label' => $label, 'value' => $total])->values(),
            'suppliers' => $rentals->countBy(fn (MiriRentalItem $item) => trim((string) $item->supplier) ?: 'Not specified')->sortDesc()->take(6)->map(fn (int $total, string $label) => ['label' => $label, 'value' => $total])->values(),
            'dueTimeline' => collect(range(0, 5))->map(function (int $offset) use ($rentals, $monthStart) {
                $start = $monthStart->copy()->addMonths($offset);
                $end = $start->copy()->endOfMonth();
                return ['label' => $start->format('M'), 'full_label' => $start->format('M Y'), 'value' => $rentals->filter(fn (MiriRentalItem $item) => $item->rental_due_date?->betweenIncluded($start, $end))->count()];
            })->values(),
            'upcoming' => $rentals->filter(fn (MiriRentalItem $item) => $item->rental_due_date?->gte($today))->sortBy('rental_due_date')->take(8)->map(fn (MiriRentalItem $item) => [
                'id' => $item->id, 'description' => $item->description, 'identifier' => $item->serial_tag_equipment_no,
                'location' => $item->current_location, 'supplier' => $item->supplier, 'status' => $item->status,
                'due_date' => $item->rental_due_date?->format('Y-m-d'), 'days_remaining' => $today->diffInDays($item->rental_due_date, false),
            ])->values(),
            'recent' => $rentals->take(8)->map(fn (MiriRentalItem $item) => [
                'id' => $item->id, 'description' => $item->description, 'identifier' => $item->serial_tag_equipment_no,
                'location' => $item->current_location, 'status' => $item->status, 'updated_at' => $item->updated_at?->format('d M Y, H:i'),
            ])->values(),
        ];
    }

    public function index(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);
        $filters = $request->validate([
            'inventory_type' => ['nullable', 'in:machinery,cargo'],
            'search' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:255'],
            'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:255'],
            'issue_out_location' => ['nullable', 'string', 'max:255'], 'quality' => ['nullable', 'in:duplicates,missing,warnings'],
        ]);
        $type = $filters['inventory_type'] ?? 'machinery';
        $base = MajorEquipment::query()->where('inventory_type', $type);
        $query = (clone $base)->select('miri_inventory_items.*')->withCount('certificates')->withDuplicateCount();
        foreach (['category', 'section_1', 'section_2', 'status', 'issue_out_location'] as $column) {
            if (filled($filters[$column] ?? null)) $query->where($column, $filters[$column]);
        }
        if (filled($filters['location'] ?? null)) $query->where('current_location', $filters['location']);
        if (filled($filters['search'] ?? null)) $query->where(function ($q) use ($filters) {
            foreach (['tag_no', 'serial_no', 'description', 'model_brand', 'size_model', 'current_location', 'issue_out_cog_no', 'received_backload_cog_no'] as $column) $q->orWhere($column, 'like', '%'.$filters['search'].'%');
        });
        match ($filters['quality'] ?? '') {
            'duplicates' => $query->duplicateTag(),
            'missing' => $query->missingDetails(),
            'warnings' => $query->whereNotNull('import_warnings'),
            default => null,
        };
        $options = fn ($column) => (clone $base)->whereNotNull($column)->where($column, '<>', '')->distinct()->orderBy($column)->pluck($column)->values();
        return Inertia::render('MajorEquipment/Index', [
            'equipment' => $query->orderBy('section_1')->orderBy('section_2')->orderBy('description')->orderBy('id')->paginate(25)->withQueryString(),
            'tabCounts' => MajorEquipment::query()->select('inventory_type')->selectRaw('COUNT(*) AS total')->groupBy('inventory_type')->pluck('total', 'inventory_type'),
            'summary' => [
                'total' => (clone $base)->count(), 'in_use' => (clone $base)->where('status', 'In Use')->count(),
                'standby' => (clone $base)->where('status', 'Standby')->count(), 'under_repair' => (clone $base)->whereIn('status', ['Under Repair', 'PENDING REPAIR'])->count(),
                'missing_details' => (clone $base)->missingDetails()->count(), 'duplicates' => (clone $base)->duplicateTag()->count(),
                'warnings' => (clone $base)->whereNotNull('import_warnings')->count(),
                'quantity_known' => (clone $base)->whereNotNull('quantity')->count(),
            ],
            'filters' => array_merge(array_fill_keys(['search', 'category', 'section_1', 'section_2', 'location', 'status', 'issue_out_location', 'quality'], ''), $filters, ['inventory_type' => $type]),
            'categoryOptions' => $options('category'), 'section1Options' => $options('section_1'), 'section2Options' => $options('section_2'),
            'locationOptions' => $options('current_location'), 'issueOutLocationOptions' => $options('issue_out_location'), 'statusOptions' => $options('status'),
            'canEdit' => $request->user()->canEdit('assets'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canEdit('assets'), 403);
        return Inertia::render('MajorEquipment/Form', ['inventoryType' => $request->query('inventory_type') === 'cargo' ? 'cargo' : 'machinery', 'equipment' => null, 'categories' => $this->categories(), 'certificateTypes' => $this->certificateTypes()]);
    }

    public function store(SaveMajorEquipmentRequest $request, AuditLogger $auditLogger): RedirectResponse
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
        $auditLogger->record('miri_inventory', 'created', "Added Miri equipment record {$equipment->description}.", $equipment, after: $equipment->toArray(), user: $request->user(), request: $request);
        return redirect()->route('major-equipment.show', $equipment)->with('success', 'Miri equipment registered.');
    }

    public function edit(Request $request, MajorEquipment $equipment): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canEdit('assets'), 403);
        $equipment->load('certificates');
        return Inertia::render('MajorEquipment/Form', ['equipment' => $equipment, 'categories' => $this->categories(), 'certificateTypes' => $this->certificateTypes()]);
    }

    public function update(SaveMajorEquipmentRequest $request, MajorEquipment $equipment, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureMiri($request);
        $before = $equipment->toArray();
        $data = $request->validated();
        $certificates = $data['certificates'] ?? [];
        unset($data['certificates']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($equipment, $data, $certificates): void {
            $equipment->update($data);
            $equipment->certificates()->delete();
            foreach ($certificates as $certificate) $equipment->certificates()->create(['branch_id' => $equipment->branch_id, ...$certificate]);
        });
        $auditLogger->record('miri_inventory', 'updated', "Updated Miri equipment record {$equipment->description}.", $equipment, before: $before, after: $equipment->fresh()->toArray(), user: $request->user(), request: $request);
        return redirect()->route('major-equipment.show', $equipment)->with('success', 'Miri equipment updated.');
    }

    public function show(MajorEquipment $equipment): Response
    {
        $this->ensureMiri(request());
        abort_unless(request()->user()?->canRead('assets'), 403);
        $equipment->load('certificates');
        return Inertia::render('MajorEquipment/Show', ['equipment' => $equipment, 'duplicates' => filled($equipment->tag_no) ? MajorEquipment::query()->where('id', '<>', $equipment->id)->whereRaw('LOWER(TRIM(tag_no)) = ?', [mb_strtolower(trim($equipment->tag_no))])->get(['id', 'tag_no', 'description', 'inventory_type', 'current_location']) : []]);
    }

    public function pdf(Request $request, MajorEquipment $equipment)
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);
        $equipment->load('certificates');

        return Pdf::loadView('miri-inventory.registration-pdf', [
            'equipment' => $equipment,
            'logoPath' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/dayang-logo.png'))),
        ])->download('miri-inventory-registration-'.$equipment->id.'.pdf');
    }

    public function import(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless(request()->user()?->canEdit('assets'), 403);
        return Inertia::render('MajorEquipment/Import', ['inventoryType' => $request->query('inventory_type') === 'cargo' ? 'cargo' : 'machinery']);
    }

    public function storeImport(StoreMajorEquipmentImportRequest $request, MiriEquipmentCsvService $service, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureMiri($request);
        $type = $request->validated('inventory_type');
        $hash = hash_file('sha256', $request->file('file')->getRealPath());
        abort_unless($request->session()->get('miri_import_preview') === $type.':'.$hash, 422, 'Preview this file before importing.');
        $summary = $service->import($request->file('file'), $request->user()->id, $type);
        $request->session()->forget('miri_import_preview');
        $auditLogger->record('miri_inventory', 'imported', "Imported Miri equipment file: {$summary['created']} records created and {$summary['certificates_created']} certificates captured.", user: $request->user(), request: $request);
        return redirect()->route('major-equipment.index', ['inventory_type' => $type])->with('success', "Import complete: {$summary['created']} records and {$summary['certificates_created']} certificates. {$summary['duplicate_records']} records have duplicate tags; {$summary['warning_records']} have import warnings. Review Data quality below.");
    }

    public function previewImport(StoreMajorEquipmentImportRequest $request, MiriEquipmentCsvService $service)
    {
        $this->ensureMiri($request);
        $report = $service->preview($request->file('file'), $request->validated('inventory_type'));
        $request->session()->put('miri_import_preview', $request->validated('inventory_type').':'.$report['file_hash']);
        return response()->json($report);
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
        return [...MiriEquipmentCsvService::MACHINERY_CERTIFICATES, ...MiriEquipmentCsvService::CARGO_CERTIFICATES];
    }
}
