<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\MajorEquipment;
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
                'categories' => MajorEquipment::query()
                    ->whereNotNull('category')->select('category')->distinct()->orderBy('category')->pluck('category')
                    ->values()->map(fn (string $name, int $index) => [
                        'id' => 'miri-'.$index,
                        'code' => 'MIRI-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                        'name' => $name, 'type' => 'major_equipment', 'active' => true, 'read_only' => true,
                    ]),
                'readOnly' => true,
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
            'branchCode' => $branch?->code,
        ]);
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
