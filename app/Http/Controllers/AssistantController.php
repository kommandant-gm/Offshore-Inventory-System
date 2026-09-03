<?php

namespace App\Http\Controllers;

use App\Support\AssistantPrompts;
use App\Support\ItAssetAssistant;
use App\Services\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('assistant'), 403);
        abort_unless(app(BranchContext::class)->branch()?->code === 'KL-IT', 404);

        return Inertia::render('Assistant/Index', [
            'prompts' => AssistantPrompts::forBranch(app(BranchContext::class)->branch()?->code),
            'assistantContext' => $this->context(),
        ]);
    }

    public function query(Request $request, ItAssetAssistant $itAssistant, BranchContext $branches): JsonResponse
    {
        abort_unless($request->user()?->canRead('assistant'), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        abort_unless($branches->branch($request->user())?->code === 'KL-IT', 404);
        return response()->json($itAssistant->respond($validated['message'], $request->user()));
    }

    private function context(): array
    {
        return ['branch_code' => 'KL-IT', 'title' => 'KL IT Asset Assistant', 'subtitle' => 'Live IT asset answers', 'intro' => 'Ask about asset tags, serial numbers, assignments, operating systems, age, or repairs.', 'placeholder' => 'Ask about an IT asset...'];
    }
}
