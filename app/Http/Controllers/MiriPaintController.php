<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePaintRequest;
use App\Jobs\ImportMiriPaint;
use App\Models\MiriPaintItem;
use App\Services\{BranchContext, PaintCsvService, PaintRecordService};
use App\Support\PaintFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MiriPaintController extends Controller
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
        return ['fields' => PaintFields::FIELDS, 'dateStates' => ['unconfirmed', 'confirmed', 'not_recorded']];
    }

    private function record(MiriPaintItem $item, bool $private): array
    {
        $data = $item->toArray();
        $data['review_flags'] = $item->reviewFlags();
        if ($private) {
            $data['original_values'] = $item->source_values;
        }
        return $data;
    }

    public function index(Request $request)
    {
        $this->authorizePage($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:255'],
            'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'],
            'quality' => ['nullable', 'in:review,duplicates,unconfirmed,expired,due_30_days'], 'page' => ['nullable', 'integer', 'min:1']]);
        $base = MiriPaintItem::query();
        $query = (clone $base)->select('miri_paint_items.id', 'category', 'section_1', 'section_2', 'description', 'batch_no', 'balance_cans', 'balance_litres', 'current_location', 'best_before_date', 'manufacture_date', 'date_status', 'needs_review')->withDuplicateCount()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) {
                foreach (['description', 'batch_no', 'current_location', 'storage_rack'] as $column) $q->orWhere($column, 'like', '%'.$s.'%');
            }));
        foreach (['category', 'section_1', 'section_2'] as $key) $query->when($filters[$key] ?? null, fn ($q, $value) => $q->where($key, $value));
        $query->when($filters['location'] ?? null, fn ($q, $v) => $q->where('current_location', $v));
        if (($filters['quality'] ?? '') === 'duplicates') $query->duplicateBatch();
        if (($filters['quality'] ?? '') === 'review') $query->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateBatch()));
        if (($filters['quality'] ?? '') === 'unconfirmed') $query->where('date_status', 'unconfirmed');
        if (($filters['quality'] ?? '') === 'expired') $query->expiryEligible()->where('best_before_date', '<', today()->toDateString());
        if (($filters['quality'] ?? '') === 'due_30_days') $query->expiryEligible()->whereBetween('best_before_date', [today()->toDateString(), today()->addDays(30)->toDateString()]);
        $options = fn ($column) => (clone $base)->whereNotNull($column)->where($column, '<>', '')->distinct()->orderBy($column)->pluck($column);
        return Inertia::render('Paint/Index', [
            'records' => $query->orderBy('category')->orderBy('description')->orderBy('miri_paint_items.id')->paginate(25)->withQueryString(),
            'filters' => $filters, 'canEdit' => $request->user()->canEdit('assets'),
            'options' => ['category' => $options('category'), 'section_1' => $options('section_1'), 'section_2' => $options('section_2'), 'location' => $options('current_location')],
            'summary' => ['total' => (clone $base)->count(), 'duplicates' => (clone $base)->duplicateBatch()->count(),
                'review' => (clone $base)->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateBatch()))->count(),
                'unconfirmed_dates' => (clone $base)->where('date_status', 'unconfirmed')->count(),
                'expired' => (clone $base)->expiryEligible()->where('best_before_date', '<', today()->toDateString())->count(),
                'due_30_days' => (clone $base)->expiryEligible()->whereBetween('best_before_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->count()],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePage($request, true);
        return Inertia::render('Paint/Form', ['record' => null, ...$this->schema()]);
    }

    public function store(SavePaintRequest $request, PaintRecordService $service)
    {
        $branchId = $this->authorizePage($request, true);
        $record = $service->save(new MiriPaintItem, $request->validated(), $branchId, $request->user(), $request);
        return redirect()->route('paint.show', $record)->with('success', 'Paint record registered.');
    }

    public function show(Request $request, MiriPaintItem $paint)
    {
        $branchId = $this->authorizePage($request);
        abort_unless($paint->branch_id === $branchId, 404);
        $duplicates = filled($paint->match_key) ? MiriPaintItem::query()->where('match_key', $paint->match_key)->where('id', '<>', $paint->id)->get(['id', 'description', 'batch_no', 'current_location']) : [];
        return Inertia::render('Paint/Show', ['record' => $this->record($paint, $request->user()->canEdit('assets')),
            'canEdit' => $request->user()->canEdit('assets'), 'duplicates' => $duplicates, ...$this->schema()]);
    }

    public function edit(Request $request, MiriPaintItem $paint)
    {
        $branchId = $this->authorizePage($request, true);
        abort_unless($paint->branch_id === $branchId, 404);
        return Inertia::render('Paint/Form', ['record' => $this->record($paint, true), ...$this->schema()]);
    }

    public function update(SavePaintRequest $request, MiriPaintItem $paint, PaintRecordService $service)
    {
        $branchId = $this->authorizePage($request, true);
        abort_unless($paint->branch_id === $branchId, 404);
        $service->save($paint, $request->validated(), $branchId, $request->user(), $request);
        return redirect()->route('paint.show', $paint)->with('success', 'Record updated. Historical quantities were not replayed.');
    }

    public function importPage(Request $request)
    {
        $branchId = $this->authorizePage($request, true);
        return Inertia::render('Paint/Import', ['recentImports' => DB::table('miri_paint_imports')->where('branch_id', $branchId)
            ->where('user_id', $request->user()->id)->latest()->limit(10)->get(['id', 'filename', 'status', 'created_at'])]);
    }

    private function file(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:20480']]);
        return $request->file('file');
    }

    public function preview(Request $request, PaintCsvService $service)
    {
        $branchId = $this->authorizePage($request, true);
        $report = $service->preview($this->file($request), $branchId);
        $request->session()->put('paint_preview', $branchId.':'.$report['file_hash']);
        return response()->json($report);
    }

    public function queueImport(Request $request)
    {
        $branchId = $this->authorizePage($request, true);
        $file = $this->file($request);
        $hash = hash_file('sha256', $file->getRealPath());
        abort_unless($request->session()->get('paint_preview') === $branchId.':'.$hash, 422, 'Preview this exact file before importing.');
        if (DB::table('miri_paint_imports')->where('branch_id', $branchId)->where('active_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['file' => 'This file is already queued or imported. Open its progress page.']);
        }
        $id = (string) Str::uuid();
        $path = $file->store('imports', 'paint');
        try {
            DB::table('miri_paint_imports')->insert(['id' => $id, 'branch_id' => $branchId, 'user_id' => $request->user()->id,
                'file_hash' => $hash, 'active_hash' => $hash, 'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'file_path' => $path, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
            ImportMiriPaint::dispatch($id);
        } catch (\Throwable $error) {
            Storage::disk('paint')->delete($path);
            DB::table('miri_paint_imports')->where('id', $id)->where('status', '!=', 'completed')->update(['status' => 'failed', 'active_hash' => null, 'error' => 'Unable to queue import.', 'updated_at' => now()]);
            if ($error instanceof \Illuminate\Database\UniqueConstraintViolationException) throw ValidationException::withMessages(['file' => 'This file is already queued or imported.']);
            throw $error;
        }
        $request->session()->forget('paint_preview');
        return redirect()->route('paint.import.status', $id);
    }

    public function importStatus(Request $request, string $task)
    {
        $branchId = $this->authorizePage($request);
        $record = DB::table('miri_paint_imports')->where('id', $task)->where('branch_id', $branchId)->where('user_id', $request->user()->id)
            ->first(['id', 'filename', 'status', 'result', 'error']);
        abort_unless($record, 404);
        $record->result = $record->result ? json_decode($record->result, true) : null;
        return Inertia::render('Paint/ImportStatus', ['task' => $record]);
    }
}
