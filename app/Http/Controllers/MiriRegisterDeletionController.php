<?php
namespace App\Http\Controllers;

use App\Models\{Branch, MajorEquipment, MiriRentalItem, MiriConstructionItem, MiriPaintItem};
use App\Services\{AuditLogger, BranchContext, CertificatePreviewService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Storage};
use Illuminate\Validation\ValidationException;

class MiriRegisterDeletionController extends Controller
{
    public function destroy(Request $request, string $register, int $item, AuditLogger $audit)
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        abort_unless($request->user()->canEdit('assets'), 403);
        $request->validate(['confirmed' => ['required', 'accepted']]);
        [$class, $type] = match ($register) {
            'major' => [MajorEquipment::class, 'Major equipment'],
            'rental' => [MiriRentalItem::class, 'Rental'],
            'construction' => [MiriConstructionItem::class, 'Construction'],
            'paint' => [MiriPaintItem::class, 'Paint'],
            default => abort(404),
        };
        DB::transaction(function () use ($class, $type, $register, $item, $branch, $request, $audit) {
            // COG creation and stock confirmation take this same branch lock.
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $record = $class::where('branch_id', $branch->id)->whereKey($item)->lockForUpdate()->firstOrFail();
            $linked = DB::table('miri_cog_items')->where('item_type', $type)->where('item_id', $item)->exists();
            if ($register === 'construction') {
                $linked = $linked || DB::table('miri_cog_items')->where('construction_destination_id', $item)->exists()
                    || DB::table('miri_construction_stock_movements')->where(fn ($q) => $q->where('construction_item_id', $item)->orWhere('related_item_id', $item))->exists();
            }
            if ($register === 'paint') {
                $linked = $linked || DB::table('miri_paint_stock_movements')->where('paint_item_id', $item)->exists()
                    || DB::table('miri_paint_stock_months')->where('paint_item_id', $item)->exists();
            }
            if ($linked) throw ValidationException::withMessages(['deletion' => 'This item is linked to COG documents or stock history and cannot be deleted. Its history must be retained.']);
            $files = [];
            if ($register === 'major') {
                foreach ($record->certificates as $certificate) {
                    if ($certificate->image_path) {
                        $files[] = ['certificates', $certificate->image_path];
                        $files[] = ['certificates', app(CertificatePreviewService::class)->path($certificate->image_path)];
                    }
                }
            }
            if ($register === 'construction') {
                foreach ($record->attachments ?? [] as $attachment) if (! empty($attachment['path'])) $files[] = ['construction', $attachment['path']];
            }
            // Do not copy private personnel fields or original imports into the audit log.
            $audit->record('miri_inventory', 'deleted', "Deleted {$type} register item #{$item}.", $record,
                before: $record->only(['id', 'company', 'description', 'category', 'current_location']),
                user: $request->user(), request: $request);
            $record->delete();
            DB::afterCommit(function () use ($files) {
                foreach ($files as [$disk, $path]) {
                    try { Storage::disk($disk)->delete($path); }
                    catch (\Throwable $error) { Log::warning('Deleted register item attachment cleanup failed', ['disk' => $disk, 'path' => $path]); }
                }
            });
        });
        return back()->with('success', 'Register item deleted.');
    }
}
