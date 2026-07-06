<?php

namespace App\Http\Controllers;

use App\Actions\Cogs\CreateCogAction;
use App\Http\Requests\StoreCogRequest;
use App\Models\Cog;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CogController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('cogs'), 403);

        return Inertia::render('Cogs/Index', [
            'cogs' => Cog::query()
                ->withCount('items')
                ->latest('document_date')
                ->latest('id')
                ->paginate(12)
                ->through(fn (Cog $cog) => [
                    'id' => $cog->id,
                    'cog_no' => $cog->cog_no,
                    'document_date' => $cog->document_date?->format('Y-m-d'),
                    'consignee' => $cog->consignee,
                    'destination' => $cog->destination,
                    'vessel' => $cog->vessel,
                    'status' => $cog->status,
                    'receiver_name' => $cog->receiver_name,
                    'receiver_email' => $cog->receiver_email,
                    'items_count' => $cog->items_count,
                    'approval_sent_at' => $cog->approval_sent_at?->format('Y-m-d H:i'),
                ]),
        ]);
    }

    public function create(CreateCogAction $createCogAction): Response
    {
        abort_unless(request()->user()?->canEdit('cogs'), 403);

        return Inertia::render('Cogs/Create', [
            'items' => InventoryItem::query()
                ->orderBy('item_code')
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'value' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'uom' => $item->uom,
                    'unit_price' => $item->standard_cost,
                ]),
            'draftCogNo' => $createCogAction->draftNumber(),
        ]);
    }

    public function store(StoreCogRequest $request, CreateCogAction $createCogAction): RedirectResponse
    {
        $cog = $createCogAction->execute($request->validated(), $request->user()?->id);

        return redirect()->route('cogs.show', $cog)->with('success', "COG {$cog->cog_no} created.");
    }

    public function show(Cog $cog): Response
    {
        abort_unless(request()->user()?->canRead('cogs'), 403);

        $cog->load(['items.inventoryItem', 'creator']);

        return Inertia::render('Cogs/Show', [
            'cog' => [
                'id' => $cog->id,
                'cog_no' => $cog->cog_no,
                'document_date' => $cog->document_date?->format('Y-m-d'),
                'consignee' => $cog->consignee,
                'shipper' => $cog->shipper,
                'destination' => $cog->destination,
                'vessel' => $cog->vessel,
                'department' => $cog->department,
                'receiver_name' => $cog->receiver_name,
                'receiver_email' => $cog->receiver_email,
                'cc_emails' => $cog->cc_emails ?? [],
                'receiver_designation' => $cog->receiver_designation,
                'issued_by_name' => $cog->issued_by_name,
                'issued_by_designation' => $cog->issued_by_designation,
                'checked_by_name' => $cog->checked_by_name,
                'checked_by_designation' => $cog->checked_by_designation,
                'status' => $cog->status,
                'approval_sent_at' => $cog->approval_sent_at?->format('Y-m-d H:i'),
                'approved_at' => $cog->approved_at?->format('Y-m-d H:i'),
                'rejected_at' => $cog->rejected_at?->format('Y-m-d H:i'),
                'receiver_remarks' => $cog->receiver_remarks,
                'remarks' => $cog->remarks,
                'items' => $cog->items->map(fn ($item) => [
                    'id' => $item->id,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'part_no' => $item->part_no,
                    'full_description' => $item->full_description,
                    'measurement_cu_metre' => $item->measurement_cu_metre,
                    'gross_weight_kg' => $item->gross_weight_kg,
                    'po_no' => $item->po_no,
                    'ex_location' => $item->ex_location,
                    'serial_no' => $item->serial_no,
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total_amount,
                    'remarks' => $item->remarks,
                ]),
            ],
        ]);
    }

}
