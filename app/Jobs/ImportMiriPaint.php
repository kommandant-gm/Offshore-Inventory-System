<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\PaintCsvService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportMiriPaint implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 600;
    public bool $failOnTimeout = true;

    public function __construct(public string $taskId) { $this->onConnection('imports')->onQueue('imports')->afterCommit(); }

    public function handle(PaintCsvService $service): void
    {
        $task = DB::table('miri_paint_imports')->find($this->taskId);
        if (! $task || in_array($task->status, ['completed', 'failed'])) return;
        DB::table('miri_paint_imports')->where('id', $task->id)->where('status', 'queued')->update(['status' => 'processing', 'updated_at' => now()]);
        try {
            DB::transaction(function () use ($task, $service) {
                $locked = DB::table('miri_paint_imports')->where('id', $task->id)->lockForUpdate()->first();
                if (in_array($locked->status, ['completed', 'failed'])) return;
                abort_unless(Branch::whereKey($task->branch_id)->where('code', 'MIRI')->exists(), 403);
                $path = Storage::disk('paint')->path($task->file_path);
                if (! is_file($path) || hash_file('sha256', $path) !== $task->file_hash) throw new \RuntimeException('Staged file is missing or changed.');
                $file = new UploadedFile($path, $task->filename, 'text/csv', null, true);
                $result = $service->import($file, $task->branch_id);
                AuditLog::create(['branch_id' => $task->branch_id, 'user_id' => $task->user_id, 'module' => 'miri_paint', 'event' => 'imported',
                    'summary' => "Imported Paint register: {$result['created']} source rows. Balances preserved; no rows merged.", 'after' => $result, 'created_at' => now()]);
                DB::table('miri_paint_imports')->where('id', $task->id)->update(['status' => 'completed', 'result' => json_encode($result), 'updated_at' => now()]);
            });
        } catch (\Throwable $error) {
            $this->failed($error);
            throw $error;
        } finally { Storage::disk('paint')->delete($task->file_path); }
    }

    public function failed(?\Throwable $error): void
    {
        $task = DB::table('miri_paint_imports')->find($this->taskId);
        if (! $task || $task->status === 'completed') return;
        $message = $error instanceof ValidationException ? implode(' ', $error->errors()['file'] ?? ['CSV validation failed.']) : 'Import failed. No rows committed. Contact an administrator before retrying.';
        DB::table('miri_paint_imports')->where('id', $task->id)->where('status', '!=', 'completed')->update(['status' => 'failed', 'active_hash' => null, 'error' => $message, 'updated_at' => now()]);
        Storage::disk('paint')->delete($task->file_path);
        Log::error('Paint import failed', ['task_id' => $task->id, 'exception' => $error]);
    }
}
