<?php

namespace App\Http\Controllers;

use App\Models\AssetAssignment;
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
                'assignment_id' => $document->asset_assignment_id,
                'reopen_url' => route('it-assets.checkout.reopen', $document->assignment?->asset_id),
            ])->values();

        $documentedAssignmentIds = $documents->pluck('assignment_id')->filter()->all();
        $signedWithoutPdf = AssetAssignment::query()
            ->with('asset')
            ->where('checkout_status', 'signed')
            ->where(function ($query) {
                $query->whereNotNull('signed_at')->orWhereNotNull('checkout_sent_at');
            })
            ->whereNull('returned_at')
            ->whereNotIn('id', $documentedAssignmentIds ?: [0])
            ->latest('signed_at')
            ->get()
            ->map(fn (AssetAssignment $assignment) => [
                'id' => 'recovery-'.$assignment->id,
                'type' => 'checkout',
                'filename' => null,
                'generated_at' => $assignment->signed_at?->format('Y-m-d H:i'),
                'asset_tag' => $assignment->asset?->asset_tag_no,
                'description' => $assignment->asset?->description ?: $assignment->asset?->model,
                'staff' => $assignment->assigned_to_name,
                'url' => null,
                'assignment_id' => $assignment->id,
                'reopen_url' => route('it-assets.checkout.reopen', $assignment->asset_id),
                'recovery' => true,
            ]);
        $documents = $documents->concat($signedWithoutPdf)->sortByDesc('generated_at')->values();

        $pending = AssetAssignment::query()
            ->with('asset')
            ->where('checkout_status', 'pending')
            ->whereNull('returned_at')
            ->latest('checkout_sent_at')
            ->get()
            ->map(fn (AssetAssignment $assignment) => [
                'asset_id' => $assignment->asset_id,
                'asset_tag' => $assignment->asset?->asset_tag_no,
                'description' => $assignment->asset?->description ?: $assignment->asset?->model,
                'staff' => $assignment->assigned_to_name,
                'email' => $assignment->assigned_email,
                'sent_at' => $assignment->checkout_sent_at?->format('Y-m-d H:i'),
                'resend_url' => route('it-assets.checkout.resend', $assignment->asset_id),
            ])->values();

        $staffMovements = AssetAssignment::query()
            ->with('asset')
            ->latest('assigned_at')
            ->latest('id')
            ->get()
            ->groupBy(fn (AssetAssignment $assignment) => mb_strtolower(trim((string) $assignment->assigned_to_name)))
            ->map(function ($assignments) {
                $first = $assignments->first();
                return [
                    'name' => $first->assigned_to_name,
                    'employee_id' => $first->employee_id,
                    'department' => $first->department,
                    'email' => $first->assigned_email,
                    'assets' => $assignments->map(fn (AssetAssignment $assignment) => [
                        'asset_tag' => $assignment->asset?->asset_tag_no,
                        'description' => $assignment->asset?->description ?: $assignment->asset?->model,
                        'assigned_at' => $assignment->assigned_at?->format('Y-m-d'),
                        'returned_at' => $assignment->returned_at?->format('Y-m-d'),
                        'status' => $assignment->returned_at ? 'Returned' : ($assignment->checkout_status === 'pending' ? 'Awaiting signature' : 'Deployed'),
                    ])->values(),
                ];
            })->values();

        return Inertia::render('ItMovementRecords/Index', [
            'documents' => $documents,
            'pending' => $pending,
            'staffMovements' => $staffMovements,
            'summary' => [
                'total' => $documents->count(),
                'checkouts' => $documents->where('type', 'checkout')->count(),
                'checkins' => $documents->where('type', 'checkin')->count(),
                'pending' => $pending->count(),
                'staff' => $staffMovements->count(),
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
