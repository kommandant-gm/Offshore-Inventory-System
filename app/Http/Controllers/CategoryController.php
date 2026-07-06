<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('categories'), 403);

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
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create([
            ...$request->validated(),
            'code' => $this->generateCategoryCode(),
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

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
