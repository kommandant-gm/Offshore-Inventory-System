<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditTrailController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('settings'), 403);

        return Inertia::render('AuditTrail/Index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->latest('created_at')
                ->paginate(25)
                ->through(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'module' => $log->module,
                    'event' => $log->event,
                    'summary' => $log->summary,
                    'user' => $log->user?->name,
                    'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                    'before' => $log->before,
                    'after' => $log->after,
                ]),
        ]);
    }
}
