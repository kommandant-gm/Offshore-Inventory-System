<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MiriLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404);
        abort_unless($request->user()?->canRead('assets'), 403);

        $query = AuditLog::query()->with('user')->latest('created_at');
        $start = CarbonImmutable::now('Asia/Kuala_Lumpur')->startOfDay();
        $end = $start->addDay();

        return Inertia::render('MajorEquipment/MiriLog', [
            'summary' => [
                'total' => (clone $query)->count(),
                'today' => (clone $query)->where('created_at', '>=', $start->utc())
                    ->where('created_at', '<', $end->utc())->count(),
                'created' => (clone $query)->whereIn('event', ['created', 'imported', 'login'])->count(),
                'changed' => (clone $query)->whereIn('event', ['updated', 'deleted', 'signed', 'logout'])->count(),
            ],
            'logs' => $query->paginate(30)->withQueryString()->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name ?: 'System',
                'module' => str($log->module)->replace('_', ' ')->title()->toString(),
                'event' => $log->event,
                'summary' => $log->summary,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, H:i'),
            ]),
        ]);
    }
}
