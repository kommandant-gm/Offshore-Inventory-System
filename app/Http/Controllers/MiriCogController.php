<?php

namespace App\Http\Controllers;

use App\Models\MajorEquipment;
use App\Models\MiriCog;
use App\Models\MiriRentalItem;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;

class MiriCogController extends Controller
{
    private const TYPES = ['Issue out', 'Transfer', 'Received backload', 'Return to supplier'];

    public function index(Request $request): Response
    {
        $this->ensureMiri($request); abort_unless($request->user()?->canRead('cogs'), 403);
        return Inertia::render('MiriCog/Index', ['cogs' => MiriCog::query()->with('creator')->withCount('items')->latest()->paginate(25)->through(fn (MiriCog $cog) => $this->summary($cog)), 'canEdit' => $request->user()->canEdit('cogs')]);
    }

    public function create(Request $request): Response
    {
        $this->ensureMiri($request); abort_unless($request->user()?->canEdit('cogs'), 403);
        $items = MajorEquipment::query()->get(['id', 'tag_no', 'description', 'unit', 'current_location'])->map(fn ($item) => ['key' => 'equipment:'.$item->id, 'type' => 'Major equipment', 'id' => $item->id, 'identifier' => $item->tag_no, 'description' => $item->description, 'unit' => $item->unit, 'location' => $item->current_location])
            ->concat(MiriRentalItem::query()->get(['id', 'serial_tag_equipment_no', 'description', 'unit', 'current_location'])->map(fn ($item) => ['key' => 'rental:'.$item->id, 'type' => 'Rental', 'id' => $item->id, 'identifier' => $item->serial_tag_equipment_no, 'description' => $item->description, 'unit' => $item->unit, 'location' => $item->current_location]))->values();
        return Inertia::render('MiriCog/Create', ['items' => $items, 'movementTypes' => self::TYPES]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureMiri($request); abort_unless($request->user()?->canEdit('cogs'), 403);
        $data = $request->validate(['movement_type' => ['required', 'in:'.implode(',', self::TYPES)], 'document_date' => ['required', 'date'], 'from_location' => ['nullable', 'string', 'max:255'], 'to_location' => ['nullable', 'string', 'max:255'], 'receiver_name' => ['nullable', 'string', 'max:255'], 'receiver_email' => ['nullable', 'email', 'max:255'], 'issued_by_name' => ['required', 'string', 'max:255'], 'remarks' => ['nullable', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.item_type' => ['required', 'in:Major equipment,Rental'], 'items.*.item_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'numeric', 'min:0.01'], 'items.*.remarks' => ['nullable', 'string']]);
        $branchId = app(BranchContext::class)->id($request->user());
        $cog = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $branchId, $request) {
            $prefix = 'MIRI-COG-'.now()->format('Y'); $next = ((int) MiriCog::withoutGlobalScopes()->where('branch_id', $branchId)->where('cog_no', 'like', $prefix.'-%')->count()) + 1;
            $cog = MiriCog::create([...collect($data)->except('items')->all(), 'branch_id' => $branchId, 'cog_no' => $prefix.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT), 'status' => 'draft', 'created_by' => $request->user()->id]);
            foreach ($data['items'] as $line) {
                $model = $line['item_type'] === 'Rental' ? MiriRentalItem::query()->findOrFail($line['item_id']) : MajorEquipment::query()->findOrFail($line['item_id']);
                $cog->items()->create(['branch_id' => $branchId, 'item_type' => $line['item_type'], 'item_id' => $model->id, 'identifier' => $line['item_type'] === 'Rental' ? $model->serial_tag_equipment_no : $model->tag_no, 'description' => $model->description, 'quantity' => $line['quantity'], 'unit' => $model->unit, 'current_location' => $model->current_location, 'remarks' => $line['remarks'] ?? null]);
            }
            return $cog;
        });
        $auditLogger->record('miri_cogs', 'created', "Created Miri COG {$cog->cog_no}.", $cog, after: $cog->toArray(), user: $request->user(), request: $request);
        return redirect()->route('miri-cogs.show', $cog)->with('success', "COG {$cog->cog_no} created.");
    }

    public function show(Request $request, MiriCog $cog): Response { $this->ensureMiri($request); abort_unless($request->user()?->canRead('cogs'), 403); $cog->load(['items', 'creator']); return Inertia::render('MiriCog/Show', ['cog' => $cog, 'canEdit' => $request->user()->canEdit('cogs')]); }
    public function sign(Request $request, MiriCog $cog, AuditLogger $auditLogger): RedirectResponse { $this->ensureMiri($request); abort_unless($request->user()?->canEdit('cogs'), 403); $data = $request->validate(['signature' => ['required', 'string', 'max:200000']]); $cog->update(['signature' => $data['signature'], 'signed_at' => now(), 'signed_ip' => $request->ip(), 'status' => 'signed', 'updated_by' => $request->user()->id]); $auditLogger->record('miri_cogs', 'signed', "Signed Miri COG {$cog->cog_no}.", $cog, after: ['status' => 'signed', 'signed_at' => $cog->signed_at?->toIso8601String()], user: $request->user(), request: $request); return back()->with('success', "COG {$cog->cog_no} signed."); }
    public function pdf(Request $request, MiriCog $cog) { $this->ensureMiri($request); abort_unless($request->user()?->canRead('cogs'), 403); $cog->load(['items', 'creator']); return Pdf::loadView('miri-cogs.pdf', ['cog' => $cog, 'logoPath' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/dayang-logo.png')))]) ->download('miri-cog-'.$cog->cog_no.'.pdf'); }
    private function summary(MiriCog $cog): array { return ['id' => $cog->id, 'cog_no' => $cog->cog_no, 'movement_type' => $cog->movement_type, 'document_date' => $cog->document_date?->format('Y-m-d'), 'from_location' => $cog->from_location, 'to_location' => $cog->to_location, 'status' => $cog->status, 'items_count' => $cog->items_count, 'created_by' => $cog->creator?->name ?: 'System', 'created_at' => $cog->created_at?->format('d M Y, H:i')]; }
    private function ensureMiri(Request $request): void { abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404); }
}
