<?php

namespace App\Http\Controllers;

use App\Support\AssistantPrompts;
use App\Support\InventoryAssistant;
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

        return Inertia::render('Assistant/Index', [
            'prompts' => AssistantPrompts::forBranch(app(BranchContext::class)->branch()?->code),
            'assistantContext' => $this->context(),
        ]);
    }

    public function query(Request $request, InventoryAssistant $assistant, ItAssetAssistant $itAssistant, BranchContext $branches): JsonResponse
    {
        abort_unless($request->user()?->canRead('assistant'), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $handler = $branches->branch($request->user())?->code === 'KL-IT' ? $itAssistant : $assistant;
        return response()->json($handler->respond($validated['message'], $request->user()));
    }

    private function context(): array
    {
        $code=app(BranchContext::class)->branch()?->code; $kl=$code==='KL-IT';
        return ['branch_code'=>$code,'title'=>$kl?'KL IT Asset Assistant':'Miri Inventory Assistant','subtitle'=>$kl?'Live IT asset answers':'Live inventory answers','intro'=>$kl?'Ask about asset tags, serial numbers, assignments, operating systems, age, or repairs.':'Ask about item location, current stock, last movement, or stock anomalies.','placeholder'=>$kl?'Ask about an IT asset...':'Ask about a stock item...'];
    }
}
