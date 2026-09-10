<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveConstructionRequest;
use App\Jobs\ImportMiriConstruction;
use App\Models\MiriConstructionItem;
use App\Services\{BranchContext, ConstructionCsvService, ConstructionRecordService};
use App\Support\ConstructionFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MiriConstructionController extends Controller
{
    private function authorizePage(Request $request, bool $edit = false): int
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        abort_unless($edit ? $request->user()?->canEdit('assets') : $request->user()?->canRead('assets'), 403);
        return $branch->id;
    }

    private function schema(): array
    {
        return ['fields' => ConstructionFields::FIELDS, 'attachmentSlots' => ConstructionFields::ATTACHMENTS];
    }

    private function record(MiriConstructionItem $item, bool $private): array
    {
        $data = $item->toArray();
        $data['review_flags'] = $item->reviewFlags();
        $data['attachments'] = collect($item->attachments ?? [])->map(fn ($file) => collect($file)->except('path')->all())->all();
        if ($private) {
            $data['personnel_details'] = $item->personnel_details;
            $data['original_values'] = $item->source_values;
        }
        return $data;
    }

    public function index(Request $request)
    {
        $this->authorizePage($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:255'],
            'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'],
            'quality' => ['nullable', 'in:review,duplicates'], 'page' => ['nullable', 'integer', 'min:1']]);
        $base = MiriConstructionItem::query();
        $query = (clone $base)->select('miri_construction_items.id', 'category', 'section_1', 'section_2', 'description', 'tag_no', 'stock_balance', 'unit', 'current_location', 'storage_rack', 'certificate_due_date', 'needs_review')->withDuplicateCount()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) {
                foreach (['description', 'tag_no', 'model_brand', 'current_location', 'storage_rack'] as $column) $q->orWhere($column, 'like', '%'.$s.'%');
            }));
        foreach (['category', 'section_1', 'section_2'] as $key) $query->when($filters[$key] ?? null, fn ($q, $value) => $q->where($key, $value));
        $query->when($filters['location'] ?? null, fn ($q, $v) => $q->where('current_location', $v));
        if (($filters['quality'] ?? '') === 'duplicates') $query->duplicateTag();
        if (($filters['quality'] ?? '') === 'review') $query->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateTag()));
        $options = fn ($column) => (clone $base)->whereNotNull($column)->where($column, '<>', '')->distinct()->orderBy($column)->pluck($column);
        return Inertia::render('Construction/Index', [
            'records' => $query->orderBy('category')->orderBy('description')->orderBy('miri_construction_items.id')->paginate(25)->withQueryString(),
            'filters' => $filters, 'canEdit' => $request->user()->canEdit('assets'),
            'options' => ['category' => $options('category'), 'section_1' => $options('section_1'), 'section_2' => $options('section_2'), 'location' => $options('current_location')],
            'summary' => ['total' => (clone $base)->count(), 'duplicates' => (clone $base)->duplicateTag()->count(),
                'review' => (clone $base)->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateTag()))->count(),
                'dated_certificates' => (clone $base)->whereNotNull('certificate_due_date')->count()],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePage($request, true);
        return Inertia::render('Construction/Form', ['record' => null, ...$this->schema()]);
    }

    public function store(SaveConstructionRequest $request, ConstructionRecordService $service)
    {
        $branchId = $this->authorizePage($request, true);
        $record = $service->save(new MiriConstructionItem, $request->validated(), $branchId, $request->user(), $request);
        return redirect()->route('construction.show', $record)->with('success', 'Construction record registered.');
    }

    public function show(Request $request, MiriConstructionItem $construction)
    {
        $branchId = $this->authorizePage($request);
        abort_unless($construction->branch_id === $branchId, 404);
        $duplicates = filled($construction->normalized_tag) ? MiriConstructionItem::query()->where('normalized_tag', $construction->normalized_tag)->where('id', '<>', $construction->id)->get(['id', 'description', 'tag_no', 'current_location']) : [];
        return Inertia::render('Construction/Show', ['record' => $this->record($construction, $request->user()->canEdit('assets')),
            'canEdit' => $request->user()->canEdit('assets'), 'duplicates' => $duplicates, ...$this->schema()]);
    }

    public function edit(Request $request, MiriConstructionItem $construction)
    {
        $branchId = $this->authorizePage($request, true);
        abort_unless($construction->branch_id === $branchId, 404);
        return Inertia::render('Construction/Form', ['record' => $this->record($construction, true), ...$this->schema()]);
    }

    public function update(SaveConstructionRequest $request, MiriConstructionItem $construction, ConstructionRecordService $service)
    {
        $branchId = $this->authorizePage($request, true);
        abort_unless($construction->branch_id === $branchId, 404);
        $service->save($construction, $request->validated(), $branchId, $request->user(), $request);
        return redirect()->route('construction.show', $construction)->with('success', 'Record updated. Historical quantities were not replayed.');
    }

    public function attachment(Request $request, MiriConstructionItem $construction, string $slot)
    {
        $branchId = $this->authorizePage($request);
        abort_unless($construction->branch_id === $branchId, 404);
        abort_unless(array_key_exists($slot, ConstructionFields::ATTACHMENTS), 404);
        $file = ($construction->attachments ?? [])[$slot] ?? null;
        abort_unless($file && Storage::disk('construction')->exists($file['path']), 404);
        $extension = match ($file['mime']) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf', default => abort(404) };
        return Storage::disk('construction')->response($file['path'], 'construction-certificate-'.$construction->id.'-'.$slot.'.'.$extension,
            ['Content-Type' => $file['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"],
            $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function importPage(Request $request)
    {
        $branchId = $this->authorizePage($request, true);
        return Inertia::render('Construction/Import', ['recentImports' => DB::table('miri_construction_imports')->where('branch_id', $branchId)
            ->where('user_id', $request->user()->id)->latest()->limit(10)->get(['id', 'filename', 'status', 'created_at'])]);
    }

    private function file(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:20480']]);
        return $request->file('file');
    }

    public function preview(Request $request, ConstructionCsvService $service)
    {
        $branchId = $this->authorizePage($request, true);
        $report = $service->preview($this->file($request), $branchId);
        $request->session()->put('construction_preview', $branchId.':'.$report['file_hash']);
        return response()->json($report);
    }

    public function queueImport(Request $request)
    {
        $branchId = $this->authorizePage($request, true);
        $file = $this->file($request);
        $hash = hash_file('sha256', $file->getRealPath());
        abort_unless($request->session()->get('construction_preview') === $branchId.':'.$hash, 422, 'Preview this exact file before importing.');
        if (DB::table('miri_construction_imports')->where('branch_id', $branchId)->where('active_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['file' => 'This file is already queued or imported. Open its progress page.']);
        }
        $id = (string) Str::uuid();
        $path = $file->store('imports', 'construction');
        try {
            DB::table('miri_construction_imports')->insert(['id' => $id, 'branch_id' => $branchId, 'user_id' => $request->user()->id,
                'file_hash' => $hash, 'active_hash' => $hash, 'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'file_path' => $path, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
            ImportMiriConstruction::dispatch($id);
        } catch (\Throwable $error) {
            Storage::disk('construction')->delete($path);
            DB::table('miri_construction_imports')->where('id', $id)->where('status', '!=', 'completed')->update(['status' => 'failed', 'active_hash' => null, 'error' => 'Unable to queue import.', 'updated_at' => now()]);
            if ($error instanceof \Illuminate\Database\UniqueConstraintViolationException) throw ValidationException::withMessages(['file' => 'This file is already queued or imported.']);
            throw $error;
        }
        $request->session()->forget('construction_preview');
        return redirect()->route('construction.import.status', $id);
    }

    public function importStatus(Request $request, string $task)
    {
        $branchId = $this->authorizePage($request);
        $record = DB::table('miri_construction_imports')->where('id', $task)->where('branch_id', $branchId)->where('user_id', $request->user()->id)
            ->first(['id', 'filename', 'status', 'result', 'error']);
        abort_unless($record, 404);
        $record->result = $record->result ? json_decode($record->result, true) : null;
        return Inertia::render('Construction/ImportStatus', ['task' => $record]);
    }
}
