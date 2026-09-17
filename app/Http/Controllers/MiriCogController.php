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
        return Inertia::render('MiriCog/Create', ['movementTypes' => self::TYPES, 'registerTypes' => \App\Services\MiriCogSource::TYPES]);
    }

    public function items(Request $request, \App\Services\MiriCogSource $sources)
    {
        $this->ensureMiri($request); abort_unless($request->user()?->canEdit('cogs'), 403);
        $data = $request->validate(['search'=>['nullable','string','max:255'], 'type'=>['required',\Illuminate\Validation\Rule::in($sources::TYPES)]]);
        $branch = app(BranchContext::class)->id($request->user());
        $identifier = $sources->identifier($data['type']);
        $items = $sources->query($data['type'],$branch)
            ->when($data['search'] ?? null, fn ($q,$term) => $q->where(fn ($q) => $q->where($identifier,'like','%'.$term.'%')->orWhere('description','like','%'.$term.'%')->orWhere('current_location','like','%'.$term.'%')->when(ctype_digit($term),fn ($q)=>$q->orWhere('id',$term))))
            ->orderBy('id')->limit(26)->get();
        $allocations = app(\App\Services\MiriCogAvailability::class)->outstanding($branch, $data['type'], $items->pluck('id')->all());
        return response()->json(['has_more'=>$items->count()>25, 'items'=>$items->take(25)->map(fn ($item)=>[
            ...$sources->snapshot($item,$data['type']), 'key'=>$data['type'].':'.$item->id, 'id'=>$item->id,
            'type'=>$data['type'], 'location'=>$item->current_location,
            'allocated_to'=>array_values(array_unique(array_column($allocations[$item->id] ?? [], 'cog_no'))),
            'outstanding_quantity'=>array_sum(array_column($allocations[$item->id] ?? [], 'quantity')) / 1000,
        ])->values()]);
    }

    public function store(Request $request, AuditLogger $auditLogger, \App\Services\MiriCogSource $sources): RedirectResponse
    {
        $this->ensureMiri($request); abort_unless($request->user()?->canEdit('cogs'),403);
        $rules = [
            'movement_type'=>['required',\Illuminate\Validation\Rule::in(self::TYPES)], 'document_date'=>['required','date_format:Y-m-d'],
            'receiver_email'=>['nullable','email','max:255'], 'remarks'=>['nullable','string','max:1000'],
            'items'=>['required','array','min:1','max:100'],
            'items.*.item_type'=>['required',\Illuminate\Validation\Rule::in($sources::TYPES)],
            'items.*.item_id'=>['required','integer'], 'items.*.quantity'=>['required','numeric','min:0.001','max:999999999.999','decimal:0,3'],
        ];
        foreach (['from_location','to_location','receiver_name','issued_by_name','consignee_name','consignee_department','from_department','copy_to','destination','issued_designation','verified_by_name','verified_designation','receiver_designation'] as $key) $rules[$key] = [$key === 'issued_by_name' ? 'required' : 'nullable','string','max:255'];
        foreach (['issued_date','verified_date','received_date'] as $key) $rules[$key] = ['nullable','date_format:Y-m-d'];
        foreach (['description','size_model','identifier','serial_no','mr_reference','remarks','unit'] as $key) $rules['items.*.'.$key] = ['nullable','string','max:'.($key === 'unit' ? 20 : (in_array($key,['remarks','mr_reference']) ? 1000 : 255))];
        $data = $request->validate($rules);
        $branch = app(BranchContext::class)->id($request->user());
        $cog = \Illuminate\Support\Facades\DB::transaction(function () use ($data,$branch,$request,$sources,$auditLogger) {
            // Serialize availability checks, returns, cancellation and numbering per branch.
            \App\Models\Branch::whereKey($branch)->lockForUpdate()->firstOrFail();
            app(\App\Services\MiriCogAvailability::class)->validate($branch, $data['movement_type'], $data['items']);
            $prefix = 'DESB/'.now()->format('y').'/';
            $legacyPrefix = 'MIRI-COG-'.now()->format('Y').'-';
            $last = MiriCog::withoutGlobalScopes()->where('branch_id', $branch)
                ->where(fn ($q) => $q->where('cog_no', 'like', $prefix.'%')->orWhere('cog_no', 'like', $legacyPrefix.'%'))
                ->pluck('cog_no')->map(function ($number) use ($prefix, $legacyPrefix) {
                    $suffix = substr($number, str_starts_with($number, $prefix) ? strlen($prefix) : strlen($legacyPrefix));
                    return ctype_digit($suffix) ? (int) $suffix : 0;
                })->max() ?? 0;
            $cog = MiriCog::create([...collect($data)->except('items')->all(), 'branch_id'=>$branch,
                'cog_no'=>$prefix.str_pad((string)($last+1),3,'0',STR_PAD_LEFT), 'status'=>'draft', 'created_by'=>$request->user()->id]);
            foreach ($data['items'] as $i=>$line) {
                $model = $sources->query($line['item_type'],$branch)->findOrFail($line['item_id']);
                $snapshot = $sources->snapshot($model,$line['item_type']);
                foreach (['description','size_model','identifier','serial_no','mr_reference','remarks','unit'] as $key) if (array_key_exists($key,$line)) $snapshot[$key] = $line[$key];
                if ($line['item_type'] === 'Paint') {
                    $snapshot['identifier'] = null; // Batch is not an equipment tag.
                    $snapshot['unit'] = strtoupper(trim($snapshot['unit'] ?? ''));
                    if (! in_array($snapshot['unit'],['CAN','LTR'])) throw \Illuminate\Validation\ValidationException::withMessages(["items.$i.unit"=>'Choose CAN or LTR for Paint.']);
                }
                if (blank($snapshot['unit'])) throw \Illuminate\Validation\ValidationException::withMessages(["items.$i.unit"=>'Enter the issue unit; it is missing from the source record.']);
                $cog->items()->create([...$snapshot,'branch_id'=>$branch,'item_type'=>$line['item_type'],'item_id'=>$model->id,'quantity'=>$line['quantity']]);
            }
            $auditLogger->record('miri_cogs','created',"Created Miri COG {$cog->cog_no}. Document only; no stock movements.",$cog,after:$cog->toArray(),user:$request->user(),request:$request);
            return $cog;
        });
        return redirect()->route('miri-cogs.show',$cog)->with('success',"COG {$cog->cog_no} created.");
    }

    private function authorizeDocument(Request $request, MiriCog $cog, bool $edit = false): void
    {
        $this->ensureMiri($request);
        abort_unless($cog->branch_id === app(BranchContext::class)->id($request->user()),404);
        abort_unless($edit ? $request->user()->canEdit('cogs') : $request->user()->canRead('cogs'),403);
    }
    public function show(Request $request, MiriCog $cog): Response
    {
        $this->authorizeDocument($request,$cog);
        $cog->load(['items','creator']);
        return Inertia::render('MiriCog/Show',['cog'=>$cog,'document'=>app(\App\Services\MiriCogDocument::class)->data($cog),'canEdit'=>$request->user()->canEdit('cogs')]);
    }
    public function sign(Request $request, MiriCog $cog, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeDocument($request,$cog,true);
        $data = $request->validate(['signature'=>['required','string','max:200000']]);
        $valid = preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/D',$data['signature'],$matches);
        $bytes = $valid ? base64_decode($matches[1],true) : false;
        $info = $bytes ? @getimagesizefromstring($bytes) : false;
        if (! $info || $info[2] !== IMAGETYPE_PNG || $info[0]>2000 || $info[1]>1000) throw \Illuminate\Validation\ValidationException::withMessages(['signature'=>'Use a valid PNG signature drawn below.']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($cog,$request,$data,$auditLogger) {
            $locked = MiriCog::whereKey($cog->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->signature || $locked->status !== 'draft',409,'Only an unsigned draft COG can be signed.');
            $locked->update(['signature'=>$data['signature'],'signed_at'=>now(),'signed_ip'=>$request->ip(),'status'=>'signed','updated_by'=>$request->user()->id]);
            $auditLogger->record('miri_cogs','signed',"Signed Miri COG {$locked->cog_no}.",$locked,after:['status'=>'signed','signed_at'=>$locked->signed_at?->toIso8601String()],user:$request->user(),request:$request);
        });
        return back()->with('success','Receiver signature recorded.');
    }
    public function cancel(Request $request, MiriCog $cog, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeDocument($request, $cog, true);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($cog, $request, $auditLogger, $data) {
            \App\Models\Branch::whereKey($cog->branch_id)->lockForUpdate()->firstOrFail();
            $locked = MiriCog::whereKey($cog->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft' && ! $locked->signature && in_array($locked->movement_type, \App\Services\MiriCogAvailability::OUTBOUND, true), 409, 'Only an unfulfilled draft outbound note can be cancelled.');
            foreach ($locked->items as $item) {
                $laterReturn = \App\Models\MiriCogItem::where('branch_id', $locked->branch_id)
                    ->where('item_type', $item->item_type)->where('item_id', $item->item_id)
                    ->where('miri_cog_id', '>', $locked->id)
                    ->whereHas('cog', fn ($q) => $q->where('movement_type', 'Received backload')->where('status', '!=', 'cancelled'))->exists();
                abort_if($laterReturn, 409, 'This note has subsequent return history and cannot be cancelled.');
            }
            $locked->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
            $auditLogger->record('miri_cogs', 'cancelled', "Cancelled unfulfilled Miri COG {$locked->cog_no}.", $locked, after: ['status' => 'cancelled', 'reason' => $data['reason']], user: $request->user(), request: $request);
        });
        return back()->with('success', 'Unfulfilled issue note cancelled; equipment reservations released.');
    }

    public function pdf(Request $request, MiriCog $cog)
    {
        $this->authorizeDocument($request,$cog);
        $cog->load(['items','creator']);
        $pdf = Pdf::loadView('miri-cogs.pdf',[
            'cog'=>$cog,'document'=>app(\App\Services\MiriCogDocument::class)->data($cog),
            'logoPath'=>'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/dayang-logo.png'))),
        ])->setPaper('a4','landscape');
        // Uncompressed font streams avoid corrupted embedded-font rendering on this host.
        return response($pdf->output(['compress'=>0]),200,[
            'Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="miri-cog-'.preg_replace('/[^A-Za-z0-9_-]/','-',$cog->cog_no).'.pdf"',
            'Cache-Control'=>'private, no-store',
        ]);
    }
    private function summary(MiriCog $cog): array { return ['id' => $cog->id, 'cog_no' => $cog->cog_no, 'movement_type' => $cog->movement_type, 'document_date' => $cog->document_date?->format('Y-m-d'), 'from_location' => $cog->from_location, 'to_location' => $cog->to_location, 'status' => $cog->status, 'items_count' => $cog->items_count, 'created_by' => $cog->creator?->name ?: 'System', 'created_at' => $cog->created_at?->format('d M Y, H:i')]; }
    private function ensureMiri(Request $request): void { abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404); }
}
