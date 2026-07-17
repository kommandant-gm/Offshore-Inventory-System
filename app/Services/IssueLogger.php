<?php

namespace App\Services;

use App\Models\IssueLog;
use ErrorException;
use Illuminate\Support\Facades\Schema;
use Throwable;

class IssueLogger
{
    public function record(Throwable $exception): void
    {
        try {
            if (! Schema::hasTable('issue_logs')) return;

            $request = app()->bound('request') ? request() : null;
            IssueLog::query()->create([
                'level' => $exception instanceof ErrorException ? 'warning' : 'error',
                'message' => mb_substr($exception->getMessage(), 0, 10000),
                'exception_class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'user_id' => auth()->id(),
                'ip_address' => $request?->ip(),
                'stack_trace' => mb_substr($exception->getTraceAsString(), 0, 65000),
                'context' => ['route' => $request?->route()?->getName()],
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never allow diagnostic persistence to hide the original exception.
        }
    }
}
