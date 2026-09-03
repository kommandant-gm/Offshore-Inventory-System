<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\MajorEquipment;
use App\Models\MiriInventoryCategory;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('categories'), 403);

        $branch = app(BranchContext::class)->branch(request()->user());
        if ($branch?->code === 'MIRI') {
            return Inertia::render('Shared/Categories/Index', [
                'categories' => MiriInventoryCategory::query()->orderBy('name')->get()
                    ->map(fn (MiriInventoryCategory $category) => [
                        'id' => $category->code, 'code' => $category->code, 'name' => $category->name,
                        'type' => 'major_equipment', 'active' => $category->active,
                    ]),
                'readOnly' => false,
                'miriMode' => true,
                'branchCode' => $branch->code,
            ]);
        }

        return Inertia::render('Shared/Categories/Index', [
            'categories' => Category::query()
                ->latest()
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'type' => $category->type->value,
                    'active' => $category->active,
                ]),
            'readOnly' => false,
            'miriMode' => false,
            'branchCode' => $branch?->code,
        ]);
    }

    public function storeMiri(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureMiri($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $branchId = app(BranchContext::class)->id($request->user());
        $next = ((int) MiriInventoryCategory::withoutGlobalScopes()->where('branch_id', $branchId)->max('id')) + 1;
        $category = MiriInventoryCategory::create([
            'branch_id' => $branchId,
            'code' => 'MIRI-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT),
            'name' => trim($data['name']),
            'active' => true,
        ]);
        $auditLogger->record(module: 'miri_categories', event: 'created', summary: "Created Miri category {$category->name}.", auditable: $category, after: $category->toArray(), user: $request->user(), request: $request);
        return back()->with('success', 'Miri category created.');
    }

    public function updateMiri(Request $request, string $category, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureMiri($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $record = MiriInventoryCategory::query()->where('code', $category)->firstOrFail();
        $before = $record->toArray();
        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data): void {
            MajorEquipment::query()->where('category', $record->name)->update(['category' => trim($data['name'])]);
            $record->update(['name' => trim($data['name'])]);
        });
        $auditLogger->record(module: 'miri_categories', event: 'updated', summary: "Renamed Miri category {$before['name']} to {$record->name}.", auditable: $record, before: $before, after: $record->fresh()->toArray(), user: $request->user(), request: $request);
        return back()->with('success', 'Miri category updated.');
    }

    public function destroyMiri(Request $request, string $category, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureMiri($request);
        $record = MiriInventoryCategory::query()->where('code', $category)->firstOrFail();
        if (MajorEquipment::query()->where('category', $record->name)->exists()) {
            return back()->with('error', 'This category cannot be deleted while equipment is assigned to it. Rename it or move the equipment first.');
        }
        $before = $record->toArray();
        $record->delete();
        $auditLogger->record(module: 'miri_categories', event: 'deleted', summary: "Deleted Miri category {$before['name']}.", auditable: $record, before: $before, after: [], user: $request->user(), request: $request);
        return back()->with('success', 'Miri category deleted.');
    }

    private function ensureMiri(Request $request): void
    {
        abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404);
    }

    public function store(StoreCategoryRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless(app(BranchContext::class)->branch($request->user())?->code !== 'MIRI', 404);
        $category = Category::create([
            ...$request->validated(),
            'code' => $this->generateCategoryCode(),
        ]);

        $auditLogger->record(
            module: 'categories',
            event: 'created',
            summary: "Created category {$category->code}.",
            auditable: $category,
            after: $category->only(['code', 'name', 'type', 'active']),
            user: $request->user(),
            request: $request,
        );

        return back()->with('success', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless(app(BranchContext::class)->branch($request->user())?->code !== 'MIRI', 404);
        $before = $category->only(['code', 'name', 'type', 'active']);
        $category->update($request->validated());
        $auditLogger->record(
            module: 'categories',
            event: 'updated',
            summary: "Updated category {$category->code}.",
            auditable: $category,
            before: $before,
            after: $category->fresh()->only(['code', 'name', 'type', 'active']),
            user: $request->user(),
            request: $request,
        );

        return back()->with('success', 'Category updated.');
    }

    private function generateCategoryCode(): string
    {
        $nextNumber = ((int) Category::query()->max('id')) + 1;

        do {
            $code = 'CAT-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Category::query()->where('code', $code)->exists());

        return $code;
    }
}
