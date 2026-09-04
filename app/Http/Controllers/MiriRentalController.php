<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMiriRentalRequest;
use App\Http\Requests\StoreMiriRentalImportRequest;
use App\Models\MiriRentalItem;
use App\Services\BranchContext;
use App\Services\MiriRentalImportService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MiriRentalController extends Controller
{
    private const STATUSES = ['On Hire', 'Issued', 'Received Backload', 'Off Hire', 'Returned to Supplier', 'Overdue'];

    public function index(Request $request): Response
    {
        $this->ensureMiri($request); abort_unless($request->user()?->canRead('assets'), 403);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:255'], 'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'], 'supplier' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)], 'missing_details' => ['nullable', 'in:missing']]);
        $query = MiriRentalItem::query()->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) { foreach (['serial_tag_equipment_no', 'description', 'supplier', 'project_contract', 'current_location', 'issue_out_cog_no', 'received_backload_cog_no', 'return_cog_no'] as $column) $q->orWhere($column, 'like', "%{$s}%"); }))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))->when($filters['section_1'] ?? null, fn ($q, $v) => $q->where('section_1', $v))->when($filters['section_2'] ?? null, fn ($q, $v) => $q->where('section_2', $v))->when($filters['location'] ?? null, fn ($q, $v) => $q->where('current_location', $v))->when($filters['supplier'] ?? null, fn ($q, $v) => $q->where('supplier', $v))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when(($filters['missing_details'] ?? null) === 'missing', fn ($q) => $q->where(fn ($q) => $q->whereNull('description')->orWhereNull('supplier')->orWhereNull('current_location')))
            ->orderBy('section_1')->orderBy('section_2')->orderBy('description');
        $all = MiriRentalItem::query(); $options = fn (string $column) => MiriRentalItem::query()->whereNotNull($column)->where($column, '<>', '')->distinct()->orderBy($column)->pluck($column)->values();
        return Inertia::render('MiriRental/Index', ['rentals' => $query->paginate(25)->withQueryString(), 'summary' => ['total' => (clone $all)->count(), 'on_hire' => (clone $all)->where('status', 'On Hire')->count(), 'issued' => (clone $all)->where('status', 'Issued')->count(), 'backload' => (clone $all)->where('status', 'Received Backload')->count(), 'overdue' => (clone $all)->where('status', 'Overdue')->count()], 'filters' => array_merge(['search' => '', 'category' => '', 'section_1' => '', 'section_2' => '', 'location' => '', 'supplier' => '', 'status' => '', 'missing_details' => ''], $filters), 'categoryOptions' => $options('category'), 'section1Options' => $options('section_1'), 'section2Options' => $options('section_2'), 'locationOptions' => $options('current_location'), 'supplierOptions' => $options('supplier'), 'statusOptions' => self::STATUSES, 'canEdit' => $request->user()->canEdit('assets')]);
    }

    public function create(Request $request): Response { $this->ensureMiri($request); abort_unless($request->user()?->canEdit('assets'), 403); return Inertia::render('MiriRental/Form', ['rental' => null, 'categories' => $this->categories()]); }
    public function store(SaveMiriRentalRequest $request, AuditLogger $auditLogger): RedirectResponse { $this->ensureMiri($request); $rental = MiriRentalItem::create([...$request->validated(), 'branch_id' => app(BranchContext::class)->id($request->user())]); $auditLogger->record('miri_rentals', 'created', "Added Miri rental record {$rental->description}.", $rental, after: $rental->toArray(), user: $request->user(), request: $request); return redirect()->route('miri-rental.show', $rental)->with('success', 'Rental record registered.'); }
    public function show(Request $request, MiriRentalItem $rental): Response { $this->ensureMiri($request); abort_unless($request->user()?->canRead('assets'), 403); return Inertia::render('MiriRental/Show', ['rental' => $rental]); }
    public function edit(Request $request, MiriRentalItem $rental): Response { $this->ensureMiri($request); abort_unless($request->user()?->canEdit('assets'), 403); return Inertia::render('MiriRental/Form', ['rental' => $rental, 'categories' => $this->categories()]); }
    public function update(SaveMiriRentalRequest $request, MiriRentalItem $rental, AuditLogger $auditLogger): RedirectResponse { $this->ensureMiri($request); $before = $rental->toArray(); $rental->update($request->validated()); $auditLogger->record('miri_rentals', 'updated', "Updated Miri rental record {$rental->description}.", $rental, before: $before, after: $rental->fresh()->toArray(), user: $request->user(), request: $request); return redirect()->route('miri-rental.show', $rental)->with('success', 'Rental record updated.'); }
    public function import(Request $request): Response { $this->ensureMiri($request); abort_unless($request->user()?->canEdit('assets'), 403); return Inertia::render('MiriRental/Import'); }
    public function storeImport(StoreMiriRentalImportRequest $request, MiriRentalImportService $service, AuditLogger $auditLogger): RedirectResponse { $this->ensureMiri($request); $summary = $service->import($request->file('file')); $auditLogger->record('miri_rentals', 'imported', "Imported Miri rental file: {$summary['created']} records created.", user: $request->user(), request: $request); return redirect()->route('miri-rental.index')->with('success', "Rental import complete. {$summary['created']} records created."); }
    private function categories(): array { return \App\Models\MiriInventoryCategory::query()->where('active', true)->orderBy('name')->pluck('name')->values()->all(); }
    private function ensureMiri(Request $request): void { abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404); }
}
