<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\ConstructionCsvService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportMiriConstruction implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 600;
    public bool $failOnTimeout = true;

    public function __construct(public string $taskId) { $this->onConnection('imports')->onQueue('imports')->afterCommit(); }

    public function handle(ConstructionCsvService $service): void
    {
        $task = DB::table('miri_construction_imports')->find($this->taskId);
        if (! $task || in_array($task->status, ['completed', 'failed'])) return;
        DB::table('miri_construction_imports')->where('id', $task->id)->where('status', 'queued')->update(['status' => 'processing', 'updated_at' => now()]);
        try {
            DB::transaction(function () use ($task, $service) {
                $locked = DB::table('miri_construction_imports')->where('id', $task->id)->lockForUpdate()->first();
                if (in_array($locked->status, ['completed', 'failed'])) return;
                abort_unless(Branch::whereKey($task->branch_id)->where('code', 'MIRI')->exists(), 403);
                $path = Storage::disk('construction')->path($task->file_path);
                if (! is_file($path) || hash_file('sha256', $path) !== $task->file_hash) throw new \RuntimeException('Staged file is missing or changed.');
                $file = new UploadedFile($path, $task->filename, 'text/csv', null, true);
                $result = $service->import($file, $task->branch_id);
                AuditLog::create(['branch_id' => $task->branch_id, 'user_id' => $task->user_id, 'module' => 'miri_construction', 'event' => 'imported',
                    'summary' => "Imported Construction register: {$result['created']} source rows. Balances preserved; no rows merged.", 'after' => $result, 'created_at' => now()]);
                DB::table('miri_construction_imports')->where('id', $task->id)->update(['status' => 'completed', 'result' => json_encode($result), 'updated_at' => now()]);
            });
        } catch (\Throwable $error) {
            $this->failed($error);
            throw $error;
        } finally { Storage::disk('construction')->delete($task->file_path); }
    }

    public function failed(?\Throwable $error): void
    {
        $task = DB::table('miri_construction_imports')->find($this->taskId);
        if (! $task || $task->status === 'completed') return;
        $message = $error instanceof ValidationException ? implode(' ', $error->errors()['file'] ?? ['CSV validation failed.']) : 'Import failed. No rows committed. Contact an administrator before retrying.';
        DB::table('miri_construction_imports')->where('id', $task->id)->where('status', '!=', 'completed')->update(['status' => 'failed', 'active_hash' => null, 'error' => $message, 'updated_at' => now()]);
        Storage::disk('construction')->delete($task->file_path);
        Log::error('Construction import failed', ['task_id' => $task->id, 'exception' => $error]);
    }
}
