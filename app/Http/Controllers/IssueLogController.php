<?php

namespace App\Http\Controllers;

use App\Models\IssueLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $level = $request->string('level')->value();

        return Inertia::render('Settings/IssueLogs', [
            'level' => $level,
            'logs' => IssueLog::query()->with('user:id,name')->when(in_array($level, ['error', 'warning'], true), fn ($query) => $query->where('level', $level))
                ->latest()->paginate(25)->withQueryString()->through(fn (IssueLog $log) => self::format($log, true)),
        ]);
    }

    public static function format(IssueLog $log, bool $full = false): array
    {
        return [
            'id' => $log->id, 'level' => $log->level, 'message' => $log->message,
            'location' => $log->file ? "{$log->file}:{$log->line}" : null,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            'method' => $log->method, 'url' => $log->url, 'user' => $log->user?->name,
            'exception_class' => $log->exception_class,
            'stack_trace' => $full ? $log->stack_trace : null,
        ];
    }
}
