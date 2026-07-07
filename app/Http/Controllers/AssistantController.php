<?php

namespace App\Http\Controllers;

use App\Support\AssistantPrompts;
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
            'prompts' => AssistantPrompts::defaults(),
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
