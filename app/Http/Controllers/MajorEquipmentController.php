<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMajorEquipmentImportRequest;
use App\Http\Requests\SaveMajorEquipmentRequest;
use App\Models\MajorEquipment;
use App\Models\MajorEquipmentCertificate;
use App\Models\MiriRentalItem;
use App\Models\MiriInventoryCategory;
use App\Services\MiriEquipmentCsvService;
use App\Services\MiriCertificateService;
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

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:All,Major equipment,Rental'], 'status' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $equipment = MajorEquipment::query()->selectRaw("id, id as source_id, 'Major equipment' as type,
            COALESCE(NULLIF(description, ''), 'Unnamed equipment') as name, tag_no as identifier,
            category, COALESCE(NULLIF(status, ''), 'Not stated') as status, current_location,
            CASE WHEN issue_out_location IS NOT NULL AND issue_out_location != '' THEN 'Miri store / base' END as from_location,
            issue_out_location as to_location, issue_out_cog_date as issue_date, issue_out_cog_no as issue_reference,
            NULL as backload_from, received_backload_cog_date as received_date, received_backload_cog_no as received_reference,
            NULL as due_date, remarks")->toBase();
        $rentals = MiriRentalItem::query()->selectRaw("id, id as source_id, 'Rental' as type,
            COALESCE(NULLIF(description, ''), 'Unnamed rental') as name, serial_tag_equipment_no as identifier,
            category, COALESCE(NULLIF(status, ''), 'Not stated') as status, current_location,
            CASE WHEN issue_out_cog_date IS NOT NULL THEN 'Miri store / base' END as from_location,
            current_location as to_location, issue_out_cog_date as issue_date, issue_out_cog_no as issue_reference,
            received_backload_from_location as backload_from, received_backload_cog_date as received_date,
            received_backload_cog_no as received_reference, rental_due_date as due_date, remarks")->toBase();
        $query = \Illuminate\Support\Facades\DB::query()->fromSub($equipment->unionAll($rentals), 'movement');
        $summary = (clone $query)->selectRaw("COUNT(*) as total,
            COALESCE(SUM(CASE WHEN current_location IS NOT NULL AND TRIM(current_location) != '' THEN 1 ELSE 0 END), 0) as located,
            COALESCE(SUM(CASE WHEN issue_date IS NOT NULL THEN 1 ELSE 0 END), 0) as issued,
            COALESCE(SUM(CASE WHEN received_date IS NOT NULL THEN 1 ELSE 0 END), 0) as backloaded,
            COALESCE(SUM(CASE WHEN due_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as due_soon,
            COALESCE(SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END), 0) as overdue",
            [today()->toDateString(), today()->addDays(30)->toDateString(), today()->toDateString()])->first();
        $locations = (clone $query)->whereNotNull('current_location')->whereRaw("TRIM(current_location) != ''")
            ->selectRaw('current_location as label, COUNT(*) as total')->groupBy('current_location')->orderByDesc('total')->get();
        $statuses = (clone $query)->distinct()->orderBy('status')->pluck('status');
        $filtered = (clone $query)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                foreach (['name', 'identifier', 'category', 'current_location', 'to_location', 'backload_from'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            }))
            ->when($filters['location'] ?? null, fn ($q, $value) => $q->where('current_location', $value))
            ->when(($filters['type'] ?? 'All') !== 'All', fn ($q) => $q->where('type', $filters['type']))
            ->when(($filters['status'] ?? 'All') !== 'All', fn ($q) => $q->where('status', $filters['status']));
        return Inertia::render('MajorEquipment/Movement', [
            'summary' => collect((array) $summary)->map(fn ($value) => (int) $value),
            'locations' => $locations->take(10)->values(), 'locationOptions' => $locations->pluck('label'),
            'statusOptions' => $statuses, 'filters' => $filters,
            'records' => $filtered->orderByRaw('COALESCE(issue_date, received_date) DESC')->orderBy('type')->orderBy('id')
                ->paginate($filters['per_page'] ?? 10)->withQueryString(),
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);

        $selection = $request->validate(['company' => ['nullable', 'in:DESB,FTSB,unassigned'], 'inventory_type' => ['nullable', 'in:all,machinery,cargo']]);
        $company = $selection['company'] ?? '';
        $type = $selection['inventory_type'] ?? 'all';
        if ($request->query('view') === 'paint') {
            return Inertia::render('MajorEquipment/Dashboard', [
                'activeDashboard' => 'paint', 'companyFilter' => $company, 'inventoryType' => $type,
                'paintDashboard' => app(\App\Services\PaintDashboardService::class)->data($company),
                'canEditPaint' => $request->user()->canEdit('assets'),
            ]);
        }
        if ($request->query('view') === 'construction') {
            return Inertia::render('MajorEquipment/Dashboard', [
                'activeDashboard' => 'construction', 'companyFilter' => $company, 'inventoryType' => $type,
                'constructionDashboard' => app(\App\Services\ConstructionDashboardService::class)->data($company),
                'canEditConstruction' => $request->user()->canEdit('assets'),
            ]);
        }
        if ($request->query('view') === 'rentals') {
            return Inertia::render('MajorEquipment/Dashboard', [
                'activeDashboard' => 'rentals', 'companyFilter' => $company, 'inventoryType' => $type,
                'rentalDashboard' => $this->rentalDashboard(today(), $company),
            ]);
        }
        $query = MajorEquipment::query()->companyFilter($company)->when($type !== 'all', fn ($q) => $q->where('inventory_type', $type));
        $statusRows = (clone $query)->selectRaw("COALESCE(NULLIF(TRIM(status), ''), 'Not recorded') as label, COUNT(*) as value")->groupByRaw("COALESCE(NULLIF(TRIM(status), ''), 'Not recorded')")->orderByDesc('value')->get();
        $subcategoryExpression = "COALESCE(NULLIF(TRIM(section_2), ''), 'Not recorded')";
        $statusExpression = "COALESCE(NULLIF(TRIM(status), ''), 'Not recorded')";
        $categories = (clone $query)
            ->selectRaw("{$subcategoryExpression} as category, {$statusExpression} as label, COUNT(*) as value")
            ->groupByRaw("{$subcategoryExpression}, {$statusExpression}")
            ->get()
            ->groupBy('category')
            ->map(fn ($rows, $category) => [
                'category' => (string) $category,
                'total' => (int) $rows->sum('value'),
                'statuses' => $rows->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])->values(),
            ])
            ->sort(fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['category'], $b['category']))
            ->values();
        $today = today();
        $certificateQuery = MajorEquipmentCertificate::query()->whereHas('equipment', fn ($q) => $q->companyFilter($company)->when($type !== 'all', fn ($q) => $q->where('inventory_type', $type)));
        return Inertia::render('MajorEquipment/Dashboard', [
            'companyFilter' => $company, 'inventoryType' => $type,
            'typeCounts' => MajorEquipment::query()->companyFilter($company)->select('inventory_type')->selectRaw('COUNT(*) as total')->groupBy('inventory_type')->pluck('total', 'inventory_type'),
            'statusBreakdown' => $statusRows,
            'quality' => ['duplicates' => (clone $query)->duplicateTag()->count(), 'missing' => (clone $query)->missingDetails()->count(), 'warnings' => (clone $query)->whereNotNull('import_warnings')->count()],
            'quantityRecorded' => (clone $query)->whereNotNull('quantity')->count(),
            'activeDashboard' => $request->string('view')->toString() === 'rentals' ? 'rentals' : 'major',
            'summary' => [
                'total' => (int) $statusRows->sum('value'),
                'in_use' => (int) $statusRows->where('label', 'In Use')->sum('value'),
                'standby' => (int) $statusRows->where('label', 'Standby')->sum('value'),
                'under_repair' => (int) $statusRows->whereIn('label', ['Under Repair', 'PENDING REPAIR'])->sum('value'),
                'damaged' => (int) $statusRows->where('label', 'Damaged')->sum('value'),
            ],
            'expiry' => collect((array) (clone $certificateQuery)->selectRaw("
                COALESCE(SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END), 0) as expired,
                COALESCE(SUM(CASE WHEN expiry_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as due_30_days,
                COALESCE(SUM(CASE WHEN expiry_date BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as due_90_days,
                COALESCE(SUM(CASE WHEN expiry_date > ? THEN 1 ELSE 0 END), 0) as valid,
                COALESCE(SUM(CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END), 0) as not_recorded",
                [$today, $today, $today->copy()->addDays(30), $today->copy()->addDays(31), $today->copy()->addDays(90), $today->copy()->addDays(90)])
                ->toBase()->first())->map(fn ($value) => (int) $value),
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
            'categories' => $categories,
            'locations' => (clone $query)
                ->selectRaw("COALESCE(current_location, 'Unassigned') as label")
                ->selectRaw('COUNT(*) as total')
                ->groupBy('current_location')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'recent' => (clone $query)->latest('updated_at')->limit(8)->get([
                'id', 'description', 'tag_no', 'section_1', 'section_2', 'status', 'current_location',
            ]),
            'rentalDashboard' => null,
        ]);
    }

    private function rentalDashboard($today, ?string $company = null): array
    {
        $query = MiriRentalItem::query()->companyFilter($company);
        $statusCounts = (clone $query)->selectRaw("COALESCE(NULLIF(status, ''), 'Not stated') as label, COUNT(*) as total")->groupByRaw("COALESCE(NULLIF(status, ''), 'Not stated')")->pluck('total', 'label');
        $statusLabels = ['On Hire', 'Issued', 'Received Backload', 'Off Hire', 'Returned to Supplier', 'Overdue'];
        $group = fn (string $column, int $limit) => (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM({$column}), ''), 'Not specified') as label, COUNT(*) as value")
            ->groupByRaw("COALESCE(NULLIF(TRIM({$column}), ''), 'Not specified')")
            ->orderByDesc('value')->limit($limit)->get();
        $active = (clone $query)->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['Received Backload', 'Off Hire', 'Returned to Supplier']));
        $overdue = "CASE WHEN COALESCE(status, '') NOT IN ('Received Backload', 'Off Hire', 'Returned to Supplier') AND (status = 'Overdue' OR rental_due_date < ?) THEN 1 ELSE 0 END";
        $projectRows = (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM(project_contract), ''), 'Not recorded') as project, COALESCE(NULLIF(TRIM(status), ''), 'Not stated') as label, COUNT(*) as value, SUM({$overdue}) as overdue", [$today->toDateString()])
            ->groupByRaw("COALESCE(NULLIF(TRIM(project_contract), ''), 'Not recorded'), COALESCE(NULLIF(TRIM(status), ''), 'Not stated')")
            ->get();
        $projects = $projectRows->groupBy('project')->map(function ($rows, $project) use ($statusLabels) {
            $counts = $rows->pluck('value', 'label');
            $labels = collect($statusLabels)->merge($counts->keys())->unique();
            return [
                'project' => (string) $project, 'total' => (int) $rows->sum('value'),
                'on_hire' => (int) ($counts['On Hire'] ?? 0), 'off_hire' => (int) ($counts['Off Hire'] ?? 0),
                'overdue' => (int) $rows->sum('overdue'),
                'status' => $labels->map(fn ($label) => ['label' => $label, 'value' => $label === 'Overdue' ? (int) $rows->sum('overdue') : (int) ($counts[$label] ?? 0)])->values(),
            ];
        })->sortBy('project')->values();
        return [
            'summary' => [
                'total' => (int) $statusCounts->sum(), 'on_hire' => (int) ($statusCounts['On Hire'] ?? 0),
                'issued' => (int) ($statusCounts['Issued'] ?? 0), 'backload' => (int) ($statusCounts['Received Backload'] ?? 0),
                'overdue' => (int) $projects->sum('overdue'),
                'due_30_days' => (clone $active)->whereBetween('rental_due_date', [$today, $today->copy()->addDays(30)])->count(),
            ],
            'status' => collect($statusLabels)->merge($statusCounts->keys())->unique()->map(fn ($label) => ['label' => $label, 'value' => $label === 'Overdue' ? (int) $projects->sum('overdue') : (int) ($statusCounts[$label] ?? 0)])->values(),
            'projects' => $projects,
            'locations' => (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(project_contract), ''), 'Not recorded') as project,
                    COALESCE(NULLIF(TRIM(current_location), ''), 'Not specified') as label, COUNT(*) as value,
                    SUM(CASE WHEN status = 'On Hire' THEN 1 ELSE 0 END) as on_hire,
                    SUM(CASE WHEN status = 'Off Hire' THEN 1 ELSE 0 END) as off_hire,
                    SUM({$overdue}) as overdue", [$today->toDateString()])
                ->groupByRaw("COALESCE(NULLIF(TRIM(project_contract), ''), 'Not recorded'), COALESCE(NULLIF(TRIM(current_location), ''), 'Not specified')")
                ->orderBy('project')->orderBy('label')->get()
                ->map(fn ($row) => ['project' => $row->project, 'label' => $row->label, 'value' => (int) $row->value,
                    'on_hire' => (int) $row->on_hire, 'off_hire' => (int) $row->off_hire, 'overdue' => (int) $row->overdue]),
            'suppliers' => $group('supplier', 6),
            'upcoming' => (clone $active)->where('rental_due_date', '>=', $today)->orderBy('rental_due_date')->orderBy('id')->limit(8)->get()->map(fn ($item) => [
                'id' => $item->id, 'description' => $item->description, 'identifier' => $item->serial_tag_equipment_no,
                'location' => $item->current_location, 'supplier' => $item->supplier, 'status' => $item->status,
                'due_date' => $item->rental_due_date?->format('Y-m-d'), 'days_remaining' => $today->diffInDays($item->rental_due_date, false),
            ]),
            'recent' => (clone $query)->latest('updated_at')->orderByDesc('id')->limit(8)->get()->map(fn ($item) => [
                'id' => $item->id, 'description' => $item->description, 'identifier' => $item->serial_tag_equipment_no,
                'location' => $item->current_location, 'status' => $item->status, 'updated_at' => $item->updated_at?->format('d M Y, H:i'),
            ]),
        ];
    }

    public function index(Request $request): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);
        $filters = $request->validate(['company' => ['nullable', 'in:DESB,FTSB,unassigned'],
            'inventory_type' => ['nullable', 'in:machinery,cargo'],
            'search' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:255'],
            'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:255'],
            'issue_out_location' => ['nullable', 'string', 'max:255'], 'quality' => ['nullable', 'in:duplicates,missing,warnings'],
        ]);
        $type = $filters['inventory_type'] ?? 'machinery';
        if ($type !== 'cargo') unset($filters['description']);
        $base = MajorEquipment::query()->companyFilter($filters['company'] ?? null)->where('inventory_type', $type);
        $searchQuery = clone $base;
        if (filled($filters['search'] ?? null)) $searchQuery->where(function ($q) use ($filters) {
            foreach (['tag_no', 'serial_no', 'description', 'model_brand', 'size_model', 'current_location', 'issue_out_cog_no', 'received_backload_cog_no'] as $column) $q->orWhere($column, 'like', '%'.$filters['search'].'%');
        });
        $filterColumns = ['category' => 'category', 'section_1' => 'section_1', 'section_2' => 'section_2',
            'location' => 'current_location', 'issue_out_location' => 'issue_out_location', 'status' => 'status'];
        if ($type === 'cargo') $filterColumns['description'] = 'description';
        $filteredQuery = function (?string $except = null, bool $includeQuality = true) use ($searchQuery, $filterColumns, $filters) {
            $query = clone $searchQuery;
            foreach ($filterColumns as $key => $column) {
                if ($column !== $except && filled($filters[$key] ?? null)) $query->where($column, $filters[$key]);
            }
            if ($includeQuality) {
                match ($filters['quality'] ?? '') {
                    'duplicates' => $query->duplicateTag(),
                    'missing' => $query->missingDetails(),
                    'warnings' => $query->whereNotNull('import_warnings'),
                    default => null,
                };
            }
            return $query;
        };
        $query = $filteredQuery()->select('miri_inventory_items.*')->withCount('certificates')->withDuplicateCount();
        // Each dropdown respects the other filters but leaves its own alternatives available.
        $options = fn ($column) => $filteredQuery($column)->whereNotNull($column)->whereRaw("TRIM({$column}) != ''")
            ->distinct()->orderBy($column)->pluck($column)->values();
        $summaryQuery = $filteredQuery(null, false);
        return Inertia::render('MajorEquipment/Index', [
            'equipment' => $query->orderBy('section_1')->orderBy('section_2')->orderBy('description')->orderBy('id')->paginate(25)->withQueryString(),
            'tabCounts' => MajorEquipment::query()->select('inventory_type')->selectRaw('COUNT(*) AS total')->groupBy('inventory_type')->pluck('total', 'inventory_type'),
            'summary' => [
                'total' => (clone $summaryQuery)->count(), 'in_use' => (clone $summaryQuery)->where('status', 'In Use')->count(),
                'standby' => (clone $summaryQuery)->where('status', 'Standby')->count(), 'under_repair' => (clone $summaryQuery)->whereIn('status', ['Under Repair', 'PENDING REPAIR'])->count(),
                'missing_details' => (clone $summaryQuery)->missingDetails()->count(), 'duplicates' => (clone $summaryQuery)->duplicateTag()->count(),
                'warnings' => (clone $summaryQuery)->whereNotNull('import_warnings')->count(),
                'quantity_known' => (clone $summaryQuery)->whereNotNull('quantity')->count(),
            ],
            'filters' => array_merge(array_fill_keys(['company', 'search', 'description', 'category', 'section_1', 'section_2', 'location', 'status', 'issue_out_location', 'quality'], ''), $filters, ['inventory_type' => $type]),
            'descriptionOptions' => $type === 'cargo' ? $options('description') : [],
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

    public function store(SaveMajorEquipmentRequest $request, AuditLogger $auditLogger, MiriCertificateService $certificateService): RedirectResponse
    {
        $this->ensureMiri($request);
        $data = $request->validated();
        $certificates = $data['certificates'] ?? [];
        $removedIds = $data['removed_certificate_ids'] ?? [];
        unset($data['certificates'], $data['removed_certificate_ids']);
        $data['branch_id'] = app(BranchContext::class)->id($request->user());
        $equipment = $certificateService->save(new MajorEquipment(), $data, $certificates, $removedIds, $request->user()->id);
        $auditLogger->record('miri_inventory', 'created', "Added Miri equipment record {$equipment->description}.", $equipment, after: $equipment->load('certificates')->toArray(), user: $request->user(), request: $request);
        return redirect()->route('major-equipment.show', $equipment)->with('success', 'Miri equipment registered.');
    }

    public function edit(Request $request, MajorEquipment $equipment): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canEdit('assets'), 403);
        $equipment->load('certificates');
        return Inertia::render('MajorEquipment/Form', ['equipment' => $equipment, 'categories' => $this->categories(), 'certificateTypes' => $this->certificateTypes()]);
    }

    public function update(SaveMajorEquipmentRequest $request, MajorEquipment $equipment, AuditLogger $auditLogger, MiriCertificateService $certificateService): RedirectResponse
    {
        $this->ensureMiri($request);
        $before = $equipment->load('certificates')->toArray();
        $data = $request->validated();
        $certificates = $data['certificates'] ?? [];
        $removedIds = $data['removed_certificate_ids'] ?? [];
        unset($data['certificates'], $data['removed_certificate_ids']);
        $certificateService->save($equipment, $data, $certificates, $removedIds, $request->user()->id);
        $auditLogger->record('miri_inventory', 'updated', "Updated Miri equipment record {$equipment->description}.", $equipment, before: $before, after: $equipment->fresh()->load('certificates')->toArray(), user: $request->user(), request: $request);
        return redirect()->route('major-equipment.show', $equipment)->with('success', 'Miri equipment updated.');
    }

    public function show(MajorEquipment $equipment): Response
    {
        $this->ensureMiri(request());
        abort_unless(request()->user()?->canRead('assets'), 403);
        $equipment->load('certificates');
        return Inertia::render('MajorEquipment/Show', ['equipment' => $equipment, 'duplicates' => filled($equipment->normalized_tag) ? MajorEquipment::query()->where('branch_id', $equipment->branch_id)->where('id', '<>', $equipment->id)->where('normalized_tag', $equipment->normalized_tag)->get(['id', 'tag_no', 'description', 'inventory_type', 'current_location']) : []]);
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
        return Inertia::render('MajorEquipment/Import', [
            'inventoryType' => $request->query('inventory_type') === 'cargo' ? 'cargo' : 'machinery',
            'recentImports' => \Illuminate\Support\Facades\DB::table('miri_import_tasks')
                ->where('branch_id', app(BranchContext::class)->id())->where('user_id', $request->user()->id)
                ->latest()->limit(5)->get(['id', 'filename', 'status', 'created_at']),
        ]);
    }

    public function storeImport(StoreMajorEquipmentImportRequest $request, MiriEquipmentCsvService $service, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureMiri($request);
        $type = $request->validated('inventory_type');
        $hash = hash_file('sha256', $request->file('file')->getRealPath());
        abort_unless($request->session()->get('miri_import_preview') === $type.':'.$hash, 422, 'Preview this file before importing.');
        if (\Illuminate\Support\Facades\DB::table('miri_inventory_imports')->where('branch_id', app(BranchContext::class)->id())
            ->where('inventory_type', $type)->where('file_hash', $hash)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'This exact file has already been imported.']);
        }
        $id = (string) \Illuminate\Support\Str::uuid();
        $path = $request->file('file')->store('miri-imports', 'local');
        abort_unless($path, 500, 'Unable to stage import file.');
        try {
            \Illuminate\Support\Facades\DB::table('miri_import_tasks')->insert([
                'id' => $id, 'branch_id' => app(BranchContext::class)->id(), 'user_id' => $request->user()->id,
                'inventory_type' => $type, 'filename' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255),
                'file_path' => $path, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
            ]);
            \App\Jobs\ImportMiriEquipment::dispatch($id);
        } catch (\Throwable $error) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            \Illuminate\Support\Facades\DB::table('miri_import_tasks')->where('id', $id)->update(['status' => 'failed', 'error' => 'Unable to queue import. Please contact an administrator.', 'updated_at' => now()]);
            throw $error;
        }
        $request->session()->forget('miri_import_preview');
        return redirect()->route('major-equipment.import.status', $id);
    }

    public function importStatus(Request $request, string $task): Response
    {
        $this->ensureMiri($request);
        abort_unless($request->user()?->canRead('assets'), 403);
        $record = \Illuminate\Support\Facades\DB::table('miri_import_tasks')->where('id', $task)
            ->where('branch_id', app(BranchContext::class)->id())->where('user_id', $request->user()->id)
            ->first(['id', 'filename', 'inventory_type', 'status', 'result', 'error']);
        abort_unless($record, 404);
        $record->result = $record->result ? json_decode($record->result, true) : null;
        return Inertia::render('MajorEquipment/ImportStatus', ['task' => $record]);
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
