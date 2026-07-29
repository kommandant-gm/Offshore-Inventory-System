<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveKemamanInventoryItemRequest;
use App\Models\KemamanInventoryItem;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KemamanInventoryController extends Controller
{
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
