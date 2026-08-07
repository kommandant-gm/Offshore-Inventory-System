<?php

namespace App\Http\Controllers;

use App\Models\ItMovementDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ItMovementRecordController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);

        $documents = ItMovementDocument::query()
            ->with('assignment.asset')
            ->whereHas('assignment')
            ->latest('generated_at')
            ->get()
            ->map(fn (ItMovementDocument $document) => [
                'id' => $document->id,
                'type' => $document->document_type,
                'filename' => $document->filename,
                'generated_at' => $document->generated_at?->format('Y-m-d H:i'),
                'asset_tag' => $document->assignment?->asset?->asset_tag_no,
                'description' => $document->assignment?->asset?->description ?: $document->assignment?->asset?->model,
                'staff' => $document->assignment?->assigned_to_name,
                'url' => route('it-movement-records.download', $document),
            ])->values();

        return Inertia::render('ItMovementRecords/Index', [
            'documents' => $documents,
            'summary' => [
                'total' => $documents->count(),
                'checkouts' => $documents->where('type', 'checkout')->count(),
                'checkins' => $documents->where('type', 'checkin')->count(),
            ],
        ]);
    }

    public function download(Request $request, ItMovementDocument $document)
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);
        abort_unless($document->assignment()->exists(), 404);

        return Storage::disk('local')->download($document->path, $document->filename, ['Content-Type' => 'application/pdf']);
    }
}
