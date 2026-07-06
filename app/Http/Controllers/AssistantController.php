<?php

namespace App\Http\Controllers;

use App\Support\InventoryAssistant;
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
            'prompts' => [
                'Where is CON-Y1-0001?',
                'What is the current stock for CAT-ROPE-001?',
                'Show anomalies',
                'Show critical anomalies',
                'Why is CON-Y1-0001 flagged?',
                'Show anomalies for Labuan Inventory',
                'Show anomalies for Consumables',
                'Show last movement for CAT-HOSE-009.',
                'Tell me about AIR HOSE 1 1/2".',
                'How many items are in Labuan Inventory?',
                'How many items are in Consumables?',
                'Total stock in Labuan Inventory',
            ],
        ]);
    }

    public function query(Request $request, InventoryAssistant $assistant): JsonResponse
    {
        abort_unless($request->user()?->canRead('assistant'), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($assistant->respond($validated['message'], $request->user()));
    }
}
