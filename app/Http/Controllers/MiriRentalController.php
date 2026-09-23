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
use Barryvdh\DomPDF\Facade\Pdf;

class MiriRentalController extends Controller
{
    private const STATUSES = ['On Hire', 'Issued', 'Received Backload', 'Off Hire', 'Returned to Supplier', 'Overdue'];

    public function index(Request $request): Response
    {
        $this->ensureMiri($request); abort_unless($request->user()?->canRead('assets'), 403);
        $filters = $request->validate(['company' => ['nullable', 'in:DESB,FTSB,unassigned'], 'search' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:255'], 'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'], 'supplier' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)], 'due_status' => ['nullable', 'string', 'in:overdue,due_7,due_30,later,no_date'], 'missing_details' => ['nullable', 'in:missing']]);
        $today = today();
        $searchQuery = MiriRentalItem::query()->companyFilter($filters['company'] ?? null)->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) { foreach (['serial_tag_equipment_no', 'description', 'supplier', 'project_contract', 'current_location', 'issue_out_cog_no', 'received_backload_cog_no', 'return_cog_no'] as $column) $q->orWhere($column, 'like', "%{$s}%"); }));
        $applyDue = function ($q, $value) use ($today) { $q->whereNotIn('status', ['Received Backload', 'Off Hire', 'Returned to Supplier']); return match ($value) { 'overdue' => $q->where(fn ($q) => $q->whereDate('rental_due_date', '<', $today)->orWhere('status', 'Overdue')), 'due_7' => $q->whereBetween('rental_due_date', [$today, $today->copy()->addDays(7)]), 'due_30' => $q->whereBetween('rental_due_date', [$today->copy()->addDays(8), $today->copy()->addDays(30)]), 'later' => $q->whereDate('rental_due_date', '>', $today->copy()->addDays(30)), 'no_date' => $q->whereNull('rental_due_date'), }; };
        $filteredQuery = function (?string $except = null, ?string $due = null) use ($searchQuery, $filters, $applyDue) {
            $query = clone $searchQuery;
            foreach (['category' => 'category', 'section_1' => 'section_1', 'section_2' => 'section_2', 'location' => 'current_location', 'supplier' => 'supplier', 'status' => 'status'] as $key => $column) {
                if ($column !== $except && filled($filters[$key] ?? null)) $query->where($column, $filters[$key]);
            }
            $due ??= $filters['due_status'] ?? '';
            if ($due !== '') $applyDue($query, $due);
            if (($filters['missing_details'] ?? '') === 'missing') $query->where(fn ($q) => $q->whereNull('description')->orWhereNull('supplier')->orWhereNull('current_location'));
            return $query;
        };
        $query = $filteredQuery()->orderBy('section_1')->orderBy('section_2')->orderBy('description');
        $all = $filteredQuery(null, '');
        $active = (clone $all)->whereNotIn('status', ['Received Backload', 'Off Hire', 'Returned to Supplier']);
        $options = fn (string $column) => $filteredQuery($column)->whereNotNull($column)->whereRaw("TRIM({$column}) != ''")->distinct()->orderBy($column)->pluck($column)->values();
        $dueOptions = collect(['overdue', 'due_7', 'due_30', 'later', 'no_date'])->filter(fn ($due) => $filteredQuery(null, $due)->exists())->values();
        return Inertia::render('MiriRental/Index', ['rentals' => $query->paginate(25)->withQueryString(), 'summary' => ['total' => (clone $all)->count(), 'on_hire' => (clone $all)->where('status', 'On Hire')->count(), 'issued' => (clone $all)->where('status', 'Issued')->count(), 'backload' => (clone $all)->where('status', 'Received Backload')->count(), 'overdue' => (clone $active)->where(fn ($q) => $q->whereDate('rental_due_date', '<', $today)->orWhere('status', 'Overdue'))->count(), 'due_7' => (clone $active)->whereBetween('rental_due_date', [$today, $today->copy()->addDays(7)])->count(), 'due_30' => (clone $active)->whereBetween('rental_due_date', [$today->copy()->addDays(8), $today->copy()->addDays(30)])->count(), 'no_due_date' => (clone $active)->whereNull('rental_due_date')->count()], 'filters' => array_merge(['company' => '', 'search' => '', 'category' => '', 'section_1' => '', 'section_2' => '', 'location' => '', 'supplier' => '', 'status' => '', 'due_status' => '', 'missing_details' => ''], $filters), 'categoryOptions' => $options('category'), 'section1Options' => $options('section_1'), 'section2Options' => $options('section_2'), 'locationOptions' => $options('current_location'), 'supplierOptions' => $options('supplier'), 'statusOptions' => $options('status')->intersect(self::STATUSES)->values(), 'dueOptions' => $dueOptions, 'canEdit' => $request->user()->canEdit('assets')]);
    }

    public function create(Request $request): Response { $this->ensureMiri($request); abort_unless($request->user()?->canEdit('assets'), 403); return Inertia::render('MiriRental/Form', ['rental' => null, 'categories' => $this->categories()]); }
    public function store(SaveMiriRentalRequest $request, AuditLogger $auditLogger): RedirectResponse { $this->ensureMiri($request); $rental = MiriRentalItem::create([...$request->validated(), 'branch_id' => app(BranchContext::class)->id($request->user())]); $auditLogger->record('miri_rentals', 'created', "Added Miri rental record {$rental->description}.", $rental, after: $rental->toArray(), user: $request->user(), request: $request); return redirect()->route('miri-rental.show', $rental)->with('success', 'Rental record registered.'); }
    public function show(Request $request, MiriRentalItem $rental): Response { $this->ensureMiri($request); abort_unless($request->user()?->canRead('assets'), 403); return Inertia::render('MiriRental/Show', ['rental' => $rental]); }
    public function pdf(Request $request, MiriRentalItem $rental) { $this->ensureMiri($request); abort_unless($request->user()?->canRead('assets'), 403); return Pdf::loadView('miri-rentals.registration-pdf', ['rental' => $rental, 'logoPath' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/dayang-logo.png'))),])->download('miri-rental-registration-'.$rental->id.'.pdf'); }
    public function edit(Request $request, MiriRentalItem $rental): Response { $this->ensureMiri($request); abort_unless($request->user()?->canEdit('assets'), 403); return Inertia::render('MiriRental/Form', ['rental' => $rental, 'categories' => $this->categories()]); }
    public function update(SaveMiriRentalRequest $request, MiriRentalItem $rental, AuditLogger $auditLogger): RedirectResponse { $this->ensureMiri($request); $before = $rental->toArray(); $rental->update($request->validated()); $auditLogger->record('miri_rentals', 'updated', "Updated Miri rental record {$rental->description}.", $rental, before: $before, after: $rental->fresh()->toArray(), user: $request->user(), request: $request); return redirect()->route('miri-rental.show', $rental)->with('success', 'Rental record updated.'); }
    public function import(Request $request): Response { $this->ensureMiri($request); abort_unless($request->user()?->canEdit('assets'), 403); return Inertia::render('MiriRental/Import'); }
    public function storeImport(StoreMiriRentalImportRequest $request, MiriRentalImportService $service, AuditLogger $auditLogger): RedirectResponse { $this->ensureMiri($request); $summary = $service->import($request->file('file')); $auditLogger->record('miri_rentals', 'imported', "Imported Miri rental file: {$summary['created']} records created.", user: $request->user(), request: $request); return redirect()->route('miri-rental.index')->with('success', "Rental import complete. {$summary['created']} records created."); }
    private function categories(): array { return \App\Models\MiriInventoryCategory::query()->where('active', true)->orderBy('name')->pluck('name')->values()->all(); }
    private function ensureMiri(Request $request): void { abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404); }
}
