<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveKemamanInventoryItemRequest;
use App\Models\KemamanInventoryItem;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KemamanInventoryController extends Controller
{
    public function dashboard(Request $request, BranchContext $branchContext): Response
    {
        $this->authorizeBranch($request, $branchContext);
        abort_unless($request->user()?->canRead('assets'), 403);

        $summary = KemamanInventoryItem::query()->selectRaw(
            'COUNT(*) as records, COALESCE(SUM(total_quantity), 0) as total_quantity, COALESCE(SUM(available_quantity), 0) as available_quantity, COALESCE(SUM(quantity_out), 0) as quantity_out, COALESCE(SUM(damaged_quantity), 0) as damaged_quantity, COALESCE(SUM(beyond_repair_quantity), 0) as beyond_repair_quantity, COALESCE(SUM(not_traceable_quantity), 0) as not_traceable_quantity'
        )->first();

        $expiry = [
            'expired' => KemamanInventoryItem::query()->whereNotNull('test_expiry_date')->whereDate('test_expiry_date', '<', today())->count(),
            'due_30_days' => KemamanInventoryItem::query()->whereBetween('test_expiry_date', [today(), today()->addDays(30)])->count(),
            'due_90_days' => KemamanInventoryItem::query()->whereBetween('test_expiry_date', [today()->addDays(31), today()->addDays(90)])->count(),
            'valid' => KemamanInventoryItem::query()->whereDate('test_expiry_date', '>', today()->addDays(90))->count(),
            'not_recorded' => KemamanInventoryItem::query()->whereNull('test_expiry_date')->count(),
        ];

        return Inertia::render('KemamanInventory/Dashboard', [
            'summary' => [
                'records' => (int) $summary->records,
                'total_quantity' => (int) $summary->total_quantity,
                'available_quantity' => (int) $summary->available_quantity,
                'quantity_out' => (int) $summary->quantity_out,
                'damaged_quantity' => (int) $summary->damaged_quantity,
                'beyond_repair_quantity' => (int) $summary->beyond_repair_quantity,
                'not_traceable_quantity' => (int) $summary->not_traceable_quantity,
            ],
            'statusDistribution' => KemamanInventoryItem::query()
                ->selectRaw('equipment_status as label, COUNT(*) as records, COALESCE(SUM(total_quantity), 0) as value')
                ->groupBy('equipment_status')
                ->orderByDesc('value')
                ->get()
                ->map(fn ($row) => [
                    'key' => $row->label,
                    'label' => Str::headline($row->label),
                    'records' => (int) $row->records,
                    'value' => (int) $row->value,
                ]),
            'categories' => KemamanInventoryItem::query()
                ->selectRaw('category as label, COUNT(*) as records, COALESCE(SUM(total_quantity), 0) as value')
                ->groupBy('category')
                ->orderByDesc('value')
                ->limit(10)
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'records' => (int) $row->records, 'value' => (int) $row->value]),
            'locations' => KemamanInventoryItem::query()
                ->selectRaw("COALESCE(location, 'Unassigned') as label, COUNT(*) as records, COALESCE(SUM(total_quantity), 0) as value")
                ->groupBy('location')
                ->orderByDesc('value')
                ->limit(8)
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'records' => (int) $row->records, 'value' => (int) $row->value]),
            'condition' => [
                ['label' => 'Available', 'value' => (int) $summary->available_quantity],
                ['label' => 'At Location', 'value' => KemamanInventoryItem::query()->sum('location_quantity')],
                ['label' => 'Damaged', 'value' => (int) $summary->damaged_quantity],
                ['label' => 'Beyond Repair', 'value' => (int) $summary->beyond_repair_quantity],
                ['label' => 'Traceability Variance', 'value' => abs((int) $summary->not_traceable_quantity)],
            ],
            'expiry' => $expiry,
            'expiringItems' => KemamanInventoryItem::query()
                ->whereNotNull('test_expiry_date')
                ->whereDate('test_expiry_date', '<=', today()->addDays(90))
                ->orderBy('test_expiry_date')
                ->limit(8)
                ->get()
                ->map(fn (KemamanInventoryItem $item) => [
                    'id' => $item->id,
                    'description' => $item->item_description,
                    'tag_no' => $item->tag_no,
                    'certificate_no' => $item->certificate_no,
                    'test_expiry_date' => $item->test_expiry_date?->format('Y-m-d'),
                    'days_remaining' => today()->diffInDays($item->test_expiry_date, false),
                ]),
            'attentionItems' => KemamanInventoryItem::query()
                ->where(fn (Builder $query) => $query
                    ->where('damaged_quantity', '>', 0)
                    ->orWhere('beyond_repair_quantity', '>', 0)
                    ->orWhere('not_traceable_quantity', '!=', 0)
                    ->orWhereIn('equipment_status', ['under_inspection', 'damaged', 'beyond_repair', 'not_traceable']))
                ->orderByDesc('damaged_quantity')
                ->orderByDesc('beyond_repair_quantity')
                ->limit(8)
                ->get()
                ->map(fn (KemamanInventoryItem $item) => [
                    'id' => $item->id,
                    'description' => $item->item_description,
                    'tag_no' => $item->tag_no,
                    'category' => $item->category,
                    'status' => Str::headline($item->equipment_status),
                    'damaged' => $item->damaged_quantity,
                    'beyond_repair' => $item->beyond_repair_quantity,
                    'not_traceable' => $item->not_traceable_quantity,
                ]),
            'recentItems' => KemamanInventoryItem::query()
                ->latest('updated_at')
                ->limit(6)
                ->get()
                ->map(fn (KemamanInventoryItem $item) => [
                    'id' => $item->id,
                    'description' => $item->item_description,
                    'tag_no' => $item->tag_no,
                    'category' => $item->category,
                    'status' => Str::headline($item->equipment_status),
                    'location' => $item->location,
                    'updated_at' => $item->updated_at?->format('d M Y, H:i'),
                ]),
            'canEdit' => $request->user()->canEdit('assets'),
        ]);
    }

    public function index(Request $request, BranchContext $branchContext): Response
    {
        $this->authorizeBranch($request, $branchContext);
        abort_unless($request->user()?->canRead('assets'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $baseQuery = KemamanInventoryItem::query();
        $query = (clone $baseQuery)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.trim($search).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('item_description', 'like', $term)
                    ->orWhere('tag_no', 'like', $term)
                    ->orWhere('certificate_no', 'like', $term)
                    ->orWhere('document_reference', 'like', $term)
                    ->orWhere('location', 'like', $term));
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('equipment_status', $status));

        $summary = (clone $baseQuery)->selectRaw(
            'COUNT(*) as records, COALESCE(SUM(total_quantity), 0) as total_quantity, COALESCE(SUM(available_quantity), 0) as available_quantity, COALESCE(SUM(damaged_quantity), 0) as damaged_quantity, COALESCE(SUM(beyond_repair_quantity), 0) as beyond_repair_quantity, COALESCE(SUM(not_traceable_quantity), 0) as not_traceable_quantity'
        )->first();

        return Inertia::render('KemamanInventory/Index', [
            'items' => $query
                ->orderBy('category')
                ->orderBy('item_description')
                ->orderBy('tag_no')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (KemamanInventoryItem $item) => $this->payload($item)),
            'summary' => [
                'records' => (int) $summary->records,
                'total_quantity' => (int) $summary->total_quantity,
                'available_quantity' => (int) $summary->available_quantity,
                'damaged_quantity' => (int) $summary->damaged_quantity,
                'beyond_repair_quantity' => (int) $summary->beyond_repair_quantity,
                'not_traceable_quantity' => (int) $summary->not_traceable_quantity,
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'categories' => $baseQuery->clone()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'statuses' => $this->statuses(),
            'canEdit' => $request->user()->canEdit('assets'),
        ]);
    }

    public function store(SaveKemamanInventoryItemRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $item = KemamanInventoryItem::create($request->validated());
        $auditLogger->record('kemaman_inventory', 'created', "Created Kemaman inventory record {$item->item_description}.", $item, after: $item->toArray(), user: $request->user(), request: $request);

        return back()->with('success', 'Kemaman inventory record created.');
    }

    public function update(SaveKemamanInventoryItemRequest $request, KemamanInventoryItem $item, AuditLogger $auditLogger): RedirectResponse
    {
        $before = $item->toArray();
        $item->update($request->validated());
        $auditLogger->record('kemaman_inventory', 'updated', "Updated Kemaman inventory record {$item->item_description}.", $item, before: $before, after: $item->fresh()->toArray(), user: $request->user(), request: $request);

        return back()->with('success', 'Kemaman inventory record updated.');
    }

    public function destroy(Request $request, KemamanInventoryItem $item, BranchContext $branchContext, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeBranch($request, $branchContext);
        abort_unless($request->user()?->canEdit('assets'), 403);

        $before = $item->toArray();
        $auditLogger->record('kemaman_inventory', 'deleted', "Deleted Kemaman inventory record {$item->item_description}.", $item, before: $before, user: $request->user(), request: $request);
        $item->delete();

        return back()->with('success', 'Kemaman inventory record deleted.');
    }

    private function authorizeBranch(Request $request, BranchContext $branchContext): void
    {
        abort_unless($branchContext->branch($request->user())?->code === 'KEMAMAN', 404);
    }

    private function payload(KemamanInventoryItem $item): array
    {
        return [
            ...$item->only([
                'id', 'category', 'item_description', 'size_swl', 'unit', 'tag_no',
                'total_quantity', 'quantity_in', 'quantity_out', 'available_quantity',
                'location_quantity', 'damaged_quantity', 'beyond_repair_quantity',
                'not_traceable_quantity', 'location', 'document_reference',
                'transfer_reference', 'certificate_no', 'equipment_status', 'remarks',
            ]),
            'date_issued' => $item->date_issued?->format('Y-m-d'),
            'backload_date' => $item->backload_date?->format('Y-m-d'),
            'test_expiry_date' => $item->test_expiry_date?->format('Y-m-d'),
        ];
    }

    private function statuses(): array
    {
        return [
            ['value' => 'available', 'label' => 'Available'],
            ['value' => 'in_use', 'label' => 'In Use'],
            ['value' => 'under_inspection', 'label' => 'Under Inspection'],
            ['value' => 'damaged', 'label' => 'Damaged'],
            ['value' => 'beyond_repair', 'label' => 'Beyond Repair'],
            ['value' => 'not_traceable', 'label' => 'Not Traceable'],
        ];
    }
}
