<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\MiriEquipmentCsvService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportMiriEquipment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 600;
    public bool $failOnTimeout = true;

    public function __construct(public string $taskId) { $this->onConnection('imports'); $this->onQueue('imports'); $this->afterCommit(); }

    public function handle(MiriEquipmentCsvService $service): void
    {
        $task = DB::table('miri_import_tasks')->find($this->taskId);
        if (! $task || in_array($task->status, ['completed', 'failed'], true)) return;
        DB::table('miri_import_tasks')->where('id', $task->id)->where('status', 'queued')->update(['status' => 'processing', 'updated_at' => now()]);
        try {
            DB::transaction(function () use ($task, $service) {
                $locked = DB::table('miri_import_tasks')->where('id', $task->id)->lockForUpdate()->first();
                if (in_array($locked->status, ['completed', 'failed'], true)) return;
                $file = new UploadedFile(Storage::disk('local')->path($task->file_path), $task->filename, 'text/csv', null, true);
                $summary = $service->import($file, $task->user_id, $task->inventory_type);
                AuditLog::create(['branch_id' => $task->branch_id, 'user_id' => $task->user_id, 'module' => 'miri_inventory', 'event' => 'imported', 'summary' => "Imported Miri equipment: {$summary['created']} records and {$summary['certificates_created']} certificates.", 'after' => $summary, 'created_at' => now()]);
                DB::table('miri_import_tasks')->where('id', $task->id)->update(['status' => 'completed', 'result' => json_encode($summary), 'updated_at' => now()]);
            });
        } catch (\Throwable $error) {
            $this->failed($error);
            throw $error;
        } finally {
            Storage::disk('local')->delete($task->file_path);
        }
    }

    public function failed(?\Throwable $error): void
    {
        $task = DB::table('miri_import_tasks')->find($this->taskId);
        if (! $task || $task->status === 'completed') return;
        $message = $error instanceof ValidationException ? implode(' ', $error->validator->errors()->all()) : 'Import failed. No records were committed. Contact an administrator before retrying.';
        DB::table('miri_import_tasks')->where('id', $task->id)->where('status', '!=', 'completed')->update(['status' => 'failed', 'error' => $message, 'updated_at' => now()]);
        Storage::disk('local')->delete($task->file_path);
        Log::error('Miri import failed', ['task_id' => $task->id, 'exception' => $error]);
    }
}
