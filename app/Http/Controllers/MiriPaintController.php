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
        if (! ($request->routeIs('paint.index') && $request->boolean('filter_options'))) app(\App\Services\PaintStockLedger::class)->rollover($branch->id);
        return $branch->id;
    }

    private function schema(): array
    {
        return ['fields' => PaintFields::FIELDS, 'dateStates' => ['unconfirmed', 'confirmed', 'not_recorded']];
    }

    private function record(MiriPaintItem $item, bool $private): array
    {
        $item->refresh();
        $data = $item->toArray();
        $data['stock_token'] = app(\App\Services\PaintStockLedger::class)->token($item);
        $data['review_flags'] = $item->reviewFlags();
        if ($private) {
            $data['original_values'] = $item->source_values;
        }
        return $data;
    }

    public function index(Request $request)
    {
        $this->authorizePage($request);
        $filters = $request->validate(['company' => ['nullable', 'in:DESB,FTSB,unassigned'], 'search' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:255'],
            'section_1' => ['nullable', 'string', 'max:255'], 'section_2' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'],
            'quality' => ['nullable', 'in:review,duplicates,unconfirmed,expired,due_30_days'], 'page' => ['nullable', 'integer', 'min:1']]);
        $base = MiriPaintItem::query()->companyFilter($filters['company'] ?? null);
        $searchQuery = (clone $base)->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($q) use ($s) {
            foreach (['description', 'batch_no', 'current_location', 'storage_rack'] as $column) $q->orWhere($column, 'like', '%'.$s.'%');
        }));
        $filteredQuery = function (?string $except = null, ?string $quality = null) use ($searchQuery, $filters) {
            $query = clone $searchQuery;
            foreach (['category' => 'category', 'section_1' => 'section_1', 'section_2' => 'section_2', 'location' => 'current_location'] as $key => $column) {
                if ($column !== $except && filled($filters[$key] ?? null)) $query->where($column, $filters[$key]);
            }
            if (($quality ?? ($filters['quality'] ?? '')) === 'duplicates') $query->duplicateBatch();
            if (($quality ?? ($filters['quality'] ?? '')) === 'review') $query->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateBatch()));
            if (($quality ?? ($filters['quality'] ?? '')) === 'unconfirmed') $query->where('date_status', 'unconfirmed');
            if (($quality ?? ($filters['quality'] ?? '')) === 'expired') $query->expiryEligible()->where('best_before_date', '<', today()->toDateString());
            if (($quality ?? ($filters['quality'] ?? '')) === 'due_30_days') $query->expiryEligible()->whereBetween('best_before_date', [today()->toDateString(), today()->addDays(30)->toDateString()]);
            return $query;
        };
        $query = $filteredQuery()->select('company', 'miri_paint_items.id', 'category', 'section_1', 'section_2', 'description', 'batch_no', 'balance_cans', 'balance_litres', 'current_location', 'best_before_date', 'manufacture_date', 'date_status', 'needs_review')->withDuplicateCount();
        $options = fn ($column) => $filteredQuery($column)->whereNotNull($column)->whereRaw("TRIM({$column}) != ''")->distinct()->orderBy($column)->pluck($column)->values();
        $summaryQuery = $filteredQuery(null, '');
        $qualityOptions = collect(['review', 'duplicates', 'unconfirmed', 'expired', 'due_30_days'])->filter(fn ($quality) => $filteredQuery(null, $quality)->exists())->values();
        if ($request->boolean('filter_options')) return response()->json([
            'options' => ['category' => $options('category'), 'section_1' => $options('section_1'), 'section_2' => $options('section_2'), 'location' => $options('current_location')],
            'qualityOptions' => $qualityOptions,
        ]);
        $stockSummary = app(\App\Services\PaintStockSummary::class)->data($query);
        return Inertia::render('Paint/Index', [
            'stockSummary' => $stockSummary,
            'closingStockSummary' => app(\App\Services\PaintStockSummary::class)->closingByLocation($filteredQuery()),
            'records' => $query->orderBy('category')->orderBy('description')->orderBy('miri_paint_items.id')->paginate(25)->withQueryString(),
            'qualityOptions' => $qualityOptions,
            'filters' => $filters, 'canEdit' => $request->user()->canEdit('assets'),
            'options' => ['category' => $options('category'), 'section_1' => $options('section_1'), 'section_2' => $options('section_2'), 'location' => $options('current_location')],
            'summary' => ['total' => (clone $summaryQuery)->count(), 'duplicates' => (clone $summaryQuery)->duplicateBatch()->count(),
                'review' => (clone $summaryQuery)->where(fn ($q) => $q->where('needs_review', true)->orWhere(fn ($q) => $q->duplicateBatch()))->count(),
                'unconfirmed_dates' => (clone $summaryQuery)->where('date_status', 'unconfirmed')->count(),
                'expired' => (clone $summaryQuery)->expiryEligible()->where('best_before_date', '<', today()->toDateString())->count(),
                'due_30_days' => (clone $summaryQuery)->expiryEligible()->whereBetween('best_before_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->count()],
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
            'stockMonths' => DB::table('miri_paint_stock_months')->where('paint_item_id', $paint->id)->where('branch_id', $branchId)->orderByDesc('period')->get(),
            'stockMovements' => DB::table('miri_paint_stock_movements')->where('paint_item_id', $paint->id)->where('branch_id', $branchId)->orderByDesc('id')->paginate(25)->withQueryString(),
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
